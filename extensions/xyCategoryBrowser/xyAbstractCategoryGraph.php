<?php
/**
* @author  xypron
* @version 1.7 - 2014-12-08
* @file xyAbstractCategoryGraph.php
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
 * @brief Abstract class creating category graphs.
 */ 
abstract class xyAbstractCategoryGraph {
  /**
   * @brief Add debug text to output.
   */
  protected $debug = true;

  /**
   * @brief Constructs a category graph.
   */
  public function __construct() {
    $this->debug = false;
    if(isset($_GET['debug'])) {
      $this->debug = true;
    }
  }  

  /**
   * @brief Constructs SQL statement to select categories related to title.
   *
   * @param $title title
   * @return SQL statement
   */
  abstract function getSQLCategories($title=null);

  /**
   * @brief Constructs SQL statement to select links between categories.
   *
   * @param $title title
   * @return SQL statement
   */ 
  abstract function getSQLCategoryLinks($title);

  /**
   * @brief Embeds category graph into page.
   *
   * @param title page title
   */
  function doQuery($title = null) {
    echo 'doQuery';
    global $wgOut;
    global $xyCategoryGraphStyle;
    error_reporting(0);
    $colorNode         = $xyCategoryGraphStyle["COLOR_NODE"];
    $colorNodeError    = $xyCategoryGraphStyle["COLOR_NODE_ERROR"];
    $colorNodeRedirect = $xyCategoryGraphStyle["COLOR_NODE_REDIRECT"];
    $colorNodeMissing  = $xyCategoryGraphStyle["COLOR_NODE_MISSING"];
    $colorLinkRedirect = $xyCategoryGraphStyle["COLOR_LINK_REDIRECT"];
    $height            = $xyCategoryGraphStyle["HEIGHT"]/96;
    $width             = $xyCategoryGraphStyle["WIDTH"]/96;
 
    $redirections= Array();
    $nodes=Array();
    # Start digrapph and set defaults
    $this->dot = "digraph a {\nsize=\"{$width},{$height}\";\nrankdir=LR;\n".
      "node [height=0 style=\"filled\", shape=\"box\", ".
      "font=\"Helvetica-Bold\", fontsize=\"10\", color=\"#00000\"];\n";
    $dbr =& wfGetDB( DB_SLAVE );
 
    $sql= $this->getSQLCategories($title);
    $res = $dbr->query( $sql );
    for ( $i = 0; $obj = $dbr->fetchObject($res); $i++ ) {
      $l_title = Title::makeTitle(NS_CATEGORY, $obj->cat);
 
      $color = $colorNode;
      if($obj->redirect==1) $color = $colorNodeRedirect;
      if($obj->missing == 1) $color = $colorNodeMissing;
      $nodes[$obj->cat] = array(
        'color' => $color,
        'url'   => $l_title->getFullURL(),
        'peri'  => 1,
        'label' => str_replace( '_', ' ', $obj->cat )
        );
 
      if ($title && $obj->cat == $title->getDBkey()) {
        $nodes[$obj->cat]['peri']=2;
        }
      if ($obj->redirect) {
        $article = new article($l_title);
        if ($article) {
          $text = $article->getContent();
          $rt = Title::newFromRedirect($text);
          if ($rt) {
            if (NS_CATEGORY == $rt->getNamespace()) {
              $redirections[$l_title->getDBkey()] = $rt->getDBkey();
              if (!$nodes[$rt->getDBkey()]){
                $nodes[$rt->getDBkey()] = array(
                  'color' => $colorNode,
                  'url'   => $rt->getFullURL(),
                  'peri'  => 1
                  );
                }
              }
            }
          }
        }
      }
 
    $sql= $this->getSQLCategoryLinks($title);
    $res = $dbr->query( $sql );
    for ( $i = 0; $obj = $dbr->fetchObject( $res ); $i++ ) {
      $cat_from = Title::makeName(NS_CATEGORY, $obj->cat_from);
      $cat_to   = Title::makeName(NS_CATEGORY, $obj->cat_to);
      # If destination node has not been read highlight the error.
      if (@!$nodes[$obj->cat_to]){
        $rt = Title::makeTitle(NS_CATEGORY, $obj->cat_to);
        $nodes[$rt->getDBkey()] = array(
          'color' => $colorNodeError,
          'url'   => $rt->getFullURL(),
          'peri'  => ($title && $rt->getDBkey() == $title->getDBkey())? 2 : 1,
          'label' => str_replace( '_', ' ', $rt->getDBkey())
          );
        }
      if (!$redirections[$obj->cat_from] ||  $redirections[$obj->cat_from] != $obj->cat_to) {
        $this->dot .= "\"$obj->cat_to\" -> \"$obj->cat_from\" [dir=back];\n";
        }
      }
    # Create redirection links 
    foreach( $redirections as $cat_from => $cat_to) {
      $this->dot .= "\"$cat_to\" -> \"$cat_from\" [color=\"".$colorLinkRedirect."\", dir=back];\n";
      }
 
    foreach( $nodes as $l_DbKey=>$properties ) {   
      $l_title = Title::makeTitle(NS_CATEGORY, $l_DbKey);
      $this->dot .= "\"$l_DbKey\" [URL=\"{$properties['url']}\",".
        "peripheries={$properties['peri']},label=\"{$properties['label']}\",".
        "fillcolor=\"{$properties['color']}\"];\n";
      }
 
    $this->dot .= "}\n";
    if ($this->debug) $wgOut->addWikiText("<"."pre>$this->dot<"."pre>");
    }
 
/**
 * @brief Saves dot file and generates png and map file.
 *
 * @param title to generate md5 for filename
 */
  function cacheAge( $title ) {
    echo 'cacheAge';

    $md5 = md5($title);
    $docRoot = $this->cachePath();
    $fileMap = "$docRoot$md5.map";
 
    if (!file_exists($fileMap)) return false; 
    return time() - filemtime($fileMap);
    }
 
 
/**
 * @brief Save dot file and generate png and map file
 *
 * @param title to generate md5 for filename
 */
  function doDot( $title ) {
    echo 'doDot';

    global $wgOut;
    global $xyDotPath;
 
    $md5 = md5($title);
    $docRoot = $this->cachePath();
    $fileDot = "$docRoot$md5.dot";
    $fileMap = "$docRoot$md5.map";
    $filePng = "$docRoot$md5.png";
 
    $this->file_put_contents($fileDot, $this->dot);
 
    if ($xyDotPath) {
 
      if ($this->debug) $wgOut->addWikiText("$xyDotPath -Tpng -o$filePng <$fileDot");
      $result = shell_exec("$xyDotPath -Tpng -o$filePng <$fileDot");
 
      if ($this->debug) $wgOut->addWikiText("$xyDotPath -Tcmap -o$fileMap <$fileDot");
      $map = shell_exec("$xyDotPath -Tcmap -o$fileMap <$fileDot");
      }
    }
 
