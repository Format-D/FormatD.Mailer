<?php
namespace FormatD\Mailer\Form\FormElements;

use Neos\Form\Core\Model\AbstractFormElement;
use Neos\Form\Core\Runtime\FormRuntime;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Repository\AssetRepository;

/**
 * An element to select an asset from media as email attachment
 */
class AssetAttachment extends AbstractFormElement
{

    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * @param FormRuntime $formRuntime
     * @param mixed $elementValue
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onSubmit(FormRuntime $formRuntime, &$elementValue)
    {
        if (!$elementValue) {
            $elementValue = $_POST['asset-attachment'];
        }

        /** @var Asset $asset */
        $asset = $this->assetRepository->findByIdentifier($elementValue);
        $elementValue = $asset->getResource();
    }
}
