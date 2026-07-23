<?php

namespace FormatD\Mailer\Transport;

use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Transport\TransportInterface;

abstract class AbstractTransport implements TransportInterface
{
	#[Flow\InjectConfiguration(package: 'FormatD.Mailer', type: 'Settings')]
	protected array $settings;

	const string DEFAULT_INTERCEPT_DATA_HEADER_NAME = 'X-Intercept-Metadata';
}
