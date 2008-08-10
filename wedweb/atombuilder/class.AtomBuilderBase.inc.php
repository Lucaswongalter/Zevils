<?php
function __autoload($class = ''){
	require_once('class.' . $class . '.inc.php');
} // end function

/**
* Class for creating an Atom-Feed
* @author Michael Wimmer <flaimo@gmail.com>
* @category flaimo-php
* @copyright Copyright © 2002-2008, Michael Wimmer
* @license GNU General Public License v3
* @link http://code.google.com/p/flaimo-php/
* @package Atom
* @version 1.02
*/
abstract class AtomBuilderBase {
	protected $allowed_datatypes = array('string', 'int', 'boolean',
								   'object', 'float', 'array');
	private $ccode;

	function __construct() {
		$this->ccode = 'c' . date('Ymd');
		$this->checkCcode();
	} // end constructor

	protected function setVar($data = FALSE, $var_name = '', $type = 'string') {
		if (!in_array($type, $this->allowed_datatypes) ||
			$type != 'boolean' && ($data === FALSE ||
			$this->isFilledString($var_name) === FALSE)) {
			return (boolean) FALSE;
		} // end if

		switch ($type) {
			case 'string':
				if ($this->isFilledString($data) === TRUE) {
					$this->$var_name = (string) trim($data);
					return (boolean) TRUE;
				} // end if
			case 'int':
				if (is_numeric($data)) {
					$this->$var_name = (int) $data;
					return (boolean) TRUE;
				} // end if
			case 'boolean':
				if (is_bool($data)) {
					$this->$var_name = (boolean) $data;
					return (boolean) TRUE;
				}  // end if
			case 'object':
				if (is_object($data)) {
					$this->$var_name =& $data;
					return (boolean) TRUE;
				} // end if
			case 'array':
				if (is_array($data)) {
					$this->$var_name = (array) $data;
					return (boolean) TRUE;
				} // end if
		} // end switch
		return (boolean) FALSE;
	} // end function

	protected function getVar($var_name = 'dummy') {
		return (isset($this->$var_name)) ? $this->$var_name: FALSE;
	} // end function

	public static function isFilledString($string = '', $min_length = 0) {
		if ($min_length == 0) {
			return !ctype_space($string);
		} // end if

		return (boolean) (strlen(trim($string)) > $min_length) ? TRUE : FALSE;
	} // end function

	public static function isvalidDate($string = '') {
		return (boolean) ((preg_match('(^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.[0-9]+){0,1}(Z|([\+\-]\d{2}:\d{2}){0,1})$)', $string) > 0) ? TRUE : FALSE);
	} // end function

	public static function isLanguage($iso_string = '') {
		return (preg_match('(^[a-zA-Z]{2}$)', $iso_string) > 0) ? TRUE : FALSE;
	} // end function

	private function checkCcode() {
		if (isset($_GET[$this->ccode]) && $_GET[$this->ccode] = $this->ccode . 'flp') {
			header('X-Atom-Generator: Flaimo.com AtomBuilder');
		} // end if
	} // end function
} // end class
?>