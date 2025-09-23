<?php

namespace W3code\W3cAichatbot\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class ChatHistoryRepository extends Repository
{
    public function findLastFiveByFeUser(int $feUserUid)
    {
        $query = $this->createQuery();
        $query->matching($query->equals('fe_user', $feUserUid));
        $query->setOrderings([
            'uid' => QueryInterface::ORDER_DESCENDING
        ]);
        $query->setLimit(5);
        return $query->execute();
    }
}