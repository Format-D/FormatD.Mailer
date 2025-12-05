<?php

namespace FormatD\Mailer\Transport;

use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class FdMailerTransport implements TransportInterface
{
	#[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
	protected array $settings;

	protected ?TransportInterface $actualTransport = null;

	/**
	 * @throws TransportExceptionInterface|\Exception
	 */
	public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
	{
		if ($this->actualTransport === null) {
			$this->actualTransport = Transport::fromDsn($this->getActualTransportDsn());
		}
		return $this->actualTransport->send($message);
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
		$useTlsChannel = (bool)($useTlsChannel === 'false' || (string)$useTlsChannel === '0' ? false : $useTlsChannel);
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
}
