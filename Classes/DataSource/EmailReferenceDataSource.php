<?php
namespace FormatD\Mailer\DataSource;

use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;

class EmailReferenceDataSource extends AbstractDataSource
{

	/**
	 * @var string
	 */
	protected static $identifier = 'formatd-mailer-email-reference';

	/**
	 * @var string
	 */
	protected static $icon = 'icon-envelope';

	/**
	 * @param Node|null $node
	 * @param array $arguments
	 * @return array|mixed
	 * @throws \Neos\Eel\Exception
	 */
	public function getData(Node $node = null, array $arguments = [])
	{
		$q = new FlowQuery([$node]);
		$emailNodes = $q
			->parents('[instanceof FormatD.DesignSystem:Site]')
			->find('[instanceof FormatD.Mailer:Document.Email]')
			->get();

		$data = [];
		foreach ($emailNodes as $emailNode) {
			$data[] = [
				'label' => $emailNode->getLabel(),
				'value' => $emailNode->aggregateId,
				'icon' => static::$icon
			];
		}

		return $data;
	}
}
