<?php
/**
* @author  xypron
* @version 1.7 - 2014-12-08
* @file xyCategoriesSpecialPage.php
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

/**
 * @brief Special page for graphical category browser.
 */
class xyCategoriesSpecialPage extends SpecialPage {

  /**
   * @brief Constructs new special page.
   */
  function __construct() {
    parent::__construct('Xygraphicalcategorybrowser');
  }

  /**
   * @brief Provides special page.
   */
  function execute($par) {
    require_once 'xyCategoriesPage.php';
    $cap = new xyCategoriesPage();
    $cap->doQuery();
    $cap->doDot('xycategorybrowser');
    $cap->showImg('xycategorybrowser');
  }
}
