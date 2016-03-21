<?php
/**
 * Displayed for Boku payment option
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Block_Payment_Standard_Form extends Mage_Payment_Block_Form
{
	protected function _construct(){
		parent::_construct();
		$this->setTemplate('boku/paymentgateway/standard/form.phtml');
	}
}
