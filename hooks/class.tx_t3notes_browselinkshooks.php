<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Dimitri Koenig <dk@cabag.ch>
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
 * hook to adjust add a tab to linkswizard for notes links
 *
 * @author	Dimitri Koenig <dk@cabag.ch>
 * @package TYPO3
 * @subpackage t3notes
 */

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
// include defined interface for hook
require_once (PATH_t3lib.'interfaces/interface.t3lib_browselinkshook.php');

class tx_t3notes_browselinkshooks implements t3lib_browseLinksHook {
    protected $pObj;

    function init ($parentObject, $additionalParameters) {
        $this->pObj = $parentObject;
    	if ($this->isRTE()) {
				$this->pObj->anchorTypes[] = 't3notes_tab'; //for 4.3
		}
    }

    function addAllowedItems ($currentlyAllowedItems) {
        $currentlyAllowedItems[] = 't3notes_tab';

        return $currentlyAllowedItems;
    }


    function modifyMenuDefinition ($menuDefinition) {
		if ($GLOBALS['BE_USER']->getTSConfigVal('options.disableNotesTab')) {
			return $menuDefinition;
		}
        $key = 't3notes_tab';

        $menuDefinition[$key]['isActive'] = $this->pObj->act == $key;
        $menuDefinition[$key]['label'] = "notes://";
        $menuDefinition[$key]['url'] = '#';
        $menuDefinition[$key]['addParams'] = 'onclick="jumpToUrl(\'?act='.$key.'&editorNo='.$this->pObj->editorNo.'&contentTypo3Language='.$this->pObj->contentTypo3Language.'&contentTypo3Charset='.$this->pObj->contentTypo3Charset.'\');return false;"';

        return $menuDefinition;
    }

    function getTab($act) {
    	global $TCA,$BE_USER, $BACK_PATH, $LANG;

		$javascriptMethod = 'browse_links_setValue';
        if ($this->isRTE()) {
        	$javascriptMethod = 'browse_links_setHref';	
        	
    	}

		$extUrl='
	<!--
		Enter External URL:
	-->
			<form action="" name="lnotesform" id="lurlform">
				<table border="0" cellpadding="2" cellspacing="1" id="typo3-linkNOTES">
					<tr>
						<td>URL:</td>
						<td><input type="text" name="lnotes" '.$this->pObj->doc->formWidth(20).' value="'.htmlspecialchars($this->pObj->curUrlInfo['act']=='t3notes_tab'?$this->pObj->curUrlInfo['info']:'notes://').'" /> '.
							'<input type="submit" value="'.$LANG->getLL('setLink',1).'" onclick="' . $javascriptMethod . '(document.lnotesform.lnotes.value); return link_current();" /></td>
					</tr>
				</table>
			</form>';

		$content .= $extUrl;

        if ($this->isRTE()) {
        	$content .= $this->pObj->addAttributesForm();	
        	
    	}

    	return $content;
    }


    function parseCurrentUrl ($href, $siteUrl, $info) {
		if (strtolower(substr($href,0,8))=='notes://') {
			$info['act']='t3notes_tab';
		}

        return $info;
    }


	private function isRTE() {
		if ($this->pObj->mode=='rte') {
			return true;
		}
		else {
			return false;
		}

	}
}
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/hooks/class.tx_t3notes_browselinkshooks.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/hooks/class.tx_t3notes_browselinkshooks.php']);
}

?>