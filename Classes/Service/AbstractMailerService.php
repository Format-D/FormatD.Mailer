<?php
namespace FormatD\Mailer\Service;

/*
 * This file is part of the FormatD.Mailer package.
 */

use Neos\Flow\Annotations as Flow;

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
	 * @var \Neos\Flow\Configuration\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @Flow\Inject
	 * @var \Neos\Flow\ObjectManagement\ObjectManagerInterface
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
	 * @var array
	 */
	protected $defaultFrom = array();

	/**
	 * Message which is processed at the moment
	 *
	 * @var \Neos\SwiftMailer\Message
	 */
	protected $processedMessage;

	/**
	 * Get Configuration
	 */
	public function initializeObject() {
		$this->defaultFrom = array($this->mailSettings['defaultFrom']['address'] => $this->mailSettings['defaultFrom']['name']);
	}

	/**
	 * Creates new message object and stores it as processedMessage
	 *
	 * @return \Neos\SwiftMailer\Message
	 */
	public function createMessage() {
		$message = $this->objectManager->get('Neos\SwiftMailer\Message');
		$message->setFrom($this->defaultFrom);
		$this->processedMessage = $message;
		return $message;
	}

	/**
	 * Creates StandaloneView from templatePath and assigns default variables
	 *
	 * @param $templatePath
	 * @return \Neos\FluidAdaptor\View\StandaloneView
	 */
	public function createStandaloneView($templatePath) {
		$view = $this->objectManager->get('Neos\FluidAdaptor\View\StandaloneView');
		$view->setTemplatePathAndFilename('resource://'.$templatePath);
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
	protected function attachHtmlInlineImages($html) {
		return preg_replace_callback('#(<img [^>]*[ ]?src=")([^"]+)"#', array($this, 'attachHtmlInlineImage'), $html);
	}

	/**
	 * Substitution function called by preg_replace_callback
	 *
	 * @param $match
	 * @return string
	 */
	public function attachHtmlInlineImage($match) {
		$path = $match[2];

		// only use local embed if nothing else can work (legacy mode)
		if (!isset($this->mailSettings['localEmbed']) || $this->mailSettings['localEmbed'] === false) {
				// if in cli we do not know the baseurl so we request the file locally
				if (FLOW_SAPITYPE == 'CLI' && !preg_match('#^http.*#', $path)) {
						$path = FLOW_PATH_WEB . $path;
				}
		} else if ($this->mailSettings['localEmbed']) {
				if (preg_match('#^http.*#', $path) && $this->baseUri) {
						// if we know the baseUri we remove it to be able to convert the path to a local path
						$path = str_replace($this->baseUri, "", $path);
				}
				$path = FLOW_PATH_WEB . $path;
		}

		return $match[1].$this->processedMessage->embed(\Swift_Image::fromPath($path)).'"';
	}

	/**
	 * Sets the mailcontent from a standalone view
	 *
	 * @param \Neos\SwiftMailer\Message $message
	 * @param \Neos\FluidAdaptor\View\StandaloneView $view
	 * @param bool $embedImages
	 */
	protected function setMailContentFromStandaloneView(\Neos\SwiftMailer\Message $message, \Neos\FluidAdaptor\View\StandaloneView $view, $embedImages = false) {

		$subject = trim($view->renderSection('subject'));
		$html = trim($view->renderSection('html'));
		$plain = trim($view->renderSection('plain', [], true));

		if($embedImages) $html = $this->attachHtmlInlineImages($html);

		$message->setSubject($subject);
		if ($html) $message->addPart($html,'text/html','utf-8');
		if ($plain) $message->addPart($plain,'text/plain','utf-8');
	}

	/**
	 * Sends a message
	 *
	 * @param \Neos\SwiftMailer\Message $message
	 */
	protected function sendMail(\Neos\SwiftMailer\Message $message) {
		$message->send();
	}

	/**
	 * Sends test email to check the configuration
	 *
	 * @param string|array $to
	 * @return void
	 */
	public function sendTestMail($to) {

		$mail = $this->createMessage();

		$view = $this->createStandaloneView('FormatD.Mailer/Private/Templates/Notifications/TestMail.html');
		$view->assign('teststring', 'HelloWorld');

		$this->setMailContentFromStandaloneView($mail, $view, true);
		$mail->setTo($to);

		$this->sendMail($mail);
	}
}

?>