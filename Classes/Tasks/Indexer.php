<?php

namespace StefanFroemken\Easysearch\Tasks;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Stefan Froemken <froemken@gmail.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use StefanFroemken\Easysearch\Lexer\StringLexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * @package easysearch
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Indexer extends AbstractTask
{

    /**
     * @var array
     */
    protected $queuedWordsForInsert = array();

    /**
     * @var array
     */
    protected $queuedRelationsForInsert = array();

    /**
     * @var array
     */
    protected $wordListOfCurrentPage = array();


    /**
     * @var StringLexer
     */
    protected $lexer = null;

    /**
     * Initialize this object before indexing
     *
     * @return void
     */
    protected function initialize()
    {
        $this->lexer = GeneralUtility::makeInstance(StringLexer::class);
    }

    /**
     * The first method which will be executed when task starts
     *
     * @return boolean
     */
    public function execute()
    {
        $this->initialize();
        $contentElements = $this->getDatabaseConnection()->exec_SELECTgetRows(
            'p.uid, p.title, t.sys_language_uid, LOWER(t.header) as header, LOWER(t.bodytext) as bodytext',
            'pages p
            LEFT JOIN tt_content t
            ON p.uid = t.pid',
            'p.doktype = 1
            AND (p.fe_group = \'\' OR t.fe_group = 0)
            AND (t.fe_group = \'\' OR t.fe_group = 0)
            AND t.sys_language_uid = 0'
        );
        if (!$contentElements) {
            return false;
        }
        $lastPage = 0;
        foreach ($contentElements as &$contentElement) {
            if ($lastPage !== (int)$contentElement['uid']) {
                $lastPage = (int)$contentElement['uid'];
                $this->removeRelationsOfPage($contentElement['uid']);
            }
            $this->queuedWordsForInsert = array();
            $this->queuedRelationsForInsert = array();
            $contentElement['bodytext'] = strip_tags($contentElement['bodytext']);
            $this->addContentElementToQueue($contentElement);
        }
        return true;
    }

    /**
     * add record to Queue
     *
     * @param array $contentElement
     * @return void
     */
    protected function addContentElementToQueue(array $contentElement)
    {
        if (!$this->isValid($contentElement)) {
            return;
        }
        // fill queues with extracted grouped words of bodytext
        $words = $this->lexer->extractWordsFromContent($contentElement['bodytext']);
        // add words to queues if they are already in DB
        $this->fillQueues($contentElement['uid'], $words);
        // save words which are not already in DB to have assigned UIDs
        $this->saveQueuedWords();
        // Add all queued words (which now have an UID) to queue, too
        $this->fillQueues($contentElement['uid'], $this->queuedWordsForInsert);
        // All queues are filled. Now, save queued relations
        $this->saveQueuedRelations();
    }

    /**
     * Fill queues for current page
     *
     * @param int $pid
     * @param array $words 0 => array($word => $amount)
     */
    protected function fillQueues($pid, array $words)
    {
        foreach ($words as $data) {
            $wordUid = $this->getWordUid($data['word']);
            if (!$wordUid) {
                $this->wordListOfCurrentPage[$data['word']] = array(
                    'amount' => $data['amount']
                );
                $this->queuedWordsForInsert[] = array(
                    'word' => $data['word']
                );
            } else {
                $this->addRelationToQueue(
                    $wordUid,
                    $this->wordListOfCurrentPage[$data['word']]['amount'],
                    $pid
                );
            }
        }
    }

    /**
     * add relation to queue
     *
     * @param int $wordUid
     * @param int $amount
     * @param int $pid
     */
    protected function addRelationToQueue($wordUid, $amount, $pid)
    {
        $this->queuedRelationsForInsert[] = array(
            'word_uid' => (int)$wordUid,
            'amount' => (int)$amount,
            'page_uid' => (int)$pid,
        );
    }

    /**
     * Check, if content element is valid
     *
     * @param array $contentElement
     * @return bool
     */
    protected function isValid(array $contentElement)
    {
        return
            !empty($contentElement['uid']) &&
            !empty($contentElement['title']) &&
            !empty($contentElement['bodytext']);
    }

    /**
     * Get word UID from database
     *
     * @param string $word
     * @return int|false UID of record, FALSE on empty result
     */
    protected function getWordUid($word)
    {
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
            'uid',
            'tx_easysearch_words',
            'word = ' . $this->getDatabaseConnection()->fullQuoteStr($word, 'tx_easysearch_words')
        );
        if (empty($row)) {
            // @ToDo: Log error on NULL
            return false;
        } else {
            return $row['uid'];
        }
    }

    /**
     * Remove all relations of page
     *
     * @param int $pageUid
     * @return void
     */
    protected function removeRelationsOfPage($pageUid)
    {
        $this->getDatabaseConnection()->exec_DELETEquery(
            'tx_easysearch_rel',
            'page_uid=' . (int)$pageUid
        );
    }

    /**
     * save queued words to database
     *
     * @return void
     */
    protected function saveQueuedWords()
    {
        if (!empty($this->queuedWordsForInsert)) {
            $this->getDatabaseConnection()->exec_INSERTmultipleRows(
                'tx_easysearch_words',
                array('word'),
                $this->queuedWordsForInsert
            );
        }
    }

    /**
     * save queued relations to database
     *
     * @return void
     */
    protected function saveQueuedRelations()
    {
        if (!empty($this->queuedRelationsForInsert)) {
            $this->getDatabaseConnection()->exec_INSERTmultipleRows(
                'tx_easysearch_rel',
                array('word_uid', 'amount', 'page_uid'),
                $this->queuedRelationsForInsert
            );
        }
    }

    /**
     * return TYPO3s Database Connection
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
