<?php
/**
 * PdfExport extension - Converts the current page to PDF and sends it to the browser
 *
 * @link https://www.mediawiki.org/wiki/Extension:PdfExport Documentation
 *
 * @file PdfExport.php
 * @defgroup PdfExport
 * @ingroup Extensions
 * @package MediaWiki
 * @author Thomas Hempel (Thempel)
 * @author Christian Neubauer (Cneubauer)
 * @author Andreas Hagmann (Ah)
 * @author Craig Oakes (W1BBoR)
 * @copyright (C) 2006 Thomas Hempel
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is part of a MediaWiki extension and is not a valid entry point.' );
}

# Register extension on Special:Version
$wgExtensionCredits['specialpage'][] = array(
	'name'           => 'PdfExport',
	'author'         => array(
				'Thomas Hempel',
				'Andreas Hagmann',
				'Christian Neubauer',
				'Craig Oakes',
				'...'
			 	),
	'version'        => '3.2.0',
	'descriptionmsg' => 'pdfexport-desc',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Pdf_Export'
);

$dir = dirname(__FILE__).'/';

# Internationalisation file
$wgMessagesDirs['PdfPrint'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PdfPrint'] = $dir.'PdfExport.i18n.php';
$wgExtensionMessagesFiles['PdfPrintAlias'] = $dir.'PdfExport.i18n.alias.php';
$wgSpecialPageGroups['PdfPrint'] = 'pagetools';

# Global variables
$wgPdfExportMaxImageWidth = 670; // The maximum width of images in pages
$wgPdfExportAttach = false; // True to return the PDF as a downloadable file
$wgPdfExportBackground = false; // Set this to the path to an image on the server to use as a background for the PDF. Only works with the HtmlDoc backend.
$wgPdfExportHttpsImages = false; // True to convert https image links to http in case the backend can't read images of https

# One of these backends needs to be configured:
$wgPdfExportPrincePath = false; // Path to the PrinceXML binary
$wgPdfExportPrincePhpInterface = false; // Path to the prince.php file from the prince website.

$wgPdfExportMwLibPath = false; // Path to the mw-render binary

$wgPdfExportMPdf = false; // Path to the main mPDF.php file

$wgPdfExportDomPdfConfigFile = false; // Path to the DomPdf config file

$wgPdfExportHtmlDocPath = false; // Path to the htmldoc binary

# Add the special page and setup autoloading of classes.
$wgSpecialPages['PdfPrint'] = 'SpecialPdf';
$wgAutoloadClasses['SpecialPdf'] = $dir.'PdfExport_body.php';
$wgAutoloadClasses['PdfConverterFactory'] = $dir.'converters/PdfConverterFactory.php';
$wgAutoloadClasses['PdfConverter'] = $dir.'converters/PdfConverter.php';
$wgAutoloadClasses['PrincePdfConverter'] = $dir.'converters/PrincePdfConverter.php';
$wgAutoloadClasses['MwLibPdfConverter'] = $dir.'converters/MwLibPdfConverter.php';
$wgAutoloadClasses['DomPdfConverter'] = $dir.'converters/DomPdfConverter.php';
$wgAutoloadClasses['MPdfConverter'] = $dir . 'converters/MPdfConverter.php';
$wgAutoloadClasses['HtmlDocPdfConverter'] = $dir.'converters/HtmlDocPdfConverter.php';

# Add a couple hooks to show the "Print as Pdf" links in the sidebar
$wgHooks['SkinTemplateBuildNavUrlsNav_urlsAfterPermalink'][] = 'wfSpecialPdfNav';
$wgHooks['SkinTemplateToolboxEnd'][] = 'wfSpecialPdfToolbox';

function wfSpecialPdfNav (&$skintemplate, &$nav_urls, &$oldid, &$revid) {
	$img_page = strpos($skintemplate->titletxt, "File:");
	// only display for an article, not an image
	if ($img_page > 0 || $img_page === false) {
		$nav_urls['pdfprint'] = array(
			'text' => wfMsg('pdf_print_link'),
			'href' => $skintemplate->makeSpecialUrl('PdfPrint', "page=".wfUrlencode("{$skintemplate->thispage}" ))
		);
	}
	return true;
}

function wfSpecialPdfToolbox (&$monobook) {
	if (isset($monobook->data['nav_urls']['pdfprint']))
		if ($monobook->data['nav_urls']['pdfprint']['href'] == '') {
?><li id="t-ispdf"><?php echo $monobook->msg('pdf_print_link'); ?></li><?php
		} else {
?><li id="t-pdf"><?php ?><a href="<?php echo htmlspecialchars( $monobook->data['nav_urls']['pdfprint']['href'] ) ?>"><?php
			echo $monobook->msg('pdf_print_link');
?></a><?php ?></li><?php
		}
	return true;
}
