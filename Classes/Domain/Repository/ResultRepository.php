<?php

namespace StefanFroemken\Easysearch\Domain\Repository;

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
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @package easysearch
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ResultRepository extends Repository
{

    /**
     * Get results
     *
     * @param string $q
     * @return array
     */
    public function findResults($q)
    {
        $orConstraint = array();
        $words = GeneralUtility::trimExplode(' ', htmlspecialchars(strip_tags($q)));
        foreach ($words as $word) {
            $orConstraint[] = sprintf(
                'tx_easysearch_words.word=%s',
                $this->getDatabaseConnection()->fullQuoteStr($word, 'tx_easysearch_words')
            );
        }
        $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
            'tx_easysearch_rel.page_uid, pages.title, SUM(tx_easysearch_rel.amount) as score',
            'tx_easysearch_words
            LEFT JOIN tx_easysearch_rel
            ON tx_easysearch_words.uid = tx_easysearch_rel.word_uid
            LEFT JOIN pages
            ON tx_easysearch_rel.page_uid = pages.uid',
            '(' . implode(' OR ', $orConstraint) . ')',
            'tx_easysearch_rel.page_uid',
            'score DESC',
            '10'
        );
        if (empty($rows)) {
            $rows = array();
        }
        return $rows;
    }

    /**
     * Get TYPO3s database connection
     *
     * @return DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