  /**
   * @brief Serves PNG file.
   *
   * This function is used to deliver the PNG file to the client.
   * Client side cache behaviour is controlled here.
   * This is necessary to get a match between the html and the image.
   *
   * The name of PNG file to serve is indicated by GET parameter
   * "png".
   *
   * If the file is successfully served the function returns true.
   *
   * @return success
   */
  function serveFile() {
    echo 'serveFile';

    global $xyCategoriesMaxAge;
    // Get filename from GET parameter
    if(isset($_GET['png'])) {
      $filename = @$_GET['png'];
      }
    else {
      return false;
      }
    // Check filename is valid
    if (preg_match('/\\W/',$filename))return false;
    $docRoot = $this->cachePath();
    $file = "$docRoot$filename.png";
    // Check file exists
    if (!file_exists($file)) return false;
    // Get filetime
    $time = @filemtime($file);
    // Get filesize
    $size = @filesize($file);
  
    $etag = md5("$time|$size");
    // Get "Last-Modified"
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
      $oldtime=strtotime(current(explode(';',$_SERVER['HTTP_IF_MODIFIED_SINCE'])));
      }
    // Get "ETag"
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
      $oldetag=explode(',',$_SERVER['HTTP_IF_NONE_MATCH']);
      }
    // If either is unchanged the file is not modified.     
    if ( (isset($oldtime) && $oldtime == $time ) ||
       (isset($oldetag) && $oldetag == $etag ) ) {
      header('HTTP/1.1 304 Not Modified');
      header('Date: '.gmdate('D, d M Y H:i:s').' GMT');
      header('Server: PHP');
      header("ETag: $etag");
      return true;
      }
    // Send headers
    header('HTTP/1.1 200 OK');
    header('Date: '.gmdate('D, d M Y H:i:s').' GMT');
    header('Server: PHP');
    header('Last-Modified: '.gmdate('D, d M Y H:i:s',$time).' GMT');
    header('Expires: '.gmdate('D, d M Y H:i:s',$time+$xyCategoriesMaxAge).' GMT');
    // Supply the filename that is proposed when saving the file to disk   
    header("Content-Disposition: inline; filename=cat.png");
    header("ETag: $etag");
    header("Accept-Ranges: bytes");
    header("Content-Length: ".(string)(filesize($file)));
    header("Connection: close\n");
    header("Content-Type: image/png");
    // Send file
    $h = fopen($file, 'rb');
    fpassthru($h);
    fclose($h);
    return true;
  }
 
  /**
   * @brief Outputs the image to the OutputPage object.
   *
   * @param title to generate md5 for filename
   */
  function showImg( $title ) {
    echo 'showImg';

    global $wgOut;
    global $wgUploadPath, $wgScriptPath;
 
    $docRoot = $this->cachePath();
    $md5 = md5($title);
    $fileMap = "$docRoot$md5.map";
 
    $script = "xyCategoryBrowser.php";
 
    if (file_exists($fileMap)) {
      $map = $this->file_get_contents($fileMap);
      if ($this->debug) $wgOut->addWikiText("<"."pre>$map<"."/pre>");
 
      $URLpng =  "$wgScriptPath/extensions/xyCategoryBrowser/$script?png=$md5";
      $wgOut->addHTML("<DIV id=\"xyCategoryBrowser\"><IMG src=\"$URLpng\" usemap=\"#map1\" alt=\"$title\"><MAP name=\"map1\">$map</MAP>");
      if ($this->debug) {
        $wgOut->addWikiText(
          wfMsg('xyrenderedwith')." [http://www.graphviz.org/ Graphviz - Graph Visualization Software]".
          ', '.date("Y-m-d H:i:s.", filemtime($fileMap))."\n----\n");
        }
      $wgOut->addHTML("</DIV>");
      return true;
      }
    else {
      return false;
      }
    }
 
  /**
   * @brief Provied path to graphviz files.
   *
   * @return directory for graphviz files
   */
  function cachePath() {
    echo 'cachePath';

    global $xyCategoriesCache;
 
    $path = __DIR__.'/'.$xyCategoriesCache;
    if (substr( php_uname( ), 0, 7 ) == "Windows") $path = preg_replace('/\\//', '\\',$path);
    if (!is_dir($path)) {
      mkdir($path, 0775);
      }
    return $path;
    }
 
 
  /**
   * @brief Writes binary string to file.
   *
   * @param $n file name
   * @param $d binary string
   *
   * @return success
   */
  function file_put_contents($n,$d) {
    echo 'filePutContents';

    $f=@fopen($n,"wb");
    if (!$f) {
      return false;
      } 
    else {
      fwrite($f,$d);
      fclose($f);
      return true;
      }
    }    

  /**
   * @brief Reads binary string from file.
   *
   * @param $n file name
   *
   * @return binary string (or false if failed)
   */
  function file_get_contents($n) {
    echo 'file get contents';

    $f=@fopen($n,"rb");
    if (!$f) {
      return false;
      } 
    else {
      $s=filesize($n);
      $d=false;
      if ($s) $d=fread($f, $s) ; 
      fclose($f);
      return $d;
      }
    }    
  }
?>
