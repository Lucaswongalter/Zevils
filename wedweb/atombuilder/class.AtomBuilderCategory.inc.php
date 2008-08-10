<?php
require_once 'class.AtomBuilderBase.inc.php';

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
class AtomBuilderCategory extends AtomBuilderBase {
	protected $term;
	protected $scheme;
	protected $label;

	function __construct($term = 'default') {
		parent::__construct();
		$this->setTerm($term);
	} // end constructor

	public function setTerm($string = '') {
		return parent::setVar($string, 'term', 'string');
	} // end function

	public function setScheme($string = '') {
		return parent::setVar($string, 'scheme', 'string');
	} // end function

	public function setLabel($string = '') {
		return parent::setVar($string, 'label', 'string');
	} // end function


	public function getTerm() {
		return parent::getVar('term');
	} // end function

	public function getScheme() {
		return parent::getVar('scheme');
	} // end function

	public function getLabel() {
		return parent::getVar('label');
	} // end function
} // end class
?>