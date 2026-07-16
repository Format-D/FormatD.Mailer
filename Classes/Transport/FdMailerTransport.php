<?php

namespace FormatD\Mailer\Transport;

use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Email;

class FdMailerTransport implements TransportInterface
{
	#[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
	protected array $settings;

	protected ?TransportInterface $actualTransport = null;

	#[Flow\Inject(name: "FormatD.Mailer:MailerLogger", lazy: false)]
	protected LoggerInterface $logger;

	/**
	 * @throws TransportExceptionInterface|\Exception
	 */
	public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
	{
		if ($this->actualTransport === null) {
			$this->actualTransport = Transport::fromDsn($this->getActualTransportDsn());
		}

		$sentMessage = $this->actualTransport->send($message, $envelope);

		$this->logEmailDispatch($sentMessage, $message);

		return $sentMessage;
	}

	/**
	 * @throws \Exception
	 */
	protected function getActualTransportDsn(): string
	{
		$config = $this->settings['smtpTransport'] ?? null;
		if (!is_array($config)) {
			throw new \Exception('Missing FormatD.Mailer settings.');
		}

		if (!($config['host'] ?? null)) {
			throw new \Exception('Missing configuration: FormatD.Mailer.smtpTransport.host');
		}

		$useTlsChannel = $config['encryption'] ?? null;
		$useTlsChannel = (bool)($useTlsChannel === 'false' ? false : $useTlsChannel);
		$scheme = $useTlsChannel ? 'smtps' : 'smtp';

		$credentials = '';
		if (($config['username'] ?? null) && ($config['password'] ?? null)) {
			$credentials = $config['username'] . ':' . $config['password'] . '@';
		}

		$host = $config['host'];
		$port = ($config['port'] ?? null) ? ':' . $config['port'] : '';

		$query = '';
		if (is_array($config['options'] ?? null)) {
			$query = http_build_query($config['options'], '', '&');
		}

		return $scheme . '://' . $credentials . $host . $port . ($query ? '?' . $query : '');
	}

	public function __toString(): string
	{
		return 'fd-mailer';
	}

	protected function logEmailDispatch(?SentMessage $sentMessage, RawMessage $message): void
	{
		$isLoggable = $sentMessage instanceof SentMessage
			&& $message instanceof Email
			&& $this->settings['logging']['emailDispatch'];

		if (!$isLoggable) {
			return;
		}

		$subject = $message->getSubject();
		if (mb_strlen($subject) > 50) {
			$subject = mb_substr($subject, 0, 50) . ' …';
		}
		$from = implode(', ', array_map(fn($a) => $a->toString(), $message->getFrom()));
		$to = implode(', ', array_map(fn($a) => $a->toString(), $message->getTo()));

		$this->logger->info(vsprintf('Sent email to %s (from %s) with subject "%s"', [$to, $from, $subject]));
	}
}
