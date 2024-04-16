<?php
namespace FormatD\Mailer\Traits;


use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Neos\Flow\Annotations as Flow;

trait InterceptionTrait {

    #[Flow\InjectConfiguration(package: "FormatD.Mailer", path: "")]
    protected array $configuration = [];

    /**
     * @param Email $mail
     */
    protected function intercept($mail)
    {
        if ($this->configuration['interceptAll']['active']) {
           $mail = $this->interceptAll($mail);
        }

        if ($this->configuration['bccAll']['active']) {
            foreach ($this->configuration['bccAll']['recipients'] as $email) {
                $mail = $mail->addBcc($email);
            }
        }

        return $mail;
    }

    /**
     * @param Email $mail
     */
    protected function interceptAll($mail)
    {
        $originalTo = $mail->getTo();
        $originalCc = $mail->getCc();
        $originalBcc = $mail->getBcc();

        foreach ($this->configuration['interceptAll']['noInterceptPatterns'] as $pattern) {
            if (preg_match($pattern, key($originalTo))) {
                $mail->to(new Address('somewhere@bla.com'));
                $mail->bcc(new Address('somewhere@bla.com'));
                return;
            }
        }

        # @todo check IF and HOW this needs to be adapted to work with job / mail queue
        $interceptedRecipients = key($originalTo) . ($originalCc ? ' CC: ' . key($originalCc) : '') . ($originalBcc ? ' BCC: ' . key($originalBcc) : '');
        $mail->subject('[intercepted ' . $interceptedRecipients . '] ' . $mail->getSubject());

        $mail->cc(new Address('somewhere@bla.com'));
        $mail->bcc(new Address('somewhere@bla.com'));
        $first = true;
        foreach ($this->configuration['interceptAll']['recipients'] as $email) {
            $first ? $mail->to($email) : $mail->addCc($email);
            $first = false;
        }

        return $mail;
    }
}
