<?php
/**
* @author  xypron
* @version 1.7 - 2014-12-08
* @file xyCategoriesPage.php
* @ingroup Extension
* @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 3 or later
*
* Copyright (C) 2013, Heinrich Schuchardt
*
* Changes:
* 1.7 Update license information
*     Check MEDIAWIKI is defined
* 1.6 Created
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Lesser Public License for more details.
*
* You should have received a copy of the GNU Lesser Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*/

if(!defined('MEDIAWIKI'))
  die("This is not a valid entry point to MediaWiki.\n");

require_once 'xyAbstractCategoryGraph.php';

/**
 * @brief Creates content for special page with graph of all categories.
 */
class xyCategoriesPage extends xyAbstractCategoryGraph{

  /**
   * @brief Constructs SQL statement to select categories related to title.
   *
   * @param $title title
   * @return SQL statement
   */
  function getSQLCategories( $title = null ) {
   //echo 'xyCategoriesPage->getSQLCategories<br>';

    global $wgOut;

    $NScat = NS_CATEGORY;
    $dbr =& wfGetDB(DB_SLAVE);
    $categorylinks = $dbr->tableName('categorylinks');
    $page          = $dbr->tableName('page');
    $sql =
      "SELECT\n".
      "    page_title AS cat,\n".
      "    page_is_redirect AS redirect,\n".
      "    0 AS missing\n".
      "  FROM $page\n".
      "  WHERE\n".
      "    page_namespace={$NScat}\n".
      "UNION\n".
      "SELECT\n".
      "    cl_to as cat,\n".
      "    0 AS redirect,\n".
      "    1 AS missing\n".
      "  FROM $categorylinks\n".
      "  LEFT JOIN $page\n".
      "  ON page_title=cl_to\n".
      "  WHERE\n".
      "    page_id IS NULL";
    if ($this->debug) $wgOut->addWikiText("<"."pre>$sql<"."/pre>");
    return $sql;
  }

  /**
   * @brief Constructs SQL statement to select links between categories.
   *
   * @param $title title
   * @return SQL statement
   */
  function getSQLCategoryLinks( $title = null ) {
   //echo 'xyCategoriesPage->getSQLCategoryLinks<br>';

    global $wgOut;

    $NScat = NS_CATEGORY;
    $dbr =& wfGetDB(DB_SLAVE);
    $categorylinks = $dbr->tableName('categorylinks');
    $page          = $dbr->tableName('page');
    $sql =
      "SELECT\n".
      "    page_title AS cat_from, \n".
      "    cl_to as cat_to,\n".
      "    page_is_redirect AS redirect\n".
      "  FROM $page\n".
      "  INNER JOIN $categorylinks\n".
      "  ON page_id=cl_from\n".
      "  WHERE\n".
      "    page_namespace={$NScat}";
    if ($this->debug) $wgOut->addWikiText("<"."pre>$sql<"."/pre>");

    return $sql;
  }
}

?>
