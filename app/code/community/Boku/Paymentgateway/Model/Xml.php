<?php
/**
 * XML object
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_Xml extends SimpleXMLElement
{
	/**
	 * Returns this element and it's children as a condensed array
	 *
	 * @param bool $is_canonical - whether to ignore attributes
	 * @return array|string
	 */
	public function asArray($is_canonical = false){
		return self::condenseArray(self::_asArray($this, $is_canonical));
	}

	/**
	 * Returns the element and it's children as an array
	 * attributes names are prefixed by '@' - slight warning: these could be overwritten by children with the same names
	 *
	 * @param SimpleXMLElement
	 * @param bool $is_canonical - whether to ignore attributes
	 * @return array|string
	 */
	protected static function _asArray(SimpleXMLElement $element, $is_canonical = false){
		$result = array();
		if (!$is_canonical)
			foreach ($element->attributes() as $name=>$value)
				$result['@'.$name] = (string) $value;

		if (self::hasChildren($element)){
			foreach ($element->children() as $name=>$child)
				$result[$name][] = self::_asArray($child, $is_canonical);

		} elseif (empty($result))
			$result = (string) $element;
		elseif (!empty((string) $element))
			$result[0] = (string) $element;
		return $result;
	}

	/**
	 * Does a SimpleXMLElement have any children ?
	 *
	 * @param SimpleXMLElement $element
	 * @return boolean
	 */
	public static function hasChildren(SimpleXMLElement $element){
		if ($children = $element->children())
			foreach ($children as $child)
				return true;
		return false;
	}

	/**
	 * Collapses all single element arrays to their parents
	 *
	 * @param array $data
	 * @return array
	 */
	protected static function condenseArray($data){
		if (!is_array($data) || empty($data)) return $data;
		if (count($data) == 1 && isset($data[0]))
			return self::condenseArray($data[0]);
		foreach($data as &$child)
			$child = self::condenseArray($child);
		return $data;
	}

	/**
	 * Unfortunately SimpleXMLElement can fail badly on invalid xml so here is a validation function
	 * returns false if any errors are encountered the errors are logged to system.log
	 *
	 * @param string $xml
	 * @return boolean
	 */
	public static function isValidXml($xml){
		libxml_use_internal_errors(true);

		$doc = simplexml_load_string($xml);
		if (!$doc){
			$errors = libxml_get_errors();
			$lines = explode("\n", $xml);
			foreach($errors as $error){
				$line = $lines[$error->line - 1];
				$column = $error->column;
				Mage::logErr(__METHOD__.': '.$error->line.'/'.$error->column.' - '.$error->message.' - '.substr($line, 0, $column - 1));
			}
			libxml_clear_errors();
			return false;
		}
		return true;
	}
}
