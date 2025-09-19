<?php
defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'W3cAichatbot',
    'Chatbot',
    'LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang_db.xlf:plugin.chatbot.title'
);