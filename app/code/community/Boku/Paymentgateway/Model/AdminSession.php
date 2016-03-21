<?php
/**
 * Boku transaction admin session data store
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_AdminSession extends Mage_Core_Model_Session_Abstract
{
	const APP_ROOT = 'boku';

	public function __construct(){
		$this->init(self::APP_ROOT.'admin');
	}
}
