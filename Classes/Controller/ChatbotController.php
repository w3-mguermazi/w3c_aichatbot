<?php
declare(strict_types=1);

namespace W3code\W3cAichatbot\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use W3code\W3cAichatbot\Service\AiChatbotService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * This file is part of the "w3c_aichatbot" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
class ChatbotController extends ActionController
{
    private AiChatbotService $aiChatbotService;

    public function __construct(AiChatbotService $aiChatbotService)
    {
        $this->aiChatbotService = $aiChatbotService;
    }

    /**
     * index action
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addJsFile('EXT:w3c_aichatbot/Resources/Public/JavaScript/chatbot.js');
        $pageRenderer->addJsFile('https://cdn.jsdelivr.net/npm/marked/marked.min.js');
        $pageRenderer->addCssFile('EXT:w3c_aichatbot/Resources/Public/Css/chatbot.css');

        $pageArguments = $this->request->getAttribute('routing');
        $pageId = $pageArguments->getPageId();

        $this->view->assign('currentPageUid', $pageId);
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
        return $this->jsonResponse(json_encode($response));
    }
    
    /**
     * ask action
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

        foreach ($generator as $textChunk) {
            // Format the output for Server-Sent Events
            echo "data: " . json_encode($textChunk) . "\n\n";

            // Flush the output buffers to send the data to the browser immediately
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        exit;
    }
}
