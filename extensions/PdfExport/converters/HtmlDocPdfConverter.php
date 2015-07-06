<?php
if (!defined('MEDIAWIKI'))
	die();

/**
 * An HTMLDoc based conversion backend.
 * 
 * Installation:
 * HTMLDoc can be downloaded from here: http://www.htmldoc.org/ or on an Ubuntu based system you can do
 * apt-get install htmldoc. Once installed, set $wgPdfExportHtmlDocPath equal to the full path to the
 * htmldoc binary.
 * 
 * @author Thomas Hempel
 * @author Christian Neubauer
 */
class HtmlDocPdfConverter extends PdfConverter {
	/**
	 * Sets up any necessary command line options. 
	 * @param Array $options An array of options.
	 */
	function initialize (&$options) {
		// Fix the orientation
		if ($options['orientation'] == 'landscape') {
			$options['orientation'] = " --landscape --browserwidth 1200 ";
		} else {
			$options['orientation'] = " --portrait ";
		}
		
		// Setup permissions
		$perms = array();
		$options['permissions'] = '';
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
				$options['permissions'] .= '--permissions all --encryption';
			} else {
				$options['permissions'] .= '--permissions ' . implode( ',', $perms ) . ' --encryption';
			}
			
			if( $options['owner_pass'] != '' ) {
				$options['permissions'] .= ' --owner-password ' . $options['owner_pass'];
			}
			if( $options['user_pass'] != '' ) {
				$options['permissions'] .= ' --user-password ' . $options['user_pass'];
			}
		}
	}

	/**
	 * Output the PDF document.
	 * @param Array $pages An array of page names.
	 * @param Array $options An array of options.
	 */
	function outputPdf ($pages, $options) {
		global $wgPdfExportHtmlDocPath;
		global $wgPdfExportAttach;
		global $wgPdfExportBackground;
		global $wgOut;

		$pagestring = "";

		$fontface = $options['fontface'];
		$fontsize = $options['fontsize'];
		$margintop = $options['margintop'];
		$marginsides = $options['marginsides'];
		$marginbottom = $options['marginbottom'];
		$permissions = $options['permissions'];
		$size = $options['size'];
		$orientation = $options['orientation'];

		foreach ($pages as $pg) {
			$pagestring .= $this->getPageHtml($pg, $options);
		}
		if ($pagestring == '') {
			// TODO localize these messages
			$wgOut->addHtml('Failed to generate Pdf with HTMLDoc.  Please notify the system administrators and check the logs');
			wfDebugLog('pdf', 'Failed to generate Pdf. Page string was blank.');
			return;
		}
		$wgOut->disable();
		putenv("HTMLDOC_NOCGI=1");

		# Write the content type to the client...
		header("Content-Type: application/pdf");

		if ($wgPdfExportAttach) {
			header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $options['filename']));
		} else {
			header(sprintf('Content-Disposition: inline; filename="%s.pdf"', $options['filename']));
		}

		$pipes = null;
		$htmldoc_descriptorspec = array(
			// stdin is a pipe that the child will read from
			0 => array("pipe", "r"),
			// stdout is a pipe that the child will write to
			1 => array("pipe", "w")
		);
		# Run HTMLDOC to provide the PDF file to the user...
		if( $wgPdfExportBackground ) {
			$htmldoc_process = proc_open($wgPdfExportHtmlDocPath." -t pdf14 --charset iso-8859-15 --color --quiet --jpeg --bodyfont ".$fontface." --textfont ".$fontface." --headingfont ".$fontface." --fontsize ".$fontsize." --bodyimage ".$wgPdfExportBackground." --top ".$margintop." --left ".$marginsides." --right ".$marginsides." --bottom ".$marginbottom." --permissions ".$permissions." --size ".$size." ".$orientation."--webpage -", $htmldoc_descriptorspec, $pipes);
		} else {
			$htmldoc_process = proc_open($wgPdfExportHtmlDocPath." -t pdf14 --charset iso-8859-15 --color --quiet --jpeg --bodyfont ".$fontface." --textfont ".$fontface." --headingfont ".$fontface." --fontsize ".$fontsize." --top ".$margintop." --left ".$marginsides." --right ".$marginsides." --bottom ".$marginbottom." --permissions ".$permissions." --size ".$size." ".$orientation."--webpage -", $htmldoc_descriptorspec, $pipes);
		}

		fwrite($pipes[0], $pagestring);
		fclose($pipes[0]);

		fpassthru($pipes[1]);
		fclose($pipes[1]);

		$returnStatus = proc_close($htmldoc_process);
		if ($returnStatus == 1) {
			wfDebugLog('pdf', "Generating PDF failed, check path to HTMLDoc, return status was: ".$returnStatus);
		}
		flush();
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

		$title = Title::newFromText($page);
		if (is_null($title) || !$title->userCan( 'read' )) {
			return null;
		}
		$article = new Article($title);
		$parserOptions = ParserOptions::newFromUser($wgUser);
		$parserOptions->setEditSection(false);
		$parserOptions->setTidy(true);
		$parserOutput = $wgParser->parse($article->preSaveTransform("__NOTOC__\n\n".$article->getContent())."\n\n", $title, $parserOptions);

		$bhtml = $parserOutput->getText();
		// XXX Hack to thread the EUR sign correctly
		$bhtml = str_replace(chr(0xE2).chr(0x82).chr(0xAC), chr(0xA4), $bhtml);
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
			$bhtml = str_replace('img src=\"https:\/\/', 'img src=\"http:\/\/', $bhtml);
		}

		$css = $this->getPageCss( $page, $options );
		return "<html><head><title>".utf8_decode($page)."</title>$css</head><body>".$bhtml."</body></html>";
	}

	/**
	 * Get any CSS that needs to be added to the page for the PDF tool.
	 * @param String $page The page name
	 * @param Array $options An array of options.
	 */
	function getPageCss ($page, $options) {}
}
