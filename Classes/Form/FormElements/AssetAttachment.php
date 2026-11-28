<?php
namespace FormatD\Mailer\Form\FormElements;

use Neos\Form\Core\Model\AbstractFormElement;
use Neos\Form\Core\Runtime\FormRuntime;
use Neos\Media\Domain\Model\AssetInterface;

/**
 * An element to select an asset from media as email attachment
 *
 * The attached file is the asset the editor configured on the element. The submitted value is ignored, so a visitor
 * cannot swap in another asset.
 */
class AssetAttachment extends AbstractFormElement
{
    /**
     * @param FormRuntime $formRuntime
     * @param mixed $elementValue
     */
    public function onSubmit(FormRuntime $formRuntime, &$elementValue)
    {
        $asset = $this->properties['asset'] ?? null;
        $elementValue = $asset instanceof AssetInterface ? $asset->getResource() : null;
    }
}
