<?php

namespace FormatD\Mailer\Service;

/*
 * This file is part of the FormatD.Mailer package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\FluidAdaptor\View\Exception\InvalidSectionException;
use Neos\FluidAdaptor\View\StandaloneView as FluidStandaloneView;
use Neos\SymfonyMailer\Exception\InvalidMailerConfigurationException;
use Neos\SymfonyMailer\Service\MailerService;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use FormatD\Mailer\Factories\MailFactory;

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

    #[Flow\Inject]
    protected ContentRepositoryService $contentRepositoryService;

    #[Flow\Inject]
    protected MailFactory $mailFactory;

    #[Flow\Inject(name: "FormatD.Mailer:MailerLogger")]
    protected $mailerLogger;

    protected $client;

    /**
     * Get Configuration
     */
    public function initializeObject()
    {
        $this->defaultFrom = array($this->mailSettings['defaultFrom']['address'] => $this->mailSettings['defaultFrom']['name']);

        $clientOptions = [];
        $clientOptions = $this->setSslVerification($clientOptions);
        $this->client = new Client($clientOptions);
    }

    protected function setSslVerification(array $clientOptions): array
    {
        $flowContext = Bootstrap::getEnvironmentConfigurationSetting('FLOW_CONTEXT');
        $clientOptions['verify'] = !str_contains($flowContext, 'Development');

        return $clientOptions;
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
     * Sends a message via the core mailer service (intercepted by AOP aspect)
     *
     * @param Email $message
     * @throws InvalidMailerConfigurationException|TransportExceptionInterface
     */
    protected function sendMail(Email $message)
    {
        $this->coreMailerService->getMailer()->send($message);
    }

    /**
     * Convenience method to send an email with individual parameters.
     * Uses MailFactory to create the email and routes through sendMail()
     * which is intercepted by the AOP DebuggingAspect.
     *
     * @param string $subject
     * @param Address[]|Address|string $to
     * @param Address|string|null $from
     * @param string|null $text
     * @param string|null $html
     * @param Address|string|null $replyTo
     * @param Address|string|null $cc
     * @param Address|string|null $bcc
     * @throws InvalidMailerConfigurationException|TransportExceptionInterface
     */
    public function send($subject, $to, $from = null, $text = null, $html = null, $replyTo = null, $cc = null, $bcc = null)
    {
        if ($from === null) {
            reset($this->defaultFrom);
            $from = new Address(key($this->defaultFrom), current($this->defaultFrom));
        }

        $mail = $this->mailFactory->createMail(
            $subject,
            $to,
            $from,
            $text,
            $html,
            $replyTo,
            $cc,
            $bcc
        );

        $this->sendMail($mail);
    }

    /**
     * Get a Content Repository node by its aggregate ID
     */
    public function getNodeById(string $id): Node
    {
        $contentRepository = $this->contentRepositoryService->getContentRepository();
        $contentGraph = $this->contentRepositoryService->getContentGraph($contentRepository);

        $generalizations = $contentRepository->getVariationGraph()->getRootGeneralizations();
        $dimensionSpacePoint = reset($generalizations);

        $subgraph = $contentGraph->getSubgraph(
            $dimensionSpacePoint,
            VisibilityConstraints::withoutRestrictions(),
        );

        return $subgraph->findNodeById(NodeAggregateId::fromString($id));
    }

    /**
     * Render a Content Repository email node to HTML via HTTP request
     */
    public function getHtml(Node $emailNode): string
    {
        $emailUri = $this->contentRepositoryService->uriForNode($emailNode);

        try {
            $response = $this->client->request('GET', $emailUri);
        } catch (ClientException $e) {
            $this->mailerLogger->error("MAILER_ERROR :: " . $e->getResponse()->getBody()->getContents());
            throw $e;
        }

        if ($response->getStatusCode() !== 200) {
            $this->mailerLogger->error("MAILER_ERROR :: " . $response->getStatusCode());
        }

        $html = $response->getBody()->getContents();

        if ($this->mailSettings['attachEmbeddedImages']) {
            $html = $this->attachHtmlInlineImages($html);
        }

        return $html;
    }

    /**
     * Sends test email using Fluid template to check the configuration
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

    /**
     * Sends test email using a Content Repository template node
     *
     * @param string|array $to
     * @return void
     * @throws InvalidMailerConfigurationException|TransportExceptionInterface
     */
    public function sendTestMailFromContentRepository($to)
    {
        $templateNodeId = $this->mailSettings['templateNodes']['test'] ?? '';
        if (empty($templateNodeId)) {
            throw new \RuntimeException('No test template node configured in FormatD.Mailer.templateNodes.test', 1714142100);
        }

        $this->send(
            'Format D Mailer // Test E-Mail',
            $to,
            null,
            'Hello guys, this is a test e-mail',
            $this->getHtml($this->getNodeById($templateNodeId))
        );
    }
}
