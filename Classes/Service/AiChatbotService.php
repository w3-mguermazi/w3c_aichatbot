<?php
declare(strict_types=1);

namespace W3code\W3cAichatbot\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\FnStream;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use W3code\W3cAiconnector\Service\AiConnectorFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use League\CommonMark\CommonMarkConverter;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatbotService
{
    private const SOLR_ENDPOINT = 'http://solr:8983/solr/';

    protected AiConnectorFactory $aiConnectorFactory;
    protected LanguageServiceFactory $languageServiceFactory;
    protected ?LanguageService $languageService = null;
    protected $aiConnector = null;
    protected $configurationManager;
    protected $typoscriptSettings;

    public function __construct(
        AiConnectorFactory $aiConnectorFactory, 
        LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
        ConfigurationManagerInterface $configurationManager
    )
    {
        $this->aiConnectorFactory = $aiConnectorFactory;
        $this->languageServiceFactory = $languageServiceFactory;
        $this->logger = $logger;
        $this->configurationManager = $configurationManager;
        $this->typoscriptSettings = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'W3cAichatbot',
                'Chatbot'
            );
    }

    public function getResponse(string $question, SiteLanguage $siteLanguage): array
    {
        $this->languageService = $this->languageServiceFactory->createFromSiteLanguage($siteLanguage);

        $this->setAiConnector();

        // Step 1: Refine the user's question with the first AI call
        $refinedSearch = $this->refineQuestion($question, $this->aiConnector);

        $this->logger->info('Refined search', [
            'question' => $question,
            'keywords' => $refinedSearch['keywords']
        ]);

        // Step 2: Build and execute the Solr query based on the refined search
        $results = $this->getSolrResults($refinedSearch, $siteLanguage);

        // Step 3: Summarize the results with the second AI call
        $summaryPrompt = $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:prompt_prefix') . htmlspecialchars($question) . "\n\n";
        $summaryPrompt .= $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:search_results_label') . "\n";
        foreach ($results as $result) {
            $summaryPrompt .= "- " . ($result['title'] ?? '') . ": " . ($result['content'] ?? '') . "\n";
        }

        $finalResponse = $this->aiConnector->process($summaryPrompt);

        if (empty($finalResponse)) {
            // Fallback response if AI fails
            $finalResponse = $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:ai_error_message') . htmlspecialchars($question)
                . $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:solr_results_count_prefix') . count($results)
                . $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:solr_results_count_suffix');
        }

        $this->logger->info('AI final response', ['response' => $finalResponse]);

        $converter = new CommonMarkConverter();

        return [
            'answer' => $converter->convert($finalResponse)->getContent(),
            'solrResults' => $results
        ];
    }

    private function refineQuestion(string $question): array
    {
        $defaultResponse = ['keywords' => $question];

        if (!$this->aiConnector) {
            return $defaultResponse;
        }

        $refinementPrompt = $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:refinement_prompt') . ' ' . $question;
        $jsonResponse = $this->aiConnector->process($refinementPrompt);

        if ($jsonResponse) {
            // Clean the response to get only the JSON part
            $jsonString = substr($jsonResponse, strpos($jsonResponse, '{'), strrpos($jsonResponse, '}') - strpos($jsonResponse, '{') + 1);
            $decoded = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['keywords'])) {
                return [
                    'keywords' => $decoded['keywords']
                ];
            }
        }

        // Fallback to default if refinement fails
        return $defaultResponse;
    }

    public function getStreamedResponse(string $question, SiteLanguage $siteLanguage): \Generator 
    {
        $this->languageService = $this->languageServiceFactory->createFromSiteLanguage($siteLanguage);

        $this->setAiConnector();

        // Step 1: Refine the user's question with the first AI call
        $refinedSearch = $this->refineQuestion($question, $this->aiConnector);

        $this->logger->info('Refined search', [
            'question' => $question,
            'keywords' => $refinedSearch['keywords']
        ]);

        // Step 2: Build and execute the Solr query based on the refined search
        $results = $this->getSolrResults($refinedSearch, $siteLanguage);

        // Step 3: Summarize the results with the second AI call
        $summaryPrompt = $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:prompt_prefix') . htmlspecialchars($question) . "\n\n";
        $summaryPrompt .= $this->languageService->sL('LLL:EXT:w3c_aichatbot/Resources/Private/Language/locallang.xlf:search_results_label') . "\n";
        foreach ($results as $result) {
            $summaryPrompt .= "- " . ($result['title'] ?? '') . ": " . ($result['content'] ?? '') . "\n";
        }

        yield from $this->aiConnector->streamProcess($summaryPrompt);
    }

    protected function setAiConnector(): void
    {
        // Chercher le service AI configuré
        // Récupérer la configuration de l'extension W3cAiconnector
        $extConfAiConnector = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');
        // Récupérer la configuration de l'extension W3cAichatbot
        $extConfAiChatbot = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aichatbot');
        $provider = $extConfAiConnector['provider'] ?? '';
        if (isset($extConfAiChatbot['provider']) && !empty($extConfAiChatbot['provider'])) {
            $provider = $extConfAiChatbot['provider'];
        }
        $this->aiConnector = $this->aiConnectorFactory->create($provider);
    }

    protected function getSolrResults(array $refinedSearch, SiteLanguage $siteLanguage): array
    {
        $solrCore = 'core_' . strtolower($siteLanguage->getHreflang());
        $solrUrl = self::SOLR_ENDPOINT . $solrCore . '/select?q=' . urlencode($refinedSearch['keywords']);
        
        $client = new Client();
        $results = [];
        try {
            $response = $client->get($solrUrl);
            $solrData = json_decode((string)$response->getBody(), true);
            $results = $solrData['response']['docs'] ?? [];
        } catch (GuzzleException $e) {
            // Handle exception, maybe log it
        }

        return $results;
    }

}
