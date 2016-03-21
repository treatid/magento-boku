<?php
/**
 * Boku table creation
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright 	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'boku/transactions'
 */
$transactions_table = 'boku/payment_transaction';
$table_name = $transactions_table;
$table = $installer->getConnection()
	->newTable($installer->getTable($table_name))
	->addColumn('trx_id', Varien_Db_Ddl_Table::TYPE_CHAR, 50, array(
		'nullable'	=>false,
		'primary'	=>true,
	))
	->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array('unsigned'=>true,))
	->addColumn('quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('unsigned'=>true,))
	->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('unsigned'=>true,))
	->addColumn('test', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array('nullable'=>false,))
	->addColumn('country', Varien_Db_Ddl_Table::TYPE_CHAR, 2, array('nullable'=>false,))
	->addColumn('currency', Varien_Db_Ddl_Table::TYPE_CHAR, 3, array('nullable'=>false,))
	->addColumn('amount', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('nullable'=>false,))
	->addColumn('timestamp', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array('nullable'=>false,))
	->addColumn('reference_currency', Varien_Db_Ddl_Table::TYPE_CHAR, 3)
	->addColumn('exchange', Varien_Db_Ddl_Table::TYPE_FLOAT)

	->addColumn('paid', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('paid_inc_salestax', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('paid_ex_salestax', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('receivable_gross', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('receivable_net', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('encoded_mobile', Varien_Db_Ddl_Table::TYPE_TEXT, 20)
	->addColumn('network', Varien_Db_Ddl_Table::TYPE_TEXT, 10)
	->addColumn('operator_tax_treatment', Varien_Db_Ddl_Table::TYPE_TEXT, 20)
	->addColumn('optin_enrolled', Varien_Db_Ddl_Table::TYPE_BOOLEAN)
	->addColumn('optin_used', Varien_Db_Ddl_Table::TYPE_BOOLEAN)

	->addColumn('result_code', Varien_Db_Ddl_Table::TYPE_SMALLINT, null)
	->addColumn('result_msg', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
	->addColumn('result_timestamp', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null)
	->addColumn('handled', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('cancelled', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array('default'=>0, 'nullable'=>false,))

	->addIndex($installer->getIdxName(
		$table_name, array('trx_id'), Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
		array('trx_id'), array('type'=>Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
	->addIndex($installer->getIdxName($table_name, array('quote_id')), array('quote_id'))
	->addIndex($installer->getIdxName($table_name, array('order_id')), array('order_id'))
	->setComment('Boku Transactions');
$installer->getConnection()->createTable($table);

/**
 * Create table 'boku/callbacks'
 */
$table_name = 'boku/payment_callback';
$table = $installer->getConnection()
	->newTable($installer->getTable($table_name))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
		'identity'	=>true,
		'unsigned'	=>true,
		'nullable'	=>false,
		'primary'	=>true,
	))
	->addColumn('trx_id', Varien_Db_Ddl_Table::TYPE_CHAR, 50)
	->addColumn('action', Varien_Db_Ddl_Table::TYPE_TEXT, 16, array('nullable'=>false,))
	->addColumn('status_code', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('nullable'=>false,))
	->addColumn('status', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
	->addColumn('timestamp', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array('nullable'=>false,))
	->addColumn('notes', Varien_Db_Ddl_Table::TYPE_TEXT)

	->addIndex($installer->getIdxName($table_name, array('trx_id')), array('trx_id'))
	->addIndex($installer->getIdxName($table_name, array('action')), array('action'))
	->addForeignKey(
		$installer->getFkName($table_name, 'trx_id', $transactions_table,'trx_id'),
		'trx_id', $installer->getTable($transactions_table), 'trx_id',
		Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
	->setComment('Boku Callbacks');
$installer->getConnection()->createTable($table);

/**
 * Create table 'boku/events'
 */
$table_name = 'boku/payment_event';
$table = $installer->getConnection()
	->newTable($installer->getTable($table_name))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
		'identity'	=>true,
		'unsigned'	=>true,
		'nullable'	=>false,
		'primary'	=>true,
	))
	->addColumn('trx_id', Varien_Db_Ddl_Table::TYPE_CHAR, 50, array('nullable'=>false,))
	->addColumn('currency', Varien_Db_Ddl_Table::TYPE_CHAR, 3, array('nullable'=>false,))
	->addColumn('paid', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('receivable_gross', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('message_cost', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))

	->addColumn('event_code', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('msg', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
	->addColumn('handled', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array('default'=>0, 'nullable'=>false,))

	->addIndex($installer->getIdxName($table_name, array('trx_id')), array('trx_id'))
	->addForeignKey(
		$installer->getFkName($table_name, 'trx_id', $transactions_table,'trx_id'),
		'trx_id', $installer->getTable($transactions_table), 'trx_id',
		Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
	->setComment('Boku Events');
$installer->getConnection()->createTable($table);

/**
 * Create table 'boku/chargebacks'
 */
$table_name = 'boku/payment_chargeback';
$table = $installer->getConnection()
	->newTable($installer->getTable($table_name))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
		'identity'	=>true,
		'unsigned'	=>true,
		'nullable'	=>false,
		'primary'	=>true,
	))
	->addColumn('trx_id', Varien_Db_Ddl_Table::TYPE_CHAR, 50, array('nullable'=>false,))
	->addColumn('amount', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('chargebackamount', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('reason_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array('default'=>0, 'nullable'=>false,))
	->addColumn('reason', Varien_Db_Ddl_Table::TYPE_TEXT)
	->addColumn('refundsource', Varien_Db_Ddl_Table::TYPE_TEXT, 16)
	->addColumn('handled', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array('default'=>0, 'nullable'=>false,))

	->addIndex($installer->getIdxName($table_name, array('trx_id')), array('trx_id'))
	->addForeignKey(
		$installer->getFkName($table_name, 'trx_id', $transactions_table,'trx_id'),
		'trx_id', $installer->getTable($transactions_table), 'trx_id',
		Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
	->setComment('Boku Chargebacks');
$installer->getConnection()->createTable($table);

$installer->endSetup();
