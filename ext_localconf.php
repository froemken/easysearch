<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'StefanFroemken.' . $_EXTKEY,
    'Easysearch',
    array(
        'Search' => 'form, results',
    ),
    array(
        'Search' => 'results'
    )
);

/**
 * Registering class to scheduler
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['StefanFroemken\\Easysearch\\Tasks\\Indexer'] = array(
    'extension' => $_EXTKEY,
    'title' => 'EasySearch: Indexer',
    'description' => 'This task starts the indexer',
);
