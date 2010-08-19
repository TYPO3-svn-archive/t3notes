<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

class tx_t3notes_ts_links_rte_process {
	function preProcessHref(&$ref, &$href) {
		$result = strpos($href, 'notes://');
		if($result !== FALSE) {
			$href = substr($href, $result);
		}
	}

}
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/hooks/class.tx_t3notes_ts_links_rte_process.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/hooks/class.tx_t3notes_ts_links_rte_process.php']);
}
?>
