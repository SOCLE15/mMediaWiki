<?php

Class GraphQuery {
	var $SMWengine;
	var $dbr;
	var $args;
	var $graph;
	var $lastInput;

	function __construct($args) {
		$this->SMWengine = new SMWSQLStore3;
		$this->dbr = wfGetDB( DB_SLAVE );
		$this->args = $args;
		$this->graph = array('links' => array(), 'nodes' => array());
	}

	function doQuery($titlearr) {
		global $wgSemanticGraphSettings;
		global $wgContLang;
		$p = $this->args['property'];

		if (!is_array($p)) $p = array($p);
		if (!is_array($titlearr)) $titlearr = array($titlearr);
		$this->lastInput[] = $titlearr;
		foreach ($p as $prop) {
			if ($prop == Title::makeTitle(SMW_NS_PROPERTY,$wgSemanticGraphSettings->dummyCategoryLinkProperty)->getPrefixedDBkey()) {
				$this->getPagesFromCategory($titlearr);
			} else if ($prop == Title::makeTitle(SMW_NS_PROPERTY,$wgSemanticGraphSettings->dummyWikiLinkProperty)->getPrefixedDBkey()) {
				$this->getPagesFromWikilinks($titlearr);
			} else {
			 	$this->getPagesFromSemantics( $titlearr , $prop);
			}
		}
	}
 
	function getResultNodes() {
		return $this->graph['nodes'];

	}

	function getLinks() {
		return $this->graph['links'];
	}

	function getPagesFromSemantics( $titles, $p ) {
		// this may be computationally expensive :-) and it is inefficient.
		foreach ((array) $titles as $t) {
			//$p = str_replace('Property:', '', $p);
			if ($this->args['direction'] != "incoming") {
				$thisTier = $this->SMWengine->getPropertyValues(Title::newFromDBkey($t), SMWPropertyValue::makeUserProperty($p));
				foreach ($thisTier as $row) {
					if ($row->getTypeID() == '_wpg') {
						//filter out results that are not of page type in the wiki & convert to array of titles
						if (!in_array(array($t, $p, $row->getTitle()->getPrefixedDBkey()), $this->graph['links'])) {
								if (!in_array(array($row->getTitle()->getPrefixedDBkey(), "(inv) ".$p, $t), $this->graph['links'])) {
										$this->graph['links'][] = array($t, $p, $row->getTitle()->getPrefixedDBkey());
								}
						}
						if (!in_array($row->getTitle()->getPrefixedDBkey(),$this->graph['nodes'])) {
							$this->graph['nodes'][] = $row->getTitle()->getPrefixedDBkey();
						}
					}
				}
			}
			if ($this->args['direction'] != "outgoing") {
				$thisIncoming = $this->SMWengine->getPropertySubjects(SMWPropertyValue::makeUserProperty($p), SMWWikiPageValue::makePageFromTitle(Title::newFromDBkey($t)));
				foreach ($thisIncoming as $row) {
					if (!in_array(array($row->getTitle()->getPrefixedDBkey(), $p, $t), $this->graph['links'])) {
						if (!in_array(array($t, "(inv) ".$p, $row->getTitle()->getPrefixedDBkey()), $this->graph['links'])) {
							$this->graph['links'][] = array($t, "(inv) ".$p, $row->getTitle()->getPrefixedDBkey());
						}
					}
					if (!in_array($row->getTitle()->getPrefixedDBkey(),$this->graph['nodes'])) {
						$this->graph['nodes'][] = $row->getTitle()->getPrefixedDBkey();
					}
				}
			}
		}
	}

	function getPagesFromCategory( $titles ) {
		//hacked from the main export page functions.
		//the function now returns an array of title objects and link assertions
		//this is a hack for backward compatability with MW 1.12
		global $wgSemanticGraphSettings;
		global $wgContLang;
		$name=array();
		foreach ((array) $titles as $t) {
			$name[] = Title::newFromDBkey($t)->getArticleID();
		}
		$names = implode(', ',$name);
		list( $page, $categorylinks ) = $this->dbr->tableNamesN( 'page', 'categorylinks' );
		/*
		 * EXAMPLE QUERY
		 * Select a.page_namespace as src_ns, a.page_title as src_ti, b.page_namespace as dest_ns, b.page_title as dest_ti
		 * from wikidb.page a, wikidb.page b, wikidb.categorylinks c where
		 * b.page_id=c.cl_from and a.page_title=c.cl_to and a.page_title in ('Cities', 'InternalDocument', 'Top');
		 */

		if ($names != '') {
			$sql = "Select a.page_namespace as src_ns, a.page_title as src_ti, b.page_namespace as dest_ns, b.page_title as dest_ti "
			."from $page a, $page b, $categorylinks c where "
			."b.page_id=c.cl_from and a.page_title=c.cl_to and a.page_id in ($names);";
			$res = $this->dbr->query( $sql, 'getPagesFromCategory' );

			while ( $row = $this->dbr->fetchObject( $res ) ) {
				$src = Title::makeTitle($row->src_ns, $row->src_ti)->getPrefixedDBkey();
				$dest = Title::makeTitle($row->dest_ns, $row->dest_ti)->getPrefixedDBkey();
				$this->graph['links'][] = array ($src, Title::makeTitle(SMW_NS_PROPERTY,$wgSemanticGraphSettings->dummyCategoryLinkProperty)->getPrefixedDBkey(), $dest);
				if (!in_array($dest,$this->graph['nodes'])) {
					$this->graph['nodes'][] = $dest;
				}
			}

			$this->dbr->freeResult($res);
		}
	}

	function getPagesFromWikilinks ($titles) {
		/*
		 * EXAMPLE QUERY:
		 * Select a.page_namespace, a.page_title, "has link to" as property, b.pl_namespace, b.pl_title
		 * from wikidb.page a, wikidb.pagelinks b
		 * where a.page_id=b.pl_from and b.pl_namespace not in ('102')
		 * and a.page_title in ('Cities', 'InternalDocument', 'Top', 'Tualatin');
		 */
		global $wgSemanticGraphSettings;
		global $wgContLang;
		$name=array();
		foreach ((array) $titles as $t) {
			$name[] = Title::newFromDBkey($t)->getArticleID();
		}
		$names = implode(', ',$name);
		list( $page, $pagelinks ) = $this->dbr->tableNamesN( 'page', 'pagelinks' );

		if ($names != '') {
			$sql = "Select a.page_namespace as src_ns, a.page_title as src_ti, b.pl_namespace as dest_ns, b.pl_title as dest_ti "
			." from $page a, $pagelinks b "
			." where a.page_id=b.pl_from"
			." and a.page_id in ($names)"
			." and b.pl_namespace not in (".SMW_NS_PROPERTY.",".SF_NS_FORM.") ;";
			$res = $this->dbr->query( $sql, 'getPagesFromWikiLinks' );
			while ( $row = $this->dbr->fetchObject( $res ) ) {
				$src = Title::makeTitle($row->src_ns, $row->src_ti)->getPrefixedDBkey();
				$dest = Title::makeTitle($row->dest_ns, $row->dest_ti)->getPrefixedDBkey();
				$this->graph['links'][] = array ($src, Title::makeTitle(SMW_NS_PROPERTY,$wgSemanticGraphSettings->dummyWikiLinkProperty)->getDBkey(), $dest);
				if (!in_array($dest,$this->graph['nodes'])) {
					$this->graph['nodes'][] = $dest;
				}
			}

			$this->dbr->freeResult($res);
		}
	}

}

?>
