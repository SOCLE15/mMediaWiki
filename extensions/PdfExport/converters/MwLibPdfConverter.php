<?php
if (!defined('MEDIAWIKI'))
	die();

/**
 * A mwlib based conversion backend.
 * 
 * Installation:
 * You need to install mwlib and the mwlib.rl library. These can be found here: https://github.com/pediapress or they can be installed with python easy_install or pip.
 * 
 * @author Christian Neubauer
 */
class MwLibPdfConverter extends PdfConverter {
	/**
	 * Does nothing. No init required.
	 * @param Array $options An array of options
	 */
	function initialize(&$options) {}
	
	/**
	 * Ouput the Pdf.
	 * @param Array $pages An array of page names.
	 * @param Array $options An array of options.
	 */
	function outputPdf($pages, $options) {
		global $wgPdfExportMwLibPath;
		global $wgPdfExportAttach;
		global $wgOut, $wgServer, $wgScriptPath;

		// Since mwlib operates on raw wikitext, it can only handle one file at a time.
		$page = $pages[0];
		
		# Write the content type to the client...
		$wgOut->disable();
		header("Content-Type: application/pdf");
		if ($wgPdfExportAttach) {
			header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $options['filename']));
		} else {
			header(sprintf('Content-Disposition: inline; filename="%s.pdf"', $options['filename']));
		}
		
		// TODO gather output
		$tmpFile = tempnam(sys_get_temp_dir(), 'mw-pdf-');
		$output = array();
		exec($wgPdfExportMwLibPath.' --config '.$wgServer.$wgScriptPath.'/ --output '.$tmpFile.' --writer rl '.escapeshellarg($page), $output);
		
		readfile( $tmpFile );
		unlink( $tmpFile );
	}
	
	/**
	 * Get the HTML for a page. This function should filter out any code that the converter can't handle like <script> tags.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageHtml($page, $options) {
		return '';
	}
	
	/**
	 * Get any CSS that needs to be added to the page for the PDF tool.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageCss($page, $options) {
		return '';
	}
}