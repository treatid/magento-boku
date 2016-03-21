<?php
/**
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

Class Boku_Paymentgateway_Model_Payment_Callback extends Boku_Paymentgateway_Model_Mapped_Abstract{

	/**
	 * Initialize resource model
	 */
	protected function _construct(){
		$this->_init(self::APP_ROOT.'/payment_callback');
		return parent::_construct();
	}

}