<?php
namespace FormatD\Mailer\Command;

use FormatD\Mailer\Service\DefaultMailerService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class MailerCommandController extends CommandController
{
    #[Flow\Inject]
    protected DefaultMailerService $mailerService;

    /**
     * Send a test email using a Fluid template
     *
     * @param string $to Recipient email address
     */
    public function sendTestCommand(string $to)
    {
        $this->mailerService->sendTestMail($to);
        $this->outputLine('Test email sent to %s (Fluid template)', [$to]);
    }

    /**
     * Send a test email using a Content Repository template node
     *
     * @param string $to Recipient email address
     */
    public function sendTestFromTemplateNodeCommand(string $to)
    {
        $this->mailerService->sendTestMailFromContentRepository($to);
        $this->outputLine('Test email sent to %s (Content Repository template)', [$to]);
    }
}
