<?php
/**
* @author  Heinrich Schuchardt
* @version 1.7 - 2014-12-08
* @file xyCategoryGraph.php
* @ingroup Extension
* @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 3 or later
*
* Copyright (C) 2013, Heinrich Schuchardt
*
* Changes:
* 1.7 Update license information
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

/**
 * @brief Creates graph for category page.
 */
require_once 'xyAbstractCategoryGraph.php';
class xyCategoryGraph extends xyAbstractCategoryGraph{

  /**
   * @brief Creates new instance.
   */
  public function __construct() {
    parent::__construct();
    echo 'Constructor of catgraph';
  }

  /**
   * @brief Constructs SQL statement to select categories related to title.
   *
   * @param $title title
   * @return SQL statement
   */
  function getSQLCategories( $title = null ) {
    echo 'getsqlcat cat graph';
    global $wgOut;
 
    $id   = $title->getArticleID();
    $text = $title->getDBkey();
 
    $NScat = NS_CATEGORY;
    $dbr =& wfGetDB(DB_SLAVE);
    // Use the following for MediaWiki 1.9:
    $text = $dbr->addQuotes($text);
    //$text = "'".wfStrencode($text)."'"; 
 
    $categorylinks = $dbr->tableName('categorylinks');
    $page          = $dbr->tableName('page');
    $sql =
      "SELECT\n".
      "    page_title AS cat,\n".
      "    page_is_redirect AS redirect,\n".
      "    0                AS missing\n".
      "  FROM $page as a\n".
      "  left JOIN $categorylinks as b\n".
      "  ON a.page_id=b.cl_from\n".
      "  left join $categorylinks as c\n".
      "  ON a.page_title=c.cl_to\n".
      "  WHERE\n".
      "    page_namespace = {$NScat} AND\n".
      "  (  c.cl_from    = $id OR\n".
      "     a.page_id    = $id OR\n".
      "     b.cl_to      = {$text} )\n".
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
  function getSQLCategoryLinks( $title ) {
    echo 'getsqlcatlinks cat graph';
    global $wgOut;
 
    $id   = $title->getArticleID();
    $text = $title->getDBkey();
 
    $NScat = NS_CATEGORY;
    $dbr =& wfGetDB(DB_SLAVE);
    // Use the following for MediaWiki 1.9:
    $text = $dbr->addQuotes($text);
    // $text = "'".wfStrencode($text)."'"; 
 
    $categorylinks = $dbr->tableName('categorylinks');
    $page          = $dbr->tableName('page');
    $sql =
      "SELECT\n".
      "    page_title AS cat_from, \n".
      "    cl_to as cat_to\n".
      "  FROM $page\n".
      "  INNER JOIN $categorylinks\n".
      "  ON page_id=cl_from\n".
      "  WHERE\n".
      "    ( page_id=$id  OR\n".
      "    cl_to=$text ) AND\n".
      "    page_namespace={$NScat}";
    if ($this->debug) $wgOut->addWikiText("<"."pre>$sql<"."/pre>");
    return $sql;
    }
  }
?>
