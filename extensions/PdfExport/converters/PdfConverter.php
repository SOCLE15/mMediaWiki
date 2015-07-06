<?php
if (!defined('MEDIAWIKI'))
	die();

/**
 * A base class for Pdf conversion tools.
 * 
 * @author Christian Neubauer <cneubauer@mitre.org>
 */
abstract class PdfConverter {
	/**
	 * Sets up any necessary command line options. 
	 * @param Array $options An array of options.
	 */
	abstract function initialize(&$options);
	
	/**
	 * Output the PDF document.
	 * @param Array $pages An array of page names.
	 * @param Array $options An array of options.
	 */
	abstract function outputPdf($pages, $options);
	
	/**
	 * Get the HTML for a page. This function should filter out any code that the converter can't handle like <script> tags.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	abstract function getPageHtml($page, $options);
	
	/**
	 * Get any CSS that needs to be added to the page for the PDF tool.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	abstract function getPageCss($page, $options);
}

/**
 * Filter out blank pages.
 * 
 * TODO Should be able to do this without using this function.
 * @param String $page A page name
 */
function wfFilterPageList( $page ) {
	return $page !== '' && $page !== null;
}