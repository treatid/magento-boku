<?php
/**
 * Chargeback collection
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_Resource_Payment_Chargeback_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract{

	/**
	 * Initialize collection items factory class
	 */
	protected function _construct(){
		$this->_init('boku/payment_chargeback');
		parent::_construct();
	}
}