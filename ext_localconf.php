<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

	// cag_linkchecker
$TYPO3_CONF_VARS['EXTCONF']['cag_linkchecker']['checkLinks']['lotusnotes'] = 'EXT:t3notes/lib/class.tx_caglinkchecker_checknoteslinks.php:tx_caglinkchecker_checknoteslinks';

	// "notes://" for external url jump 
$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_page.php'] = t3lib_extMgm::extPath($_EXTKEY).'typo3_versions/'.TYPO3_version.'/class.ux_t3lib_page.php';
$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templavoila/mod1/class.tx_templavoila_mod1_specialdoktypes.php'] = t3lib_extMgm::extPath($_EXTKEY).'typo3_versions/'.TYPO3_version.'/class.ux_tx_templavoila_mod1_specialdoktypes.php';

	// linkhandler hooks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['TS_links_rte_process'][] = 'EXT:t3notes/hooks/class.tx_t3notes_ts_links_rte_process.php:&tx_t3notes_ts_links_rte_process';
$TYPO3_CONF_VARS['SC_OPTIONS']['typo3/class.browse_links.php']['browseLinksHook'][] = 'EXT:t3notes/hooks/class.tx_t3notes_browselinkshooks.php:tx_t3notes_browselinkshooks';
$TYPO3_CONF_VARS['SC_OPTIONS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php']['browseLinksHook'][] = 'EXT:t3notes/hooks/class.tx_t3notes_browselinkshooks.php:tx_t3notes_browselinkshooks';


t3lib_extMgm::addService($_EXTKEY,  'auth' /* sv type */,  'tx_t3notes_sv1' /* sv key */,
		array(

			'title' => 'Lotus Notes authentification',
			'description' => 'Manages the authentification by a Lotus Notes Server',

			'subtype' => 'getUserFE,authUserFE',

			'available' => TRUE,
			'priority' => 80,
			'quality' => 80,

			'os' => '',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'sv1/class.tx_t3notes_sv1.php',
			'className' => 'tx_t3notes_sv1',
		)
	);
?>
