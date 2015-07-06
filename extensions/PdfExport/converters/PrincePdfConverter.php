<?php
if (!defined('MEDIAWIKI'))
	die();

/**
 * A PrinceXML based conversion backend.
 * 
 * Installation:
 * Prince can be downloaded from here: http://www.princexml.com/download/.
 * You also need the PHP wrapper from here: http://www.princexml.com/download/wrappers/.
 * 
 * @author Christian Neubauer
 *
 */
class PrincePdfConverter extends PdfConverter {
	/**
	 * Setup Prince. Includes the PHP wrapper.
	 * @param Array $options An array of options
	 */
	function initialize(&$options) {
		global $wgPdfExportPrincePhpInterface;
		require_once( $wgPdfExportPrincePhpInterface );
	}
	
	/**
	 * Ouput the Pdf.
	 * @param Array $pages An array of page names.
	 * @param Array $options An array of options.
	 */
	function outputPdf($pages, $options) {
		global $wgPdfExportPrincePath, $wgRequest, $wgPdfExportAttach, $wgOut;

		// TODO make this handle multiple pages
		foreach( $pages as $page ) {
			$html = $this->getPageHtml($page, $options);
		}
		
		// TODO handle this error somehow
		if (!$html) {
			return null;
		}

		// check to make sure the path is correct
		if ( !file_exists($wgPdfExportPrincePath ) ) {
			$this->setPageTitle( wfMsg( 'pdfprint_error' ) );
			$wgOut->addWikiMsg( 'pdf_prince_error_not_installed' );
			wfDebugLog('pdf', "Princexml is not installed correctly.");
			return null;
		}
		
		$error = false;
		$prince = new Prince($wgPdfExportPrincePath );
		
		// TODO Make this configurable
		$logfile = getcwd().'/logs/prince_pdf.log';
		$prince->setLog($logfile);
		
		if ($options['pass_protect'] == 'yes') {
			$prince->setEncryptInfo(128, $options['user_pass'], $options['owner_pass'], $options['perm_print'], $options['perm_modify'], $options['perm_copy'], $options['perm_annotate']);
		}

		# Capture output so we can make sure it worked before trying to feed the user the PDF.
		ob_start();
		$result = $prince->convert_string_to_passthru($html);
		$pdf = ob_get_contents();
		ob_end_clean();

		if (!strlen($result)) {
			$this->setPageTitle( wfMsg( 'pdfprint_error' ) );
			$wgOut->addWikiMsg( 'pdf_prince_error' );
			wfDebugLog('pdf', "Generating PDF failed, PrinceXML was not able to create the PDF. See prince_pdf.log for details.");
		} else {
			header('Content-Transfer-Encoding: binary');
			header("Content-Type: application/pdf");
			if ($wgPdfExportAttach || $wgRequest->wasPosted()) { # if posted dont allow to open in same window, will break if bad password is entered
				header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $options['filename']));
			} else {
				header(sprintf('Content-Disposition: inline; filename="%s.pdf"', $options['filename']));
			}
			$wgOut->disable(); # prevent any further output
			echo $pdf;
		}
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
		global $wgRawHtml;
		global $wgPdfExportMaxImageWidth;

		$title = Title::newFromText( $page );
		if( is_null( $title ) ) {
			// @TODO throw error
			return null;
		}
		
		if( !$title->userCan( 'read' ) ) {
			// @TODO throw error
			return null;
		}
		
		$article = new Article($title);
		$parserOptions = ParserOptions::newFromUser( $wgUser );
		$parserOptions->setEditSection( false );
		$parserOptions->setTidy(true);
		$parserOutput = $wgParser->parse( $article->preSaveTransform( "__NOTOC__\n\n".$article->getContent() ) ."\n\n", $title, $parserOptions );

		$bhtml = $parserOutput->getText();
		// Hack to thread the EUR sign correctly
		$bhtml = str_replace(chr(0xE2) . chr(0x82) . chr(0xAC), chr(0xA4), $bhtml);
		$bhtml = utf8_decode($bhtml);

		// add the '"'. so links pointing to other wikis do not get erroneously converted.
		$bhtml = str_replace ('"'.$wgScriptPath, '"'.$wgServer . $wgScriptPath, $bhtml);
		$bhtml = str_replace ('/w/',$wgServer . '/w/', $bhtml);
		
		// remove scripts
		$bhtml = preg_replace('/<script[^>]*?>.*?><\/script>/is', '$1', $bhtml);

		// removed heights of images
		$bhtml = preg_replace ('/height="\d+"/', '', $bhtml);
		// set upper limit for width
		$bhtml = preg_replace ('/width="(\d+)"/e', '"width=\"".($1> $wgPdfExportMaxImageWidth ? $wgPdfExportMaxImageWidth : $1)."\""', $bhtml);
 
		if ($wgPdfExportHttpsImages) {
			$bhtml = str_replace('img src=\"https:\/\/','img src=\"http:\/\/', $bhtml);
		}
		
		$css = $this->getPageCss( $page, $options );
		$page = utf8_decode( $page );
		$html = <<<EOD
<?xml version="1.0" encoding="iso-8859-1"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml" lang="en">
	<head>
		<title>$page</title>
		$css
	</head>
	<body>
		$bhtml
	</body>
</html>
EOD;

		return $html;
	}
	
	/**
	 * Get any CSS that needs to be added to the page for the PDF tool.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageCss($page, $options) {
		global $wgServer, $wgScriptPath;

		$top_css = <<<EOD
			.hideForPDF { display: none; }
			body {font-family: sans-serif;font-weight:normal;font-style:normal;font-size:10.5pt; }
			heading { page-break-after: avoid; }
			table { page-break-inside: avoid; }
			hr { margin:7px 0 7px 0; height:0px; padding:0px; border:none; border-top:2px solid black; }\r\n
EOD;

		$size = $options['size'];
		$landscape = $options['orientation'];
		$options['margintop'] < 5 ? $margintop = 5 : $margintop = $options['margintop'];
		$options['marginbottom'] < 5 ? $marginbottom = 5 : $marginbottom = $options['marginbottom'];
		$marginsides = $options['marginsides'];
		// $bottom_css represents specific CSS for PDF conversion only, will not effect the html
		// may need to change left & right margins to 2.54cm if converting PDF to a Word Document
		
		// TODO fix font family to use value in options
		$bottom_css = <<<EOD
			@page {
				size: $size $landscape;
				margin: {$margintop}mm {$marginsides}mm {$marginbottom}mm {$marginsides}mm;
				@bottom-right {
					content: counter(page);
					font-family:VERDANA;
				}
			}
EOD;
		return '<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/skins/vector/main-ltr.css?207" type="text/css" media="screen" />'.
		'<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/skins/common/shared.css?207" type="text/css" media="screen" />'.
		'<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/index.php?title=MediaWiki:Common.css&amp;usemsgcache=yes&amp;ctype=text%2Fcss&amp;smaxage=18000&amp;action=raw&amp;maxage=18000" type="text/css" media="all" />'.
		'<style>' . $top_css . $bottom_css . '</style>';
	}
}
