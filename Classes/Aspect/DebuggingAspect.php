<?php

namespace FormatD\Mailer\Aspect;

/*
 * This file is part of the FormatD.Mailer package.
 */

use FormatD\Mailer\Transport\FdMailerTransport;
use FormatD\Mailer\Transport\InterceptingTransport;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

/**
 * @Flow\Aspect
 */
class DebuggingAspect
{
    #[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
    protected array $settings;

    #[Flow\InjectConfiguration(path: 'mailer', package: 'Neos.SymfonyMailer')]
    protected array $symfonyMailerSettings;

    protected ?Mailer $mailer = null;

    /**
     * @Flow\Around("method(Neos\SymfonyMailer\Service\MailerService->getMailer())")
     */
    public function decorateMailer(JoinPointInterface $joinPoint): MailerInterface
    {
        if (!($this->settings['interceptAll']['active'] ?? false) && !($this->settings['bccAll']['active'] ?? false)) {
            if ($this->mailer === null && ($this->symfonyMailerSettings['dsn'] ?? null) === 'fd-mailer') {
                $this->mailer = new Mailer(new FdMailerTransport());
            } else {
                $this->mailer = $joinPoint->getAdviceChain()->proceed($joinPoint);
            }
            return $this->mailer;
		}

        if ($this->mailer === null) {
            $dsn = $this->symfonyMailerSettings['dsn'];
            if ($dsn === 'fd-mailer') {
                $actualTransport = new FdMailerTransport();
            } else {
                $actualTransport = Transport::fromDsn($this->symfonyMailerSettings['dsn']);
            }
            $interceptingTransport = new InterceptingTransport($actualTransport);
            $this->mailer = new Mailer($interceptingTransport);
        }

        return $this->mailer;
    }
}
