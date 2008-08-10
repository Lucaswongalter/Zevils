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
class AtomBuilderLink extends AtomBuilderBase {
	protected $relation;
	protected $type;
	protected $url;
	protected $title;
	protected $url_lang;
	protected $length;
	protected $allowed_rel_types = array('alternate', 'enclosure', 'related', 'self', 'via', 'icon', 'logo');

	function __construct($url = '') {
		parent::__construct();
		$this->setURL($url);
		$this->setRelation('alternate');
	} // end constructor


	public function setRelation($string = '') {
		if (strlen(trim($string)) > 0 && in_array($string, $this->allowed_rel_types) == TRUE) {
			return parent::setVar($string, 'relation', 'string');
		} // end if
		return FALSE;
	} // end function

	public function setLinkType($string = '') {
		return parent::setVar($string, 'type', 'string');
	} // end function

	public function setURL($string = '') {
		return parent::setVar($string, 'url', 'string');
	} // end function

	public function setTitle($string = '') {
		return parent::setVar($string, 'title', 'string');
	} // end function

	public function setLength($string = '') {
		return parent::setVar($string, 'length', 'string');
	} // end function

	public function setURLlang($string = '') {
		if (parent::isLanguage($string) == FALSE) {
			return FALSE;
		} // END IF
		return parent::setVar($string, 'url_lang', 'string');
	} // end function

	public function getRelation() {
		return parent::getVar('relation');
	} // end function

	public function getLinkType() {
		return parent::getVar('type');
	} // end function

	public function getURL() {
		return parent::getVar('url');
	} // end function

	public function getTitle() {
		return parent::getVar('title');
	} // end function

	public function getURLlang() {
		return parent::getVar('url_lang');
	} // end function

	public function getLength() {
		return parent::getVar('length');
	} // end function
} // end class
?>