<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'StefanFroemken.' . $_EXTKEY,
    'Easysearch',
    'LLL:EXT:easysearch/Resources/Private/Language/locallang_db.xlf:plugin.title'
);