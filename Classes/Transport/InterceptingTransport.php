<?php

namespace FormatD\Mailer\Transport;

use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class InterceptingTransport implements TransportInterface
{
	#[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
	protected array $settings;

	public function __construct(protected TransportInterface $actualTransport)
	{
	}

	public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
	{
		// Prevent interception when mail has already been intercepted before
		if ($message instanceof Email) {
			$message = $this->interceptMessage($message);
		}

		return $this->actualTransport->send($message);
	}

	protected function interceptMessage(Email $message): Email
	{
		if ($this->settings['interceptAll']['active'] ?? false) {
			$oldTo = $message->getTo();
			$oldCc = $message->getCc();
			$oldBcc = $message->getBcc();

			foreach ($this->settings['interceptAll']['noInterceptPatterns'] as $pattern) {
				if (preg_match($pattern, reset($oldTo)->getAddress())) {
					// let the mail through but clean all cc and bcc fields
					$message->getHeaders()->remove('Cc');
					$message->getHeaders()->remove('Bcc');
					return $message;
				}
			}

			$interceptedRecipients = [
				reset($oldTo)->getAddress(),
				$oldCc ? 'CC: ' . reset($oldCc)->getAddress() : '',
				$oldBcc ? 'BCC: ' . reset($oldBcc)->getAddress() : '',
			];

			$interceptedRecipients = implode(' ', array_filter($interceptedRecipients));
			$message->subject('[intercepted ' . $interceptedRecipients . '] ' . $message->getSubject());

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

		return $message;
	}

	public function __toString(): string
	{
		return 'intercept';
	}
}
