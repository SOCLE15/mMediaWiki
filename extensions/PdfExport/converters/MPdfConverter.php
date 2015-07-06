<?php
if (!defined('MEDIAWIKI'))
	die();

/**
 * A mPDF based conversion backend.
 *
 * Installation:
 * MPdf can be downloaded from here: http://mpdf1.com/manual/index.php?wpage=1. Unzip the code into your
 * extension directory and set $wgPdfExportMPdf equal to the full path to the
 * main mpdf.php file.
 *
 * @author Christian Neubauer
 */
class MPdfConverter extends PdfConverter {
	/**
	 * Sets up any necessary command line options.
	 * @param Array $options An array of options.
	 */
	function initialize (&$options) {
		global $wgPdfExportMPdf;
		// Load the configuration file. This loads the whole mPdf framework
		require_once($wgPdfExportMPdf);
	}

	/**
	 * Output the PDF document.
	 * @param Array $pages An array of page names.
	 * @param Array $options An array of options.
	 */
	function outputPdf ($pages, $options) {
		global $wgUser, $wgRequest;
		global $wgOut, $IP, $wgPdfExportAttach;

		$pagestring = '';
		foreach ($pages as $pg) {
			$pagestring .= $this->getPageHtml($pg, $options);
		}

		$html = $pagestring;

		# TODO handle page size and orientation, etc.

		# Capture output so we can make sure it worked before trying to feed the user the PDF.
		ob_start();

		$mpdf = new mPDF();
		$mpdf->WriteHTML($html);
	
		if( $options['pass_protect'] == 'yes' ) {
			if( $options['perm_print'] == 'yes' ) {
				$perms[] = 'print';
			}
			if( $options['perm_modify'] == 'yes' ) {
				$perms[] = 'modify';
			}
			if( $options['perm_copy'] == 'yes' ) {
				$perms[] = 'copy';
			}
			if( $options['perm_annotate'] == 'yes' ) {
				$perms[] = 'annot-forms';
			}
			if( count( $perms ) == 0 ) {
				$mpdf->SetProtection(array(), $options['user_pass'], $options['owner_pass']);
			} else {
				$mpdf->SetProtection($perms, $options['user_pass'], $options['owner_pass']);
			}
		}
		$mpdf->Output();

		$pdf = ob_get_contents();
		ob_end_clean();

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

	/**
	 * Get the HTML for a page. This function should filter out any code that the converter can't handle like <script> tags.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageHtml ($page, $options) {
		global $wgUser;
		global $wgParser;
		global $wgScriptPath;
		global $wgServer;
		global $wgPdfExportHttpsImages;
		global $wgPdfExportMaxImageWidth;

		$title = Title::newFromText($page);
		if (is_null($title) || !$title->userCan( 'read' ))
			return null;
		$article = new Article($title);
		$parserOptions = ParserOptions::newFromUser($wgUser);
		$parserOptions->setEditSection(false);
		$parserOptions->setTidy(true);
		$parserOutput = $wgParser->parse($article->preSaveTransform("__NOTOC__\n\n".$article->getContent())."\n\n", $title, $parserOptions);

		$bhtml = $parserOutput->getText();
		# Note: we don't want to utf8_decode here. mPDF handles UFT-8 characters.
		#$bhtml = utf8_decode($bhtml);

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
			$bhtml = str_replace('img src=\"https:\/\/', 'img src=\"http:\/\/', $bhtml);
		}

		$css = $this->getPageCss($page, $options);

		$html = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml" lang="en">
	<head>
		<title>$page</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
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
		
		return '<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/skins/vector/main-ltr.css?207" type="text/css" media="screen" />'.
		'<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/skins/common/shared.css?207" type="text/css" media="screen" />'.
		'<link rel="stylesheet" href="'.$wgServer.$wgScriptPath.'/index.php?title=MediaWiki:Common.css&amp;usemsgcache=yes&amp;ctype=text%2Fcss&amp;smaxage=18000&amp;action=raw&amp;maxage=18000" type="text/css" media="all" />';
	}
}
