<?php
declare(strict_types=1);

namespace W3code\W3cAichatbot\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use W3code\W3cAichatbot\Domain\Model\ChatHistory;
use W3code\W3cAichatbot\Domain\Repository\ChatHistoryRepository;
use W3code\W3cAichatbot\Service\AiChatbotService;
use TYPO3\CMS\Core\Page\PageRenderer;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use W3code\W3cAichatbot\Domain\Repository\FrontendUserRepository;
use League\CommonMark\CommonMarkConverter;

/**
 * This file is part of the "w3c_aichatbot" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
class ChatbotController extends ActionController
{
    const CHAT_HISTORY_COOKIE_NAME = 'tx_w3caichatbot_chathistory';
    private AiChatbotService $aiChatbotService;
    protected LoggerInterface $logger;
    private ChatHistoryRepository $chatHistoryRepository;
    private FrontendUserRepository $frontendUserRepository;
    private $feUser;

    public function __construct(
        AiChatbotService $aiChatbotService,
        LogManagerInterface $logManager,
        ChatHistoryRepository $chatHistoryRepository,
        FrontendUserRepository $frontendUserRepository
    ) {
        $this->aiChatbotService = $aiChatbotService;
        $this->logger = $logManager->getLogger(static::class);
        $this->chatHistoryRepository = $chatHistoryRepository;
        $this->frontendUserRepository = $frontendUserRepository;
        $context = GeneralUtility::makeInstance(Context::class);
        $this->feUser = $context->getAspect('frontend.user');
    }

    /**
     * index action
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addJsLibrary('marked', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js');
        $pageRenderer->addJsFile('EXT:w3c_aichatbot/Resources/Public/JavaScript/chatbot.js');
        $pageRenderer->addCssFile('EXT:w3c_aichatbot/Resources/Public/Css/chatbot.css');

        $pageArguments = $this->request->getAttribute('routing');
        $pageId = $pageArguments->getPageId();

        $history = [];
        $converter = new CommonMarkConverter();

        if ($this->feUser->isLoggedIn()) {
            $chatHistory = $this->chatHistoryRepository->findLastFiveByFeUser($this->feUser->get('id'));
            foreach ($chatHistory as $chat) {
                $history[] = ['question' => $chat->getQuestion(), 'answer' => $converter->convert($chat->getAnswer())->getContent()];
            }
        } else {
            if (isset($_COOKIE[self::CHAT_HISTORY_COOKIE_NAME])) {
                $history = json_decode($_COOKIE[self::CHAT_HISTORY_COOKIE_NAME], true);
            }
        }

        $this->view->assignMultiple([
            'currentPageUid' => $pageId,
            'history' => array_reverse($history)
        ]);
        return $this->htmlResponse();
    }

    /**
     * ask action
     *
     * @param string $question
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function askAction(string $question): ResponseInterface
    {
        $siteLanguage = $this->request->getAttribute('language');

        $response = $this->aiChatbotService->getResponse($question, $siteLanguage);
        $this->saveHistory($question, $response['result']);

        return $this->jsonResponse(json_encode($response));
    }

    /**
     * ask stream action
     *
     * @param string $question
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function askStreamAction(string $question): never
    {
        $siteLanguage = $this->request->getAttribute('language');

        $generator = $this->aiChatbotService->getStreamedResponse($question, $siteLanguage);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $response = '';
        foreach ($generator as $textChunk) {
            // Format the output for Server-Sent Events
            $response .= $textChunk;
            echo "data: " . json_encode($textChunk) . "\n\n";

            // Flush the output buffers to send the data to the browser immediately
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
        $this->logger->info('AI final response', ['response' => $response]);
        $this->saveHistory($question, $response);

        exit;
    }

    private function saveHistory(string $question, string $answer): void
    {
        if ($this->feUser->isLoggedIn()) {
            $user = $this->frontendUserRepository->findByUid($this->feUser->get('id'));
            $chatHistory = new ChatHistory();
            $chatHistory->setFeUser($user);
            $chatHistory->setQuestion($question);
            $chatHistory->setAnswer($answer);
            $this->chatHistoryRepository->add($chatHistory);

            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
            $persistenceManager->persistAll();
        } else {
            $history = [];
            if (isset($_COOKIE[self::CHAT_HISTORY_COOKIE_NAME])) {
                $history = json_decode($_COOKIE[self::CHAT_HISTORY_COOKIE_NAME], true);
            }

            $history[] = ['question' => $question, 'answer' => $answer];

            if (count($history) > 5) {
                $history = array_slice($history, -5);
            }

            setcookie(self::CHAT_HISTORY_COOKIE_NAME, json_encode($history), time() + (86400 * 30), "/"); // 30 days
        }
    }
}
