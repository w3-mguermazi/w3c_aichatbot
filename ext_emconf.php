<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'W3Code AI Chatbot',
    'description' => 'Chatbot using AI and vectorial database index',
    'category' => 'plugin',
    'state' => 'alpha',
    'author' => 'Mehdi Guermazi',
    'author_email' => 'mehdi.guermazi@w3code.tn',
    'author_company' => 'W3CODE',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'solr' => '12.0.0-12.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
