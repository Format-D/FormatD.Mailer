<?php

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

/**
 * @Flow\Aspect
 */
class MailerTransportAspect
{
    #[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
    protected array $settings;

    #[Flow\InjectConfiguration(path: 'mailer', package: 'Neos.SymfonyMailer')]
    protected array $symfonyMailerSettings;

    #[Flow\InjectConfiguration(path: 'mailer.additionalTransportFactories', package: 'FormatD.Mailer')]
    protected array $additionalTransportFactories = [];

    protected ?Mailer $mailer = null;

    /**
     * @Flow\Around("method(Neos\SymfonyMailer\Service\MailerService->getMailer())")
     */
    public function decorateMailer(JoinPointInterface $joinPoint): MailerInterface
    {
        if (!($this->settings['interceptAll']['active'] ?? false) && !($this->settings['bccAll']['active'] ?? false)) {
            if ($this->mailer === null) {
                $dsn = $this->symfonyMailerSettings['dsn'] ?? null;
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

    protected function createTransport(string $dsn): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        $httpClient = HttpClient::create();
        $factories = iterator_to_array(Transport::getDefaultFactories(client: $httpClient));
        foreach ($this->additionalTransportFactories as $factoryClass) {
            if (class_exists($factoryClass)) {
                $factories[] = new $factoryClass(null, $httpClient);
            }
        }

        return (new Transport($factories))->fromString($dsn);
    }
}
