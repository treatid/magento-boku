<?php
/**
 * Boku model for any cron bits
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_Cron
{
	const APP_ROOT = 'boku';

	public function run(){
		Mage::helper(self::APP_ROOT)->completeOutstanding();
	}
}