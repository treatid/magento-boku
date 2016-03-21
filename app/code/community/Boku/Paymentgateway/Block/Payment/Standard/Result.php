<?php
/**
 * Boku standard payment response Form Block
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Block_Payment_Standard_Result extends Mage_Core_Block_Template
{
	const APP_ROOT = 'boku';

	private $_trx_id = null;
	private $_transaction = null;
	private $_order = null;

	protected function _construct(){
		$data = Mage::app()->getRequest()->getParams();
		if (isset($data['trx-id']))
			$this->_trx_id = $data['trx-id'];
		parent::_construct();
	}

	protected function getTransaction(){
		if (empty($this->_transaction) && !empty($this->_trx_id))
			$this->_transaction = Mage::helper(self::APP_ROOT)->getTransaction($this->_trx_id);
		return $this->_transaction;
	}
	protected function getOrder(){
		if (empty($this->_order) && !empty($this->_trx_id)){
			$transaction = $this->getTransaction();
			if (!empty($transaction))
				$this->_order = $transaction->getOrder();
		}
		return $this->_order;
	}
}
