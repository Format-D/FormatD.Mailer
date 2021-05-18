<?php
namespace FormatD\Mailer\Aspect;

/*                                                                        *
 * This script belongs to the Flow package "FormatD.Mailer".              *
 *                                                                        */

use Neos\Flow\Annotations as Flow;


/**
 * @Flow\Aspect
 * @Flow\Introduce("class(Neos\SwiftMailer\Message)", traitName="FormatD\Mailer\Traits\InterceptionTrait")
 */
class DebuggingAspect {

	/**
	 * @Flow\InjectConfiguration(type="Settings", package="FormatD.Mailer")
	 * @var array
	 */
	protected $settings;

	/**
	 * Intercept all emails or add bcc according to package configuration
	 *
	 * @param \Neos\Flow\Aop\JoinPointInterface $joinPoint
	 * @Flow\Before("method(Neos\SwiftMailer\Message->send())")
	 * @return void
	 */
	public function interceptEmails(\Neos\Flow\Aop\JoinPointInterface $joinPoint) {

		if ($this->settings['interceptAll']['active'] || $this->settings['bccAll']['active']) {
			/**
			 * @var \Neos\SwiftMailer\Message $message
			 */
			$message = $joinPoint->getProxy();

			if ($this->settings['interceptAll']['active']) {

				$oldTo = $message->getTo();
				$oldCc = $message->getCc();
				$oldBcc = $message->getBcc();

				foreach ($this->settings['interceptAll']['noInterceptPatterns'] as $pattern) {
					if (preg_match($pattern, key($oldTo))) {
						// let the mail through but clean all cc and bcc fields
						$message->setCc(array());
						$message->setBcc(array());
						return;
					}
				}

				// stop if this aspect is executed twice (happens if QueueAdaptor is installed)
				if ($message->isIntercepted()) {
					return;
				}

				$interceptedRecipients = key($oldTo) . ($oldCc ? ' CC: ' . key($oldCc) : '') . ($oldBcc ? ' BCC: ' . key($oldBcc) : '');
				$message->setSubject('[intercepted '.$interceptedRecipients.'] '.$message->getSubject());
				$message->setIntercepted(true);

				$message->setCc(array());
				$message->setBcc(array());

				$first = true;
				foreach ($this->settings['interceptAll']['recipients'] as $email) {
					if ($first) {
						$message->setTo($email);
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
