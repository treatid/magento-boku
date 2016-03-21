<?php
/**
 * Event records
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

Class Boku_Paymentgateway_Model_Resource_Payment_Event extends Mage_Core_Model_Resource_Db_Abstract{

	/**
	 * Initialize main table and the primary key field name
	 */
	protected function _construct(){
		$this->_init('boku/payment_event', 'id');
	}
}