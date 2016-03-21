<?php
/**
 * Transaction records
 * Root for recording all Boku transaction data
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

Class Boku_Paymentgateway_Model_Resource_Payment_Transaction extends Mage_Core_Model_Resource_Db_Abstract{

	protected $_isPkAutoIncrement = false;

	/**
	 * Initialize main table and the primary key field name
	 */
	protected function _construct(){
		$this->_init('boku/payment_transaction', 'trx_id');
	}
}