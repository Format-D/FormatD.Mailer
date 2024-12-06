<?php

declare(strict_types=1);

namespace FormatD\Mailer\Factories;

use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

class MailerFactory
{

    #[Flow\InjectConfiguration(package: "FormatD.Mailer", path: "")]
    protected array $configuration = [];

    /**
     * @param string|string[]|null $dsn
     */
    public function createMailer(string|array $dsn = null): MailerInterface
    {
        $dsn = $dsn ?: $this->configuration['dsn'] ?? null;

        if (is_array($dsn)) {
            $transport = Transport::fromDsns($dsn);
        } elseif (is_string($dsn)) {
            $transport = Transport::fromDsn($dsn);
        } else {
            throw new \InvalidArgumentException("Mailer needs a transport dsn configuration");
        }

        return new Mailer($transport);
    }
}
