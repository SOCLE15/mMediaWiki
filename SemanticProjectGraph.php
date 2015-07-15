<?php

/**
 *
 * @author Hugo Djemaa <hugo.djemaa@gmail.com>
 * @version 0.5.0
 * @link https://github.com/realsoc/SemanticProjectGraph
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
 
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is reference.
 */
if( !defined( 'MEDIAWIKI' ) ) {
        echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
        die( -1 );
}

# Define a setup function
$wgExtensionFunctions[] = 'SemanticProjectGraphParserFunction_Setup';

// Extension credits that will show up on Special:Version    
$wgExtensionCredits['parserhook'][] = array(
		'path' 			=> __FILE__,
        'name'         => 'SemanticProjectGraph',
        'version'      => '0.5',
        'author'       => array('Hugo Djemaa'), 
        'url'          => 'https://github.com/realsoc/SemanticProjectGraph',
        'description'  => 'This extension render graphs for three type of objects (described here : http://smw.learning-socle.org')
);
$dir = __DIR__ . '/';
include_once($dir.'includes/Project.php');
include_once($dir.'includes/TechnicalRequirement.php');
include_once($dir.'includes/Recipe.php');

function SemanticProjectGraphParserFunction_Setup() {
        global $wgParser;
        # Set a function hook associating the "example" magic word with our function
        $wgParser->setFunctionHook( 'projectgraph', 'SemanticProjectGraphFunction_Render' );
        $wgParser->setFunctionHook( 'recipegraph', 'SemanticRecipeGraphFunction_Render' );
        $wgParser->setFunctionHook( 'techreqgraph', 'SemanticTechReqGraphFunction_Render' );
}

function SemanticProjectGraphFunction_Render( &$parser, $param1 = '') {
	$mProject = new Project($param1);
	return $mProject->createGraph();
	//testing:     
	//return "<pre>".$dottext."</pre>";
}

function SemanticRecipeGraphFunction_Render( &$parser,$param1 = '') {
	$mProject = new Recipe($param1);
	return $mProject->createGraph();
	//testing:
	//return "<pre>$hgtext</pre>";
}

function SemanticTechReqGraphFunction_Render( &$parser, $param1 = '') {
	$mProject = new TechnicalRequirement($param1);
	return $mProject->createGraph();
}
?>
