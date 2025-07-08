<?php

namespace FormatD\Mailer\Service;

/*
 * This file is part of the FormatD.Mailer package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\FluidAdaptor\View\Exception\InvalidSectionException;
use Neos\FluidAdaptor\View\StandaloneView as FluidStandaloneView;
use Neos\SymfonyMailer\Exception\InvalidMailerConfigurationException;
use Neos\SymfonyMailer\Service\MailerService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * A Abstract Base MailService to extend in custom projects
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractMailerService
{
    /**
     * Is only there to don't loose backwards compatibility
     *
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\InjectConfiguration(type="Settings", package="FormatD.Mailer")
     * @var array
     */
    protected $mailSettings;

    /**
     * @Flow\InjectConfiguration(type="Settings", package="Neos.Flow", path="http.baseUri")
     * @var string
     */
    protected $baseUri;

    /**
     * @var array<string, string>
     */
    protected $defaultFrom = array();

    /**
     * Message which is processed at the moment
     */
    protected Message $processedMessage;

    #[Flow\Inject]
    protected MailerService $coreMailerService;

    /**
     * Get Configuration
     */
    public function initializeObject()
    {
        $this->defaultFrom = array($this->mailSettings['defaultFrom']['address'] => $this->mailSettings['defaultFrom']['name']);
    }

    /**
     * Creates new email message object and stores it as processedMessage
     *
     * @return Message
     */
    public function createMessage()
    {
        $message = new Email();
        reset($this->defaultFrom);
        $message->from(new Address(key($this->defaultFrom), current($this->defaultFrom)));
        $this->processedMessage = $message;
        return $message;
    }

    /**
     * Creates StandaloneView from templatePath and assigns default variables
     *
     * @param $templatePath
     * @return FluidStandaloneView
     */
    public function createStandaloneView($templatePath)
    {
        $view = $this->objectManager->get('Neos\FluidAdaptor\View\StandaloneView');
        $view->setTemplatePathAndFilename('resource://' . $templatePath);
        $view->setFormat('html');
        $view->assign('baseUri', $this->baseUri);
        return $view;
    }

    /**
     * Convert inline images in html to attached ones
     *
     * @param string $html
     * @return string
     */
    protected function attachHtmlInlineImages($html)
    {
        return preg_replace_callback('#(<img [^>]*[ ]?src=")([^"]+)("[^>]*>)#', $this->attachHtmlInlineImage(...), $html);
    }

    /**
     * Substitution function called by preg_replace_callback
     *
     * @param $match
     * @return string
     */
    public function attachHtmlInlineImage($match)
    {
        $completeMatch = $match[0];
        $imgTagStart = $match[1];
        $path = $match[2];
        $imgTagEnd = $match[3];

        // you can disable embedding with data attribute (useful for tracking pixel)
        if (str_contains($completeMatch, 'data-fdmailer-embed="disable"')) {
            return $completeMatch;
        }

        // only use local embed if nothing else can work (legacy mode)
        if (!isset($this->mailSettings['localEmbed']) || $this->mailSettings['localEmbed'] === false) {
            // if in cli we do not know the baseurl so we request the file locally
            if (FLOW_SAPITYPE == 'CLI' && !preg_match('#^http.*#', $path)) {
                $path = FLOW_PATH_WEB . $path;
            }
        } else if ($this->mailSettings['localEmbed']) {
            if (preg_match('#^http.*#', $path) && $this->baseUri) {
                // if we know the baseUri we remove it to be able to convert the path to a local path
                $path = str_replace($this->baseUri, "", $path, $replaceCount);
            }
            if (!preg_match('#^http.*#', $path)) {
                // if path is now relative to document root we prepend local path
                $path = FLOW_PATH_WEB . $path;
            }
        }

        if ($this->mailSettings['attachEmbeddedImages']) {
            $part = new DataPart(new File(urldecode($path)));
            $this->processedMessage->addPart($part->asInline());
            $path = 'cid' . $part->getContentId();
        }

        return sprintf('%s%s%s', $imgTagStart, $path, $imgTagEnd);
    }

    /**
     * Sets the mailcontent from a standalone view
     *
     * @param Email $message
     * @param FluidStandaloneView $view
     * @param bool $embedImages
     * @return void
     * @throws InvalidSectionException
     */
    protected function setMailContentFromStandaloneView(Email $message, FluidStandaloneView $view, $embedImages = false)
    {
        $subject = trim($view->renderSection('subject'));
        $html = trim($view->renderSection('html'));
        $plain = trim($view->renderSection('plain', [], true));

        if ($embedImages) $html = $this->attachHtmlInlineImages($html);

        $message->subject($subject);
        if ($html) {
            $message->html($html);
        }
        if ($plain) {
            $message->text($plain);
        }
    }

    /**
     * Sends a message
     *
     * @param Email $message
     * @throws InvalidMailerConfigurationException|TransportExceptionInterface
     */
    protected function sendMail(Email $message)
    {
        $this->coreMailerService->getMailer()->send($message);
    }

    /**
     * Sends test email to check the configuration
     *
     * @param string|array $to
     * @return void
     * @throws InvalidSectionException|InvalidMailerConfigurationException|TransportExceptionInterface
     */
    public function sendTestMail($to)
    {
        $mail = $this->createMessage();

        $view = $this->createStandaloneView('FormatD.Mailer/Private/Templates/Notifications/TestMail.html');
        $view->assign('teststring', 'HelloWorld');

        $this->setMailContentFromStandaloneView($mail, $view, true);
        $mail->to($to);

        $this->sendMail($mail);
    }
}