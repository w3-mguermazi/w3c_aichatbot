<?php
defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang_db.xlf:tx_w3caichatbot_domain_model_chathistory',
        'label' => 'question',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'question,answer',
        'iconfile' => 'EXT:w3c_aichatbot/Resources/Public/Icons/tx_w3caichatbot_domain_model_chathistory.gif'
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, question, answer, fe_user, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, starttime, endtime'],
    ],
    'columns' => [
        'question' => [
            'exclude' => true,
            'label' => 'LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang_db.xlf:tx_w3caichatbot_domain_model_chathistory.question',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim'
            ]
        ],
        'answer' => [
            'exclude' => true,
            'label' => 'LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang_db.xlf:tx_w3caichatbot_domain_model_chathistory.answer',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim'
            ]
        ],
        'fe_user' => [
            'exclude' => true,
            'label' => 'LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang_db.xlf:tx_w3caichatbot_domain_model_chathistory.fe_user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'default' => 0,
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'items' => [
                    '1' => [
                        '0' => 'LLL:EXT:lang/locallang_core.xlf:labels.enabled'
                    ]
                ]
            ]
        ],
        'deleted' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.deleted',
            'config' => [
                'type' => 'check',
                'default' => 0
            ]
        ],
    ],
];
