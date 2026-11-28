<?php
namespace FormatD\Mailer\DataSource;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Neos\Service\DataSource\AbstractDataSource;

/**
 * Lists the email documents of the current site, for picking a template node in the inspector
 */
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
     * @param NodeInterface|null $node
     * @param array $arguments
     * @return array
     * @throws \Neos\Eel\Exception
     */
    public function getData(?NodeInterface $node = null, array $arguments = [])
    {
        $q = new FlowQuery([$node]);
        $emailNodes = $q
            ->parents('[instanceof Neos.Neos:Site]')
            ->find('[instanceof FormatD.Mailer:Document.Email]')
            ->get();

        $data = [];
        foreach ($emailNodes as $emailNode) {
            $data[] = [
                'label' => $emailNode->getLabel(),
                'value' => $emailNode->getIdentifier(),
                'icon' => static::$icon
            ];
        }

        return $data;
    }
}
