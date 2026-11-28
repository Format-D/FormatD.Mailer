<?php

namespace FormatD\Mailer\Form\Finishers;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Form\Exception\FinisherException;
use Neos\Form\Core\Model\AbstractFinisher;
use Neos\Media\Domain\Model\ResourceBasedInterface;
use Neos\Utility\ObjectAccess;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\HtmlToTextConverter\DefaultHtmlToTextConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mailer\Mailer;
use FormatD\Mailer\Service\DefaultMailerService;

/**
 * Email finisher that sends emails using Content Repository template nodes.
 * Interception is handled at the transport level by DebuggingAspect.
 */
class EmailFinisher extends AbstractFinisher
{

    #[Flow\InjectConfiguration(package: "FormatD.Mailer")]
    protected array $configuration;

    #[Flow\Inject]
    protected DefaultMailerService $mailerService;

    const FORMAT_PLAINTEXT = 'plaintext';
    const FORMAT_HTML = 'html';
    const FORMAT_MULTIPART = 'multipart';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_HTML = 'text/html';

    protected $formatContentTypes = [
        self::FORMAT_HTML => self::CONTENT_TYPE_HTML,
        self::FORMAT_PLAINTEXT => self::CONTENT_TYPE_PLAINTEXT,
    ];

    protected $defaultOptions = array(
        'senderAddress' => '',
        'senderName' => '',
        'recipientAddress' => '',
        'recipientName' => '',
        'format' => self::FORMAT_HTML,
        'attachAllPersistentResources' => false,
        'attachments' => [],
    );

    /**
     * @return void
     */
    public function initializeObject() {
        $this->defaultOptions['senderAddress'] = $this->configuration['defaultFrom']['address'];
        $this->defaultOptions['senderName'] = $this->configuration['defaultFrom']['name'];
        $this->defaultOptions['recipientAddress'] = $this->configuration['defaultTo']['address'];
        $this->defaultOptions['recipientName'] = $this->configuration['defaultTo']['name'];
    }

    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     *
     * @return void
     * @throws FinisherException
     */
    protected function executeInternal()
    {
        if (!class_exists(Mailer::class)) {
            throw new FinisherException('"symfony/mailer" doesn\'t seem to be installed, but is required for the EmailFinisher to work!', 1714142034);
        }

        $formValues = $this->finisherContext->getFormValues();
        $templateNode = $this->parseOption('templateNode');
        $subject = $this->parseOption('subject');
        $senderAddress = $this->parseOption('senderAddress');
        $senderName = $this->parseOption('senderName');
        $recipientAddress = $this->parseOption('recipientAddress');
        $recipientName = $this->parseOption('recipientName');
        $replyToAddress = $this->parseOption('replyToAddress');
        $carbonCopyAddress = $this->parseOption('carbonCopyAddress');
        $blindCarbonCopyAddress = $this->parseOption('blindCarbonCopyAddress');
        $format = $this->parseOption('format');

        if ($templateNode === null) {
            throw new FinisherException('The option "templateNode" must be set for the EmailFinisher.', 1714141943);
        }
        if ($subject === null) {
            throw new FinisherException('The option "subject" must be set for the EmailFinisher.', 1327060320);
        }
        if ($recipientAddress === null) {
            throw new FinisherException('The option "recipientAddress" must be set for the EmailFinisher.', 1327060200);
        }
        if (is_array($recipientAddress) && !empty($recipientName)) {
            throw new FinisherException('The option "recipientName" cannot be used with multiple recipients in the EmailFinisher.', 1483365977);
        }
        if ($senderAddress === null) {
            throw new FinisherException('The option "senderAddress" must be set for the EmailFinisher.', 1327060210);
        }

        $emailHtml = $this->mailerService->getHtml($this->mailerService->getNodeById($templateNode));
        $parsedHtml = $this->replaceMarkerWithFormValues($formValues, $emailHtml);
        [$text, $html] = $this->createBodies($format, $parsedHtml);

        $this->mailerService->send(
            $subject,
            new Address($recipientAddress, $recipientName),
            new Address($senderAddress, $senderName),
            $text,
            $html,
            $replyToAddress ? new Address($replyToAddress) : null,
            $carbonCopyAddress ? new Address($carbonCopyAddress) : null,
            $blindCarbonCopyAddress ? new Address($blindCarbonCopyAddress) : null,
            $this->createAttachments($formValues)
        );
    }

    /**
     * Attachments as the Neos.Form email finisher handles them: with "attachAllPersistentResources", every form value
     * holding a resource (file uploads, asset attachments); with "attachments", the listed resource paths and form
     * elements.
     *
     * @return DataPart[]
     * @throws FinisherException
     */
    protected function createAttachments(array $formValues): array
    {
        $attachments = [];
        if ($this->parseOption('attachAllPersistentResources')) {
            foreach ($formValues as $formValue) {
                if ($formValue instanceof ResourceBasedInterface) {
                    $formValue = $formValue->getResource();
                }
                if ($formValue instanceof PersistentResource) {
                    $attachments[] = new DataPart($formValue->getStream(), $formValue->getFilename(), $formValue->getMediaType());
                }
            }
        }

        $attachmentConfigurations = $this->parseOption('attachments');
        if (is_array($attachmentConfigurations)) {
            foreach ($attachmentConfigurations as $attachmentConfiguration) {
                if (isset($attachmentConfiguration['resource'])) {
                    $attachments[] = new DataPart(new File($attachmentConfiguration['resource']));
                    continue;
                }
                if (!isset($attachmentConfiguration['formElement'])) {
                    throw new FinisherException('The "attachments" options need to specify a "resource" path or a "formElement" containing the resource to attach', 1764331201);
                }
                $resource = ObjectAccess::getPropertyPath($formValues, $attachmentConfiguration['formElement']);
                if (!$resource instanceof PersistentResource) {
                    continue;
                }
                $content = stream_get_contents($resource->getStream());
                if (!is_bool($content)) {
                    $attachments[] = new DataPart($content, $resource->getFilename(), $resource->getMediaType());
                }
            }
        }

        return $attachments;
    }

    /**
     * The template node only renders HTML: the text part of the plaintext and multipart formats is derived from it.
     * Any other format sends HTML only, as the Neos.Form email finisher does.
     *
     * @return array{0: string|null, 1: string|null} text and HTML body
     */
    protected function createBodies(?string $format, string $html): array
    {
        return match ($format) {
            self::FORMAT_PLAINTEXT => [$this->convertHtmlToText($html), null],
            self::FORMAT_MULTIPART => [$this->convertHtmlToText($html), $html],
            default => [null, $html],
        };
    }

    protected function convertHtmlToText(string $html): string
    {
        // the converter only strips tags: entities like &amp; would otherwise end up in the text verbatim
        $text = (new DefaultHtmlToTextConverter())->convert($html, 'utf-8');
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function replaceMarkerWithFormValues($formValues, $emailHtml)
    {
        preg_match_all('#\#\#\#(.*?)\#\#\##', $emailHtml, $matches);

        if (isset($matches[1])) {
            foreach ($matches[1] as $match) {
                $nestedMatch = explode('.', $match);
                $replacement = '';

                if (count($nestedMatch) > 1 && isset($formValues[$nestedMatch[0]][$nestedMatch[1]])) {
                    $replacement = $formValues[$nestedMatch[0]][$nestedMatch[1]];
                } elseif (isset($formValues[$match])) {
                    $replacement = $formValues[$match];
                }

                $emailHtml = str_replace("###{$match}###", $replacement, $emailHtml);
            }
        }

        return $emailHtml;
    }
}
