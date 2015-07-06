<?php
/**
 * A special page to generate a PDF version of a page. The class will use PrincePDF, DomPdf, or HTMLDoc in that order.
 *
 * @file PdfExport_body.php
 * @ingroup PdfExport
 * @author Thomas Hempel (Thempel)
 * @author Christian Neubauer (Cneubauer)
 * @author Andreas Hagmann (Ah)
 * @author Craig Oakes (W1BBoR)
 * @copyright (C) 2006 Thomas Hempel
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class SpecialPdf extends SpecialPage {
	public $title;
	public $article;
	public $html;
	public $parserOptions;
	public $bhtml;

	/**
	 * A converter to actually do the Pdf generation.
	 */
	public $converter;

	/**
	 * Setup the special page. Tries to detect a PDF generation tool to use. Prefers in order: PrincePDF, HtmlDoc, DomPdf.
	 */
	function SpecialPdf () {
		parent::__construct('PdfPrint');
		$this->converter = PdfConverterFactory::getPdfConverter();
	}

	/**
	 * Generate a PDF version of the selected page.
	 *
	 * @param string $par The page name to convert. If this is not set, then the 'page' or 'pagel' parameters of the URL should be.
	 */
	public function execute ($par) {
		global $wgRequest;
		global $wgOut;

		if ($this->converter == null) {
			$wgOut->setPageTitle(wfMsg('pdfprint_error'));
			$wgOut->addHtml('<span class="errorbox">'.wfMsg('pdf_export_no_converter_found').'</span>');
			return;
		}

		$pages = null;
		$dopdf = true;

		if ($wgRequest->wasPosted()) {
			// Find a list of pages
			$pagel = $wgRequest->getText('pagel');
			$pages = array_filter(explode("\n", $pagel), 'wfFilterPageList');
			if (count($pages) == 0) {
				$dopdf = false;
			}

			// Use user/special page supplied options
			$options = array(
				'filename' => $wgRequest->getText ('filename'),
				'size'     => $wgRequest->getText ('Size', 'Letter'),
				'fontface' => $wgRequest->getText ('fontface'),
				'fontsize' => $wgRequest->getText ('fontsize'),
				'margintop' => $wgRequest->getText ('margintop'),
				'marginsides' => $wgRequest->getText ('marginsides'),
				'marginbottom' => $wgRequest->getText ('marginbottom'),
				'orientation' => $wgRequest->getVal ('orientation'),
				'pass_protect' => $wgRequest->getVal ('pass_protect'),
				'owner_pass' => $wgRequest->getVal ('owner_pass'),
				'user_pass' => $wgRequest->getVal ('user_pass'),
				'perm_print' => $wgRequest->getVal ('perm_print'),
				'perm_modify' => $wgRequest->getVal ('perm_modify'),
				'perm_copy' => $wgRequest->getVal ('perm_copy'),
				'perm_annotate' => $wgRequest->getVal ('perm_annotate')
			);
		} else {
			$page = isset($par) ? $par : $wgRequest->getText('page');
			if ($page == '') {
				$dopdf = false;
			} else {
				$pages = array($page);
			}

			// Use some default options
			$options = array(
				'page'          => $page,
				'filename'      => $page,
				'fontface'      => 'times',
				'fontsize'      => '12',
				'size'          => 'letter',
				'margintop'     => '20',
				'marginsides'   => '20',
				'marginbottom'  => '20',
				'orientation'   => 'portrait',
				'pass_protect'  => 'no',
				'owner_pass'    => '',
				'user_pass'     => '',
				'perm_print'    => 'yes',
				'perm_modify'   => 'yes',
				'perm_copy'     => 'yes',
				'perm_annotate' => 'yes'
			);
		}

		if ($dopdf) {
			$wgOut->setPrintable();
			$this->converter->initialize($options);
			$this->converter->outputPdf($pages, $options);
			return;
		}

		$this->outputForm();
	}

	/**
	 * Generate a form for the user to create a PDF document.
	 */
	protected function outputForm () {
		global $wgOut;

		// TODO add a font face and font size input

		$self = SpecialPage::getTitleFor('PdfPrint');
		$wgOut->setPageTitle(wfMsg('pdfprint'));
		$wgOut->addHtml(wfMsgExt('pdf_print_text', 'parse'));

		$form = Xml::openElement('form', array('method' => 'post', 'action' => $self->getLocalUrl('action=submit')));
		$form .= Xml::openElement('textarea', array('name' => 'pagel', 'value' => '', 'style' => 'width:30em;'));
		$form .= Xml::closeElement('textarea');
		$form .= '<br />';
		$form .= '<br />'.wfMsg('pdf_size').": ";
		$form .= Xml::listDropDown('Size', wfMsg('pdf_size_options'), '', wfMsg('pdf_size_default'));
		$form .= Xml::radioLabel(wfMsg('pdf_portrait'), 'orientation', 'portrait', 'portrait', true);
		$form .= Xml::radioLabel(wfMsg('pdf_landscape'), 'orientation', 'landscape', 'landscape', false);
		$form .= '<br />';
		$form .= wfMsg('pdf_margins_label').":";
		$form .= '<ul>';
		$form .= ' <li>';
		$form .= Xml::openElement('input', array('type' => 'text', 'name' => 'margintop', 'value' => '20'));
		$form .= Xml::closeElement('input');
		$form .= ' ('.wfMsg('pdf_margins_label_top').')';
		$form .= ' </li>';
		$form .= ' <li>';
		$form .= Xml::openElement('input', array('type' => 'text', 'name' => 'marginsides', 'value' => '20'));
		$form .= Xml::closeElement('input');
		$form .= ' ('.wfMsg('pdf_margins_label_sides').')';
		$form .= ' </li>';
		$form .= ' <li>';
		$form .= Xml::openElement('input', array('type' => 'text', 'name' => 'marginbottom', 'value' => '20'));
		$form .= Xml::closeElement('input');
		$form .= ' ('.wfMsg('pdf_margins_label_bottom').')';
		$form .= ' </li>';
		$form .= '</ul>';
		$form .= '<br />';
		$form .= wfMsg('pdf_pass_protect_label').":";
		$form .= Xml::radioLabel(wfMsg('pdf_pass_protect_yes'), 'pass_protect', 'yes', 'pass_protect', false);
		$form .= Xml::radioLabel(wfMsg('pdf_pass_protect_no'), 'pass_protect', 'no', 'pass_protect', true);
		$form .= '<br />';
		$form .= wfMsg('pdf_owner_pass_label').":";
		$form .= Xml::openElement('input', array('type' => 'password', 'name' => 'owner_pass', 'value' => '', 'size' => '50'));
		$form .= Xml::closeElement('input');
		$form .= '<br />';
		$form .= wfMsg('pdf_user_pass_label').":";
		$form .= Xml::openElement('input', array('type' => 'password', 'name' => 'user_pass', 'value' => '', 'size' => '50'));
		$form .= Xml::closeElement('input');
		$form .= '<br />';
		$form .= wfMsg('pdf_perm_print_label').":";
		$form .= Xml::radioLabel(wfMsg('pdf_perm_print_yes'), 'perm_print', 'yes', 'perm_print', true);
		$form .= Xml::radioLabel(wfMsg('pdf_perm_print_no'), 'perm_print', 'no', 'perm_print', false);
		$form .= '<br />';
		$form .= wfMsg('pdf_perm_modify_label').":";
		$form .= Xml::radioLabel(wfMsg('pdf_perm_modify_yes'), 'perm_modify', 'yes', 'perm_modify', true);
		$form .= Xml::radioLabel(wfMsg('pdf_perm_modify_no'), 'perm_modify', 'no', 'perm_modify', false);
		$form .= '<br />';
		$form .= wfMsg('pdf_perm_copy_label').":";
		$form .= Xml::radioLabel(wfMsg('pdf_perm_copy_yes'), 'perm_copy', 'yes', 'perm_copy', true);
		$form .= Xml::radioLabel(wfMsg('pdf_perm_copy_no'), 'perm_copy', 'no', 'perm_copy', false);
		$form .= '<br />';
		$form .= wfMsg('pdf_perm_annotate_label').":";
		$form .= Xml::radioLabel(wfMsg('pdf_perm_annotate_yes'), 'perm_annotate', 'yes', 'perm_annotate', true);
		$form .= Xml::radioLabel(wfMsg('pdf_perm_annotate_no'), 'perm_annotate', 'no', 'perm_annotate', false);
		$form .= '<br />';
		$form .= '<br />';
		// input field for name of PDF
		$form .= wfMsg('pdf_filename').": ";
		$form .= Xml::openElement('input', array('type' => 'text', 'name' => 'filename', 'value' => wfMsg('pdf_filename')));
		$form .= Xml::closeElement('input');
		$form .= '<br /><br />';
		$form .= Xml::submitButton(wfMsg('pdf_submit'));
		$form .= Xml::closeElement('form');
		$wgOut->addHtml($form);
	}
}
