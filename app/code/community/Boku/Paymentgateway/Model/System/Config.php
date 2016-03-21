<?php
/**
 * System config options
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */
class Boku_Paymentgateway_Model_System_Config
{
	const APP_ROOT = 'boku';

	const ABORT = 0;
	const CHARGE_MIN = 1;
	const CHARGE_MAX = 1;
	const MULTIPLE = 2;
	const ROUND_UP = 1;
	const ROUND_DOWN = 2;

	const NONE = 0;
	const MIN = 1;
	const AVG = 2;
	const MAX = 3;

	const LIVE = 0;
	const TEST = 1;

	const PAYMENT_ACTION_AUTH  = 'Authorization';

	public function getPaymentBelowMinOptions(){
		$helper = Mage::helper(self::APP_ROOT);
		return array(
			self::ABORT			=>$helper->__('Exclude Boku'),
			self::CHARGE_MIN	=>$helper->__('Charge Minimum'),
		);
	}
	public function getPaymentAboveMaxOptions(){
		$helper = Mage::helper(self::APP_ROOT);
		return array(
			self::ABORT			=>$helper->__('Exclude Boku'),
			self::CHARGE_MAX	=>$helper->__('Charge Maximum'),
			self::MULTIPLE		=>$helper->__('Use Multiple Submissions'),
		);
	}
	public function getPaymentUnavailableOptions(){
		$helper = Mage::helper(self::APP_ROOT);
		return array(
			self::ABORT			=>$helper->__('Exclude Boku'),
			self::ROUND_UP		=>$helper->__('Round-up (Exclude Boku if not possible)'),
			self::ROUND_DOWN	=>$helper->__('Round-down (Exclude Boku if not possible)'),
		);
	}
	public function getCommissionOptions(){
		$helper = Mage::helper(self::APP_ROOT);
		return array(
			self::NONE	=>$helper->__('No'),
			self::MIN	=>$helper->__('Minimum'),
			self::AVG	=>$helper->__('Average'),
			self::MAX	=>$helper->__('Maximum'),
		);
	}
	public function getModeOptions(){
		$helper = Mage::helper(self::APP_ROOT);
		return array(
			self::TEST	=>$helper->__('Test'),
			self::LIVE	=>$helper->__('Live'),
		);
	}
}
?>