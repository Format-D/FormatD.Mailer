<?php

namespace FormatD\Mailer\Aspect;

/*
 * This file is part of the FormatD.Mailer package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * @Flow\Aspect
 * @Flow\Introduce("class(Symfony\Component\Mime\Email)", traitName="FormatD\Mailer\Traits\InterceptionTrait")
 */
class DebuggingAspect
{
    /**
     * @Flow\InjectConfiguration(type="Settings", package="FormatD.Mailer")
     * @var array
     */
    protected $settings;

    /**
     * Intercept all emails or add bcc according to package configuration
     *
     * @param JoinPointInterface $joinPoint
     * @Flow\Before("method(Symfony\Component\Mailer->send())")
     * @return void
     */
    public function interceptEmails(JoinPointInterface $joinPoint)
    {
        if ($this->settings['interceptAll']['active'] || $this->settings['bccAll']['active']) {
            /**
             * @var RawMessage $message
             */
            $message = $joinPoint->getProxy();

            if (!($message instanceof Email) || !method_exists($message, 'isIntercepted') || !method_exists($message, 'setIntercepted')) {
                return;
            }

            if ($this->settings['interceptAll']['active']) {
                $oldTo = $message->getTo();
                $oldCc = $message->getCc();
                $oldBcc = $message->getBcc();

                foreach ($this->settings['interceptAll']['noInterceptPatterns'] as $pattern) {
                    if (preg_match($pattern, reset($oldTo)->getAddress())) {
                        // let the mail through but clean all cc and bcc fields
                        $message->getHeaders()->remove('Cc');
                        $message->getHeaders()->remove('Bcc');
                        return;
                    }
                }

                // stop if this aspect is executed twice (happens if QueueAdaptor is installed)
                if ($message->isIntercepted()) {
                    return;
                }

                $interceptedRecipients = [
                    reset($oldTo)->getAddress(),
                    $oldCc ? 'CC: ' . reset($oldCc)->getAddress() : '',
                    $oldBcc ? 'BCC: ' . reset($oldBcc)->getAddress() : '',
                ];

                $interceptedRecipients = implode(' ', array_filter($interceptedRecipients));
                $message->subject('[intercepted ' . $interceptedRecipients . '] ' . $message->getSubject());
                $message->setIntercepted(true);

                $message->getHeaders()->remove('Cc');
                $message->getHeaders()->remove('Bcc');

                $first = true;
                foreach ($this->settings['interceptAll']['recipients'] as $email) {
                    if ($first) {
                        $message->to($email);
                    } else {
                        $message->addCc($email);
                    }
                    $first = false;
                }
            }

            if ($this->settings['bccAll']['active']) {
                foreach ($this->settings['bccAll']['recipients'] as $email) {
                    $message->addBcc($email);
                }
            }
        }
    }
}

?>
