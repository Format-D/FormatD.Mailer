<?php
namespace FormatD\Mailer\Service;

use Neos\Flow\Core\Bootstrap;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use FormatD\Mailer\Traits\InterceptionTrait;
use FormatD\Mailer\Service\ContentRepositoryService;
use FormatD\Mailer\Factories\MailerFactory;
use FormatD\Mailer\Factories\MailFactory;
use Neos\Flow\Annotations as Flow;

/**
 * Mail service - can be extended in own packages
 *
 * @Flow\Scope("singleton")
 */
class AbstractMailerService
{

    use InterceptionTrait;

    #[Flow\Inject(name: "FormatD.Mailer:MailerLogger")]
    protected $mailerLogger;

    #[Flow\InjectConfiguration(package: "Neos.Flow", path: "http.baseUri")]
    protected string $baseUri;

    #[Flow\Inject]
    protected ContentRepositoryService $contentRepositoryService;

    #[Flow\Inject]
    protected MailerFactory $mailerFactory;

    #[Flow\Inject]
    protected MailFactory $mailFactory;

    protected MailerInterface $mailer;

    protected Address $defaultFromAddress;

    protected $client;

    public function initializeObject()
    {
        $this->mailer = $this->mailerFactory->createMailer();
        $this->defaultFromAddress = new Address($this->configuration['defaultFrom']['address'], $this->configuration['defaultFrom']['name']);

        $clientOptions = [];
        $clientOptions = $this->setSslVerification($clientOptions);
        $this->client = new Client($clientOptions);
    }

    protected function setSslVerification(array $clientOptions): array
    {
        $flowContext = Bootstrap::getEnvironmentConfigurationSetting('FLOW_CONTEXT');
        $clientOptions['verify'] = str_contains($flowContext, 'Development') ? false : true;

        return $clientOptions;
    }

    protected function send($subject, $to, $from, $text, $html)
    {
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
        $contentRepository = $this->contentRepositoryService->getContentRepository();
        $workspace = $this->contentRepositoryService->getWorkspace($contentRepository);
        $contentGraph = $this->contentRepositoryService->getContentGraph($contentRepository);

        $testEmailNodes = $contentGraph->findNodeAggregateById($workspace->currentContentStreamId, NodeAggregateId::fromString($this->configuration['templateNodes']['test']));

        $mail = $this->mailFactory->createMail(
            'Format D Mailer // Test E-Mail',
            $to,
            $this->defaultFromAddress,
            'Hello guys, this is a test e-mail',
            $this->getHtml($testEmailNodes->getNodes()[0])
        );

        $this->mailer->send($mail);
    }

    protected function getHtml(Node $emailNode)
    {
        $emailUri = $this->contentRepositoryService->getNodeUri($emailNode);

        try {
            $response = $this->client->request('GET', $emailUri . 'sdkf');
        } catch (ClientException $e) {
            $this->mailerLogger->error("MAILER_ERROR :: " . $e->getResponse()->getBody()->getContents());
        }

        if ($response->getStatusCode() !== 200) {
            $this->mailerLogger->error("MAILER_ERROR :: " . $response->getStatusCode());
        }

        $newsletterContent = $response->getBody()->getContents();

        # @todo add handling for marker in template (?)
        #$newsletterContent = preg_replace_callback('#\{[a-zA-Z]+\}#', function ($match) use ($recipientData) {
        #    return $recipientData->replaceMarker($match[0]);
        #}, $newsletterContent);

        # attach images
        if ($this->configuration['attachEmbeddedImages']) {
            $newsletterContent = $this->attachHtmlInlineImages($newsletterContent);
        }

        # @todo add attachment handling

        return $newsletterContent;
    }

    protected function attachHtmlInlineImages($html)
    {
        return preg_replace_callback('#(<img [^>]*[ ]?src=")([^"]+)("[^>]*>)#', array($this, 'attachHtmlInlineImage'), $html);
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
        if (preg_match('#data-fdmailer-embed="disable"#', $completeMatch)) {
            return $completeMatch;
        }

        // only use local embed if nothing else can work (legacy mode)
        if (!isset($this->configuration['localEmbed']) || $this->configuration['localEmbed'] === false) {
            // if in cli we do not know the baseurl so we request the file locally
            if (FLOW_SAPITYPE == 'CLI' && !preg_match('#^http.*#', $path)) {
                $path = FLOW_PATH_WEB . $path;
            }
        } else if ($this->configuration['localEmbed']) {
            if (preg_match('#^http.*#', $path) && $this->baseUri) {
                // if we know the baseUri we remove it to be able to convert the path to a local path
                $path = str_replace($this->baseUri, "", $path, $replaceCount);
            }
            if (!preg_match('#^http.*#', $path)) {
                // if path is now relative to document root we prepend local path
                $path = FLOW_PATH_WEB . $path;
            }
        }

        if ($this->configuration['attachEmbeddedImages']) {
            #$this->processedMessage->attach(\Swift_Attachment::fromPath(urldecode($path)));
            # @todo attach resource to symfony mail
        }

        #return $imgTagStart . $this->processedMessage->embed(\Swift_Image::fromPath(urldecode($path))) . '"' . $imgTagEnd;
        return $imgTagStart . ' ' . $imgTagEnd;
    }
}
