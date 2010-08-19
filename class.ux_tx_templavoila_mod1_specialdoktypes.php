<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Dimitri Koenig (dk@cabag.ch)
*  All rights reserved
*
*  script is part of the TYPO3 project. The TYPO3 project is
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
 * Adds "notes://" to the jump link url
 *
 * @author     Dimitri Koenig <dk@cabag.ch>
 */

class ux_tx_templavoila_mod1_specialdoktypes extends tx_templavoila_mod1_specialdoktypes {
	/**
	 * Displays the edit page screen if the currently selected page is of the doktype "External URL"
	 *
	 * @param	array		$pageRecord: The current page record
	 * @return	mixed		HTML output from this submodule or FALSE if this submodule doesn't feel responsible
	 * @access	public
	 */
	function renderDoktype_3($pageRecord)    {
		global $LANG, $BE_USER, $TYPO3_CONF_VARS;

			// Prepare the record icon including a content sensitive menu link wrapped around it:
		$pageTitle = htmlspecialchars(t3lib_div::fixed_lgd_cs(t3lib_BEfunc::getRecordTitle('pages', $pageRecord), 50));
		$recordIcon = $recordIcon = '<img'.t3lib_iconWorks::skinImg($this->doc->backPath, t3lib_iconWorks::getIcon('pages', $pageRecord), '').' style="text-align: center; vertical-align: middle;" width="18" height="16" border="0" title="'.$pageTitle.'" alt="" />';
		$editButton = $this->pObj->link_edit('<img'.t3lib_iconWorks::skinImg($this->doc->backPath,'gfx/edit2.gif','').' title="'.htmlspecialchars($LANG->sL('LLL:EXT:lang/locallang_mod_web_list.xml:editPage')).'" alt="" style="text-align: center; vertical-align: middle; border:0;" />', 'pages', $pageRecord['uid']);

		switch ($pageRecord['urltype']) {
			case 2:
				$url = 'ftp://' . $pageRecord['url'];
			break;
			case 3:
				$url = 'mailto:' . $pageRecord['url'];
			break;
			case 4:
				$url = 'https://' . $pageRecord['url'];
			break;
			case 5:
				$url = 'notes://' . $pageRecord['url'];
				break;
			default:
				// Check if URI scheme already present. We support only Internet-specific notation, others are not relevant for us (see http://www.ietf.org/rfc/rfc3986.txt for details)
				if (preg_match('/^[a-z]+[a-z0-9\+\.\-]*:\/\//i', $pageRecord['url'])) {
					// Do not add any other scheme
					break;
				}
				// fall through
			case 1:
				$url = 'http://' . $pageRecord['url'];
			break;
		}
		$content =
			$this->doc->icons(1).
			$LANG->getLL ('cannotedit_externalurl_'.$pageRecord['urltype'],'',1).
			' <br /><br /><strong><a href="'.$url.'" target="_new">'.htmlspecialchars(sprintf($LANG->getLL ('jumptoexternalurl'), $url)).'</a></strong>'
		;
		return $content;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/class.ux_tx_templavoila_mod1_specialdoktypes.php'])    {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/class.ux_tx_templavoila_mod1_specialdoktypes.php']);
}

?>