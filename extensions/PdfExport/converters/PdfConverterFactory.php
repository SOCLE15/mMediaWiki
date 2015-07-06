<?php
if ( !defined( 'MEDIAWIKI' ) )
	die();
	
/**
 * A factory to create Pdf documents. This factory creates a Pdf converter depending on which Pdf packages are installed on the server. The factory prefers in order: PrinceXML, DomPdf, HtmlDoc.
 */
class PdfConverterFactory {
	/**
	 * Get a Pdf converting tool. The factory prefers PrinceXML, then HtmlDoc, then DomPdf.
	 * @return A PdfConverter or null if none are installed.
	 */
	public static function getPdfConverter() {
		global $wgPdfExportPrincePath, $wgPdfExportMwLibPath, $wgPdfExportHtmlDocPath, $wgPdfExportDomPdfConfigFile, $wgPdfExportMPdf;
		if ($wgPdfExportPrincePath) {
			return new PrincePdfConverter();
		}
		
		if ($wgPdfExportMwLibPath) {
			return new MwLibPdfConverter();
		}

		if ($wgPdfExportDomPdfConfigFile && file_exists($wgPdfExportDomPdfConfigFile)) {
			return new DomPdfConverter();
		}

		if ($wgPdfExportMPdf && file_exists($wgPdfExportMPdf) ) {
			return new MPdfConverter();
		}

		if ($wgPdfExportHtmlDocPath) {
			$output = array();
			$return_val = null;
			exec($wgPdfExportHtmlDocPath.' --version', $output, $return_val);
			if ($return_val === 0) {
				return new HtmlDocPdfConverter();
			}
		}
		
		// @TODO Throw an exception?
		return null;
	}
}
