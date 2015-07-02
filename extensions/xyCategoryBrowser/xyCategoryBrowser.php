<?php
/**
* @author  Heinrich Schuchardt
* @version 1.7 - 2014-12-08
* @file xyCategoryBrowser.php
* @ingroup Extension
* @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 3 or later
*
* Copyright (C) 2012, Heinrich Schuchardt
*
* Changes:
* 1.7 Update license and credits information
* 1.6 Refactored
* 1.5 Added DIV around category browser
*     Removed date and link to GraphViz
* 1.4 Corrected display of non existent categories
*     Color codes moved to constants
* 1.3 License changed
* 1.2 Created
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
 * @brief Path to the GraphViz dot executable
 */
// Linux
$xyDotPath = "/usr/bin/dot";
// Windows
//$xyDotPath = "\"C:\\Program Files\\ATT\\Graphviz\\bin\\dot.exe\"";

/**
 * @brief Age after which the graph is newly rendered in seconds. 
 */
$xyCategoriesMaxAge = 36;
/**
 * @brief Path for the file cache relative to this script.
 */
// ??? ? ? ?? ? / ?? 
$xyCategoriesCache = "../../images/xyGraphvizCache/";
/**
 * @brief Style of category graphs.
 */
$xyCategoryGraphStyle = array(
  "COLOR_NODE"          => "#EEEEEE", // color of category nodes
  "COLOR_NODE_ERROR"    => "#FF0000", // color for internal error
  "COLOR_NODE_REDIRECT" => "#FFCCCC", // color of redirected category nodes
  "COLOR_NODE_MISSING"  => "#FFFFCC", // color of missing category nodes
  "COLOR_LINK_REDIRECT" => "#FF0000", // color of redirect links
  "HEIGHT"              => "1920",    // height in pixels (96th of an inch)
  "WIDTH"               => "768"      // width in pixels (96th of an inch)
);

if(!defined('MEDIAWIKI')){
  require_once 'xyCategoryGraph.php';
  // Serve the PNG image
  $cap = new xyCategoryGraph();
  if ($cap->serveFile()) die();
  header("HTTP/1.1 404 Not Found");
  die("<H1>404 Not Found </H1>");
  }
 
//install special page
$wgExtensionFunctions[] = 'xyCategoryHook::hookSetup';

$wgExtensionCredits['other'][] = array(
    'name' => 'Graphical Category Browser',
    'version' => '1.7',
    'author' => 'Heinrich Schuchardt',
    'url' => 'http://www.mediawiki.org/wiki/Extension:Graphical_Category_Browser',
    'description' => 'A graph is added on top of each category page showing the relationships to other categories.',
    'license-name' => 'GPLv3'
    );  

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Graphical Category Browser',
    'version' => '1.7',
    'author' => 'Heinrich Schuchardt',
    'url' => 'http://www.mediawiki.org/wiki/Extension:Graphical_Category_Browser',
    'description' => 'A special page "Graphical Categories Browser" is added.',
    'license-name' => 'GPLv3'
    );  

// Internationalization file
$wgExtensionMessagesFiles['Xygraphicalcategorybrowser'] = __DIR__ .
    '/xyCategoryBrowser.i18n.php';
// Classes to be loaded automatically
$wgAutoloadClasses['xyCategoriesSpecialPage'] = __DIR__ .
    '/xyCategoriesSpecialPage.php'; 
$wgSpecialPages['Xygraphicalcategorybrowser'] = 'xyCategoriesSpecialPage';
$wgSpecialPageGroups['Xygraphicalcategorybrowser'] = 'pages';

/**
 * @brief Supplies hook function to add graph to category pages.
 */
class xyCategoryHook {
  /**
   * @brief Sets up hook function.
   *
   * Sets <em>CategoryPageView</em> hook.
   */ 
  public static function hookSetup() {
    echo 'xyCategoryHook->hookSetup<br>';
    global $IP, $wgMessageCache, $wgHooks;
    $wgHooks['CategoryPageView'][] = 'xyCategoryHook::hook';
  }
 
  /**
   * @brief Hook function adds graph to category pages.
   *
   * @param $cat category
   */ 
  public static function hook($cat) {
    echo 'xyCategoryHook->hook<br>';
    require_once 'xyCategoryGraph.php';
    global $wgOut, $xyCategoriesMaxAge;
    $wgOut->setSquidMaxage( $xyCategoriesMaxAge );
    $title = $cat->getTitle();
    $dbKey = $title->getDBkey();
    $cap = new xyCategoryGraph();
    $age = $cap->cacheAge($dbKey);
    if (!$age || $age > $xyCategoriesMaxAge ) {
      $cap->doQuery($title);
      $cap->doDot($dbKey);
      };
    $cap->showImg($dbKey);
    return true;
    }
  }
?>
