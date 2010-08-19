<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

t3lib_div::loadTCA('pages');
	// needed for linkchecker
$TCA['pages']['columns']['url']['config']['softref'] .= ',typolink';

	// needed for quicklinks
$TCA['pages']['columns']['urltype']['config']['items'][] =	array(
																"notes://",
																"5"
															);

?>