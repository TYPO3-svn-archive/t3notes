<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Dimitri König <dk@cabag.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * 'Check Lotus Notes Links' for the 'cag_linkchecker' extension.
 *
 * @author	Dimitri König <dk@cabag.ch>
 */

class tx_caglinkchecker_checknoteslinks {
	function tx_caglinkchecker_checknoteslinks() {
	}

	function loadLLFile() {
		global $LANG;
		if(is_object($LANG)) {
			$LANG->includeLLFile('EXT:t3notes/locallang.xml');
		}
	}

	function checkLink($str, $reference) {
		return 1;
	}

	function fetchType($value, $type) {
		if (strpos($value['tokenValue'], 'notes://') !== FALSE) {
			$type = "lotusnotes";
		}

		return $type;
	}

}

?>
