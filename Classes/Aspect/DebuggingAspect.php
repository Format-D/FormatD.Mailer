<?php

declare(strict_types=1);

namespace FormatD\Mailer\Aspect;

/*
 * This file is part of the FormatD.Mailer package.
 */

use FormatD\Mailer\Transport\FdMailerTransport;
use FormatD\Mailer\Transport\InterceptingTransport;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Supplies the mailer of Neos.SymfonyMailer: the `fd-mailer` DSN gets the FormatD SMTP transport, every other DSN a
 * transport built from Symfony's default factories plus the configured additional transport factories. With
 * interceptAll or bccAll active, every transport is wrapped in the intercepting transport.
 *
 * Without interception, a transport passed to getMailer() and a missing DSN stay with Neos.SymfonyMailer.
 */
#[Flow\Aspect]
class DebuggingAspect
{
    #[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
    protected array $settings;

    #[Flow\InjectConfiguration(path: 'mailer', package: 'Neos.SymfonyMailer')]
    protected array $symfonyMailerSettings;

    /**
     * @var array<string>|null
     */
    #[Flow\InjectConfiguration(path: 'mailer.additionalTransportFactories', package: 'FormatD.Mailer')]
    protected ?array $additionalTransportFactories = [];

    protected ?Mailer $mailer = null;

    #[Flow\Around('method(Neos\SymfonyMailer\Service\MailerService->getMailer())')]
    public function decorateMailer(JoinPointInterface $joinPoint): MailerInterface
    {
        if (!($this->settings['interceptAll']['active'] ?? false) && !($this->settings['bccAll']['active'] ?? false)) {
            $dsn = $this->symfonyMailerSettings['dsn'] ?? null;
            if (($joinPoint->getMethodArguments()['transport'] ?? null) !== null || $dsn === null) {
                return $joinPoint->getAdviceChain()->proceed($joinPoint);
            }
            if ($this->mailer === null) {
                $this->mailer = $dsn === 'fd-mailer'
                    ? new Mailer(new FdMailerTransport())
                    : new Mailer($this->createTransport((string)$dsn));
            }
            return $this->mailer;
        }

        if ($this->mailer === null) {
            $dsn = $this->symfonyMailerSettings['dsn'];
            if ($dsn === 'fd-mailer') {
                $actualTransport = new FdMailerTransport();
            } else {
                $actualTransport = $this->createTransport($dsn);
            }
            $interceptingTransport = new InterceptingTransport($actualTransport);
            $this->mailer = new Mailer($interceptingTransport);
        }

        return $this->mailer;
    }

    protected function createTransport(string $dsn): TransportInterface
    {
        // symfony/http-client is optional: without it, transports needing HTTP create their own client or fail on use
        $httpClient = class_exists(HttpClient::class) ? HttpClient::create() : null;
        $factories = iterator_to_array(Transport::getDefaultFactories(client: $httpClient));
        foreach ($this->additionalTransportFactories ?? [] as $factoryClass) {
            if (class_exists($factoryClass)) {
                $factories[] = new $factoryClass(null, $httpClient);
            }
        }

        return (new Transport($factories))->fromString($dsn);
    }
}
