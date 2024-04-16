<?php
namespace FormatD\Mailer\Service;


use FormatD\Mailer\Traits\InterceptionTrait;
use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use FormatD\Mailer\Factories\MailerFactory;
use FormatD\Mailer\Factories\MailFactory;
use Symfony\Component\Mailer\MailerInterface;


/**
 * Mail service - can be extended in own packages
 *
 * @Flow\Scope("singleton")
 */
class AbstractMailerService
{

    use InterceptionTrait;

    #[Flow\InjectConfiguration(package: "Neos.Flow", path: "http.baseUri")]
	protected string $baseUri;

    #[Flow\Inject]
    protected MailerFactory $mailerFactory;

    #[Flow\Inject]
    protected MailFactory $mailFactory;

    protected MailerInterface $mailer;

    protected Address $defaultFromAddress;

    public function initializeObject()
    {
        $this->mailer = $this->mailerFactory->createMailer();
        $this->defaultFromAddress = new Address($this->configuration['defaultFrom']['address'], $this->configuration['defaultFrom']['name']);
    }

	protected function send($subject, $to, $from, $text, $html) {
        $mail = $this->mailFactory->createMail(
            $subject,
            $to,
            $from ? $from : $this->defaultFromAddress,
            $text,
            $html
        );

        if ($this->configuration['interceptAll']['active'] || $this->configuration['bccAll']['active']) {
            $mail = $this->intercept($mail);
        }

        $this->mailer->send($mail);
	}

	/**
	 * @param array|Address|string $to
	 */
	public function sendTest($to)
    {
        $mail = $this->mailFactory->createMail(
            'Format D Mailer // Test E-Mail',
            $to,
            $this->defaultFromAddress,
            'Hello guys, this is a test e-mail',
            // get html from fusion template
        );

        $this->mailer->send($mail);
	}
}
