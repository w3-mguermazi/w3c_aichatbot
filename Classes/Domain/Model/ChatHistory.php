<?php

namespace W3code\W3cAichatbot\Domain\Model;

use W3code\W3cAichatbot\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class ChatHistory extends AbstractEntity
{
    /**
     * @var string
     */
    protected $question = '';

    /**
     * @var string
     */
    protected $answer = '';

    /**
     * @var FrontendUser
     */
    protected $feUser;

    /**
     * @return string
     */
    public function getQuestion(): string
    {
        return $this->question;
    }

    /**
     * @param string $question
     */
    public function setQuestion(string $question): void
    {
        $this->question = $question;
    }

    /**
     * @return string
     */
    public function getAnswer(): string
    {
        return $this->answer;
    }

    /**
     * @param string $answer
     */
    public function setAnswer(string $answer): void
    {
        $this->answer = $answer;
    }

    /**
     * @return FrontendUser
     */
    public function getFeUser(): FrontendUser
    {
        return $this->feUser;
    }

    /**
     * @param FrontendUser $feUser
     */
    public function setFeUser(FrontendUser $feUser): void
    {
        $this->feUser = $feUser;
    }
}