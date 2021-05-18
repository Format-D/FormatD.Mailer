<?php
namespace FormatD\Mailer\Traits;

/*                                                                        *
 * This script belongs to the Flow package "FormatD.Mailer".              *
 *                                                                        */

use Neos\Flow\Annotations as Flow;


trait InterceptionTrait {

	/**
	 * @var boolean
	 */
	protected $intercepted = false;

	/**
	 * @return bool
	 */
	public function isIntercepted(): bool
	{
		return $this->intercepted;
	}

	/**
	 * @param bool $intercepted
	 */
	public function setIntercepted(bool $intercepted): void
	{
		$this->intercepted = $intercepted;
	}

}

?>
