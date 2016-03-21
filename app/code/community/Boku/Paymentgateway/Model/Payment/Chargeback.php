<?php
/**
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

Class Boku_Paymentgateway_Model_Payment_Chargeback extends Boku_Paymentgateway_Model_Mapped_Abstract{

	/**
	 * Initialize resource model
	 */
	protected function _construct(){
		$this->_init(self::APP_ROOT.'/payment_chargeback');
		return parent::_construct();
	}

	/**
	 * Attempts to creates Mage_Sales_Model_Order_Payment
	 * If successful or unneccessary then the handled flag is set to true
	 *
	 * @return boolean
	 */
	public function complete(){
		if ($this->getHandled()) return true;
		$helper = Mage::helper(self::APP_ROOT);
try{
		$currency = $this->getCurrency();
		$refund = $helper->createRefund(array(
			'trx_id'=>$this->getTrxId(),
			'amount'=>$helper->getFloatPrice($this->getChargebackamount(), $currency),
			'currency'=>$currency,
		));
		if (empty($refund)) throw new Exception('Failed to create refund.');

		$this->setHandled(true)->save();
}catch(Exception $e){$helper->logErr(__METHOD__.' - '.$e->getMessage()); return false;}
		return true;
	}

	/**
	 * runs complete on any uncompleted records
	 *
	 * @param string $trx_id - if null then looks at all transactions
	 */
	public function completeOutstanding($trx_id = null){
		$c = $this->getCollection()->addFieldToFilter('handled', 0);
		if (!is_null($trx_id)) $c->addFieldToFilter('trx_id', $trx_id);
		foreach($c->load() as $model) $model->complete();
	}

	/**
	 * Creates a new chargeback record.
	 * Fails if one already exists for the particular trx-id (only one allowed)
	 *
	 * @param array &$idata
	 * @return Boku_Paymentgateway_Model_Payment_Chargeback
	 */
	public static function create(&$idata){
		$model = new self();
		$data = $model->map($idata);
		$trx_id = $data['trx_id'];
		$id = $model->load($trx_id, 'trx_id')->getId();
		if (!empty($id))
			throw new Exception(__METHOD__.' - A chargeback is already present for transaction '.$trx_id.' (only one allowed).');
		$model->setData($data)->save()->complete();
		return $model;
	}
}
