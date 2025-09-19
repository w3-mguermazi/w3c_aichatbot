<?php
defined('TYPO3') or die();

use W3code\W3cAichatbot\Controller\ChatbotController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;

(function () {
    ExtensionUtility::configurePlugin(
        'W3cAichatbot',
        'Chatbot',
        [
            ChatbotController::class => 'index,ask'
        ],
        // non-cacheable actions
        [
            ChatbotController::class => 'index,ask'
        ]
    );

    ExtensionUtility::configurePlugin(
        'W3cAichatbot',
        'ChatbotAjax',
        [
            ChatbotController::class => 'ask'
        ],
        // non-cacheable actions
        [
            ChatbotController::class => 'ask'
        ]
    );

    ExtensionManagementUtility::addTypoScript(
        'w3c_aichatbot',
        'setup',
        '@import "EXT:w3c_aichatbot/Configuration/TypoScript/setup.typoscript"'
    );

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['W3code']['W3cAichatbot']['Service']['AiChatbotService']['writerConfiguration'] = [
        // Configure for INFO level and higher
        LogLevel::INFO => [
            FileWriter::class => [
                'logFile' => Environment::getVarPath() . '/log/w3c_aichatbot_info.log',
            ],
        ],
    ];
})();