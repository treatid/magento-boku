<?php
/**
 * Simple extension of core model abtract to accommodate field mapping
 * $_field_map is an array of from=>to mappings.
 * 
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

abstract class Boku_Paymentgateway_Model_Mapped_Abstract extends Mage_Core_Model_Abstract
{
	const APP_ROOT = 'boku';

	protected $_field_map = array();

	protected function _mapField($field){
		if (!empty($field) && array_key_exists($field = str_replace('-', '_', $field), $this->_field_map))
			$field = $this->_field_map[$field];
		return $field;
	}
	/**
	 * Warning: if a field is mapped to another field then the other with be replaced
	 */
	protected function &_map(&$data){
		if (is_array($data)){
			foreach ($data as $field=>&$value)
				if (($mapped_field = $this->_mapField($field)) != $field)
					$data[$mapped_field] = $value;
		}else
			$data = $this->_mapField($data);
		return $data;
	}
	public function map($data){return $this->_map($data);}

	public function load($id, $field=null){return parent::load($id, $this->_map($field));}
	public function addData(array $arr){return parent::addData($this->_map($arr));}
	public function setData($key, $value=null){return parent::setData($this->_map($key), $value);}
	public function unsetData($key=null){return parent::unsetData($this->_map($key));}
	public function unsetOldData($key=null){return parent::unsetOldData($this->_map($key));}
	public function getData($key='', $index=null){return parent::getData($this->_map($key), $index);}
	public function setDataUsingMethod($key, $args=array()){return parent::setDataUsingMethod($this->_map($key), $args=array());}
	public function getDataUsingMethod($key, $args=null){return parent::getDataUsingMethod($this->_map($key), $args);}
	public function getDataSetDefault($key, $default){return parent::getDataSetDefault($this->_map($key), $default);}
	public function hasData($key=''){return parent::hasData($this->_map($key));}

/*	public function toArray(array $arrAttributes = array()){return parent::toArray($arrAttributes);}
	public function toXml(array $arrAttributes = array(), $rootName = 'item', $addOpenTag=false, $addCdata=true){return parent::toXml($arrAttributes, $rootName, $addOpenTag, $addCdata);}
*/
	public function __get($var){return parent::__get($this->_map($var));}
	public function __set($var, $value){parent::__set($this->_map($var), $value);}
	public function getOrigData($key=null){return parent::getOrigData($this->_map($key));}
	public function setOrigData($key=null, $data=null){return parent::setOrigData($this->_map($key), $data);}
	public function isDirty($field=null){return parent::isDirty($this->_map($field));}
	public function flagDirty($field, $flag=true){return parent::flagDirty($this->_map($field), $flag);}
}
