<?php
/**
 * Boku table updates
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright 	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */
$installer = $this;
$installer->startSetup();
$con = $installer->getConnection();

$table_name = 'boku/payment_callback';
$table = $installer->getTable($table_name);
$con->modifyColumn($table, 'trx_id', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_TEXT, 'length'=>50,));

$table_name = 'boku/payment_event';
$table = $installer->getTable($table_name);
$con->addColumn($table, 'paid_inc_salestax', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_INTEGER, 'default'=>0, 'nullable'=>false, 'comment'=>'paid_inc_salestax'));
$con->addColumn($table, 'paid_ex_salestax', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_INTEGER, 'default'=>0, 'nullable'=>false, 'comment'=>'paid_ex_salestax'));
$con->addColumn($table, 'receivable_net', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_INTEGER, 'default'=>0, 'nullable'=>false, 'comment'=>'receivable_net'));
$con->addColumn($table, 'reference_currency', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_TEXT, 'length'=>3, 'comment'=>'reference_currency'));
$con->addColumn($table, 'exchange', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_FLOAT, 'comment'=>'exchange'));

$table_name = 'boku/payment_transaction';
$table = $installer->getTable($table_name);
$con->addColumn($table, 'exchange', array(
	'type'=>Varien_Db_Ddl_Table::TYPE_FLOAT, 'comment'=>'exchange'));
$con->addForeignKey(
	$installer->getFkName($table_name, 'quote_id', 'sales/quote','entity_id'),
	$table, 'quote_id', $installer->getTable('sales/quote'), 'entity_id',
	Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);
$con->addForeignKey(
	$installer->getFkName($table_name, 'order_id', 'sales/order','entity_id'),
	$table, 'order_id', $installer->getTable('sales/order'), 'entity_id',
	Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);

$installer->endSetup();
