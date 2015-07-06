<?php
if (!defined('MEDIAWIKI'))
	die();

/**
 * A DomPdf based conversion backend.
 * 
 * Installation:
 * DomPdf can be downloaded from here: http://code.google.com/p/dompdf/. Unzip the code into your
 * extension directory and set $wgPdfExportDomPdfConfigFile equal to the full path to the
 * dompdf_config.inc.php file.
 * 
 * @author Andreas Hagmann
 * @author Dumpydooby
 * @author Christian Neubauer
 */
class DomPdfConverter extends PdfConverter {
	/**
	 * Sets up any necessary command line options. 
	 * @param Array $options An array of options.
	 */
	function initialize (&$options) {
		global $wgPdfExportDomPdfConfigFile;
		// Load the configuration file. This loads the whole DomPdf framework
		require_once($wgPdfExportDomPdfConfigFile);
	}

	/**
	 * Output the PDF document.
	 * @param Array $pages An array of page names.
	 * @param Array $options An array of options.
	 */
	function outputPdf ($pages, $options) {
		global $wgUser;

		$pagestring = '';
		foreach ($pages as $pg) {
			$pagestring .= $this->getPageHtml($pg, $options);
		}
		# TODO enable support for margins
		$header_footer = <<<SCRIPT
<script type="text/php">
if ( isset(\$pdf) ) {
  \$font = Font_Metrics::get_font("Helvetica");
  \$size = 10;
  \$color = array(0,0,0);
  \$text_height = Font_Metrics::get_font_height(\$font, \$size);

  \$foot = \$pdf->open_object();

  \$w = \$pdf->get_width();
  \$h = \$pdf->get_height();

  // Draw a line along the bottom
  \$y = \$h - 2 * \$text_height - 24;
  \$pdf->line(16, \$y, \$w - 16, \$y, \$color, 1);

  \$y += \$text_height;

  \$text = "";
  \$pdf->text(16, \$y, \$text, \$font, \$size, \$color);

  \$pdf->close_object();
  \$pdf->add_object(\$foot, "all");

  \$text = "Page {PAGE_NUM}/{PAGE_COUNT}";

  \$width = Font_Metrics::get_text_width("Page 10/20", \$font, \$size);
  \$pdf->page_text(\$w-5 - \$width, \$y, \$text, \$font, \$size, \$color);

}
</script>
SCRIPT;

		$html = $pagestring;
		$html = str_replace( '</body></html>', $header_footer.'</body></html>', $html );
		$html = str_replace('</head>','<style type="text/css">body{padding:10px 10px 35px;}</style></head>',$html); // Insert styling to make room for header/footer.
		$html = str_replace ('<img src="/skins/common/images/magnify-clip.png" width="15" height="11" alt="" />', "", $html);

		// Work around slow PDF generation on large pages.
		if( !ini_get('safe_mode') ) { 
            set_time_limit(120); 
        } 
        
        // TODO disable warnings temporarily and then pipe them to the log.
        
 		global $wgOut, $IP, $wgPdfExportAttach;
		$wgOut->disable();
		#$old_error_level = error_reporting( 0 );
		$dompdf = new DOMPDF();
		$dompdf->set_base_path("$IP/");
		$dompdf->set_paper( strtolower( $options['size'] ), $options['orientation']);
		$dompdf->load_html($html);
		$dompdf->render();
		$perms = array();
		if( $options['pass_protect'] == 'yes' ) {
			if( $options['perm_print'] == 'no' ) {
				$perms[] = 'no-print';
			}
			if( $options['perm_modify'] == 'no' ) {
				$perms[] = 'no-modify';
			}
			if( $options['perm_copy'] == 'no' ) {
				$perms[] = 'no-copy';
			}
			if( $options['perm_annotate'] == 'no' ) {
				$perms[] = 'no-annotate';
			}
			if( count( $perms ) == 0 ) {
				$dompdf->get_canvas()->get_cpdf()->setEncryption($options['user_pass'], $options['owner_pass'], array('all'));
			} else {
				$dompdf->get_canvas()->get_cpdf()->setEncryption($options['user_pass'], $options['owner_pass'], $perms);
			}
		}
		$dompdf->stream(utf8_decode($options['filename']) . ".pdf", array('Attachment'=>($wgPdfExportAttach?1:0)));
		#error_reporting( $old_error_level );
	}
	
	/**
	 * Get the HTML for a page. This function should filter out any code that the converter can't handle like <script> tags.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageHtml($page, $options) {
		global $wgUser;
		global $wgParser;
		global $wgScriptPath;
		global $wgServer;
		global $wgPdfExportHttpsImages;
		global $wgPdfExportMaxImageWidth;

		$title = Title::newFromText( $page );
		if( is_null( $title ) || !$title->userCan( 'read' ) )
				return null;
		$article = new Article ($title);
		$parserOptions = ParserOptions::newFromUser( $wgUser );
		$parserOptions->setEditSection( false );
		$parserOptions->setTidy(true);
		$parserOutput = $wgParser->parse( $article->preSaveTransform( "__NOTOC__\n\n".$article->getContent() ) ."\n\n", $title, $parserOptions );

		$bhtml = $parserOutput->getText();
		// Hack to thread the EUR sign correctly
		$bhtml = str_replace(chr(0xE2) . chr(0x82) . chr(0xAC), chr(0xA4), $bhtml);
		$bhtml = utf8_decode($bhtml);

		// add the '"'. so links pointing to other wikis do not get erroneously converted.
		$bhtml = str_replace('"'.$wgScriptPath, '"'.$wgServer.$wgScriptPath, $bhtml);
		$bhtml = str_replace('/w/', $wgServer.'/w/', $bhtml);

		// Comment out previous two code lines if wiki is on the root folder and uncomment the following lines
		// global $wgUploadPath,$wgScript;
		// $bhtml = str_replace ($wgUploadPath, $wgServer.$wgUploadPath,$bhtml);
		// if (strlen($wgScriptPath)>0)
		//      $pathToTitle=$wgScriptPath;
		// else $pathToTitle=$wgScript;
		//      $bhtml = str_replace ("href=\"$pathToTitle", 'href="'.$wgServer.$pathToTitle, $bhtml);

		// removed heights of images
		$bhtml = preg_replace('/height="\d+"/', '', $bhtml);
		// set upper limit for width
		$bhtml = preg_replace('/width="(\d+)"/e', '"width=\"".($1> $wgPdfExportMaxImageWidth ? $wgPdfExportMaxImageWidth : $1)."\""', $bhtml);
		
		if ($wgPdfExportHttpsImages) {
			$bhtml = str_replace('img src=\"https:\/\/','img src=\"http:\/\/', $bhtml);
		}

		$css = $this->getPageCss( $page, $options );
		return "<html><head><title>" . utf8_decode($page) . "</title>$css</head><body>" . $bhtml . "</body></html>";
	}
	
	/**
	 * Get any CSS that needs to be added to the page for the PDF tool.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageCss($page, $options) {
		global $wgServer, $wgScriptPath;
		
		return '<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/skins/vector/main-ltr.css?207" type="text/css" media="screen" />'.
		'<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/skins/common/shared.css?207" type="text/css" media="screen" />'.
		'<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/index.php?title=MediaWiki:Common.css&amp;usemsgcache=yes&amp;ctype=text%2Fcss&amp;smaxage=18000&amp;action=raw&amp;maxage=18000" type="text/css" media="all" />';
	}
}