<?php
/**
 * Record of Boku event callbacks created by Boku_Paymentgateway_ApiController
 * 
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

Class Boku_Paymentgateway_Model_Payment_Event extends Boku_Paymentgateway_Model_Mapped_Abstract{

	const PSMS_MT_SUB = 1;
	const PSMS_MT_DEL = 2;
	const PSMS_MO_REC = 3;
	const PSMS_MO_NON_REC = 4;

	/**
	 * Initialize resource model
	 */
	protected function _construct(){
		$this->_init(self::APP_ROOT.'/payment_event');
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
		switch ($this->getEventCode()){
			case self::PSMS_MT_DEL:
			case self::PSMS_MO_REC:
				$currency = $this->getCurrency();
				$payment = $helper->createPayment(array(
					'trx_id'=>$this->getTrxId(),
					'amount'=>$helper->getFloatPrice($this->getMessageCost(), $currency),
					'currency'=>$currency,
					'total'=>$helper->getFloatPrice($this->getPaid(), $currency),
				));
				if (empty($payment)) throw new Exception('Failed to create payment.');
		}
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
		$helper = Mage::helper(self::APP_ROOT);
		$c = $this->getCollection()->addFieldToFilter('handled', 0);
		if (!is_null($trx_id)) $c->addFieldToFilter('trx_id', $trx_id);
		$failed = 0;
		foreach ($c->load() as $model){
			$transaction = $helper->getTransaction($model->getTrxId());
			if (empty($transaction)) {$failed++; continue;}
			$helper->setStore($transaction->getStoreId());
			if (!$model->complete()) $failed++;
		}
		if ($failed) $helper->log(__METHOD__.': '.$failed.' failed.');
	}

	/**
	 * Creates a new event record
	 * WARNING we should check whether this event has already been logged
	 *
	 * @param array $data
	 * @return Boku_Paymentgateway_Model_Payment_Event
	 */
	public static function create(&$idata){
		$model = new self();
		$data = $model->map($idata);
		if (isset($data['reference_currency']) && isset($data['paid']) && isset($data['reference_paid']))
			try{$data['exchange'] = ((float) $data['paid']) / $data['reference_paid'];}catch(Exception $e){}
		$model->setData($data)->save()->complete();
		return $model;
	}
}