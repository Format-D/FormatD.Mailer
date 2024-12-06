<?php
namespace FormatD\Mailer\Command;

use FormatD\Mailer\Service\AbstractMailerService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class MailerCommandController extends CommandController
{
    #[Flow\Inject]
    protected AbstractMailerService $abstractMailerService;

    /**
    * @param string $to
    * ./flow mailer:sendTest --to someone@somewhere.com
    */
    public function sendTestCommand($to)
    {
        $this->abstractMailerService->sendTest($to);
    }
}
