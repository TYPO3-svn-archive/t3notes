<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Dimitri Koenig (dk@cabag.ch)
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
* Adds "notes://" to external url jump
 *
 * @author	Dimitri Koenig <dk@cabag.ch>
 */

class ux_t3lib_pageSelect extends t3lib_pageSelect {
	var $urltypes = Array('','http://','ftp://','mailto:','https://','notes://');

	/**
	 * Returns the URL type for the input page row IF the doktype is 3 and not disabled.
	 *
	 * @param	array		The page row to return URL type for
	 * @param	boolean		A flag to simply disable any output from here.
	 * @return	string		The URL type from $this->urltypes array. False if not found or disabled.
	 * @see tslib_fe::setExternalJumpUrl()
	 */
	function getExtURL($pagerow, $disable = 0) {
		if ($pagerow['doktype'] == t3lib_pageSelect::DOKTYPE_LINK && !$disable) {
			$redirectTo = $this->urltypes[$pagerow['urltype']] . $pagerow['url'];

				// If relative path, prefix Site URL:
			$uI = parse_url($redirectTo);
			if (!$uI['scheme'] && substr($redirectTo, 0, 1) != '/' && $pagerow['urltype'] != 5) { // relative path assumed now...
				$redirectTo = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $redirectTo;
			}
			if($pagerow['urltype'] == 5 && $pagerow['uid'] == $GLOBALS['TSFE']->id) {
                                $GLOBALS['TSFE']->additionalHeaderData['notesredirect'] = '<meta http-equiv="refresh" content="0; url=' . $redirectTo . '">';
                        } else {
                                return $redirectTo;
                        }
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/class.ux_t3lib_page.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/class.ux_t3lib_page.php']);
}

?>
