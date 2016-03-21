<?php
/**
 * Boku standard payment Form Block
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Block_Payment_Standard_Prepare extends Mage_Core_Block_Template
{
	/**
	 * Returns the Boku gateway url
	 * This is set in Boku_Paymentgateway_Model_Payment_Standard
	 *
	 * @return string
	 */
	protected function getBuyUrl(){
		return Mage::helper('boku')->getSession()->getData('buy-url');
	}
}
