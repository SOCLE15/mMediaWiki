<?php
Class networkgraph {
	var $args;
	var $gq;
	var $nodes;
	var $links;

	function __construct($args) {
		global $wgSemanticGraphSettings;
		$this->gq = new GraphQuery($args);
		$this->args = $args;
	}

	function buildFromWiki() {
		$this->nodes = $this->args['resource']; // this can be singelton or array
		if (!is_array($this->nodes)) $this->nodes = array($this->nodes);
		$n = $this->nodes;

		$this->links = array();
		for ($i=0; $i < $this->args['depth']; $i++) {
			$this->gq->doQuery($n);
			$n = array_diff($this->gq->getResultNodes(), $this->nodes);
			$this->nodes = array_merge($this->nodes, $this->gq->getResultNodes());
			$this->links = array_merge($this->links, $this->gq->getLinks());
		}
		$this->links = $this->arrayUnique($this->links);
		$this->nodes = array_unique($this->nodes);
	}

	function getSubGraph($renderer) {
		$g='';
		foreach ((array) $this->nodes as $n) {
			$this->args['child'] = Title::newFromDBkey($n);
			if ($this->args['child']->exists()) {
				$g .= $renderer->enter('nodeExist', $this->args);
			} else {
				$g .= $renderer->enter('nodeNotExist', $this->args);
			}
		}
		foreach ((array) $this->links as $l) {
			$this->args['parent'] = Title::newFromDBkey($l[0]);
			//TODO: do somthign about property types / colours here
			$this->args['child'] = Title::newFromDBkey($l[2]);
			$g .= $renderer->enter('link', $this->args);
		}
		return $g;
	}

	function getChildNodes($title) {
		$outarr = array();
		foreach ($this->links as $l) {
			if ($l[0] == $title) {
				$outarr[] = $l[2];
			}
		}
		return $outarr;
	}

	//private functions
	
	function arrayUnique($myArray) {
		if(!is_array($myArray))
		return $myArray;
		foreach ($myArray as &$myvalue){
			$myvalue=implode("<",$myvalue);
		}
		$myArray=array_unique($myArray);
		foreach ($myArray as &$myvalue){
			$myvalue=explode("<",$myvalue);
		}
		return $myArray;
	}


}

Class freemindmap {
	var $args;
	var $ng;
	var $map;
	var $root;
	var $renderer;

	function __construct($args) {
		global $wgSemanticGraphSettings;
		$this->args = $args;
		$this->map = new DOMDocument();
		$this->ng = new networkgraph($args);
		$this->renderer = new renderer('mm');

	}

	function buildFromWiki() {
		$this->root = $this->map->createElement('map');
		$this->root->setAttribute('version',"0.8.1");
		$this->map->appendChild($this->root);
		$level = 0;
		$this->ng->buildFromWiki();
		$title = $this->args['resource']; // this should already be validated to be a singleton
		$this->recursiveBuildMap($this->root, $title, $level);
	}

	function buildFromFile() {
		global $wgLocalFileRepo;
		$t = Title::newFromDBkey($this->args['image']);
		//$img = new LocalFile($t, RepoGroup::singleton()->getLocalRepo());
		//$img->loadFromFile();
		$img = wfFindFile($t);
		$filename = $img->getPath();
		$str = file_get_contents($filename);
		$this->map->loadXML($str);
		$this->root = $this->map->documentElement;
	}

	function setupLinks() {
		$nodeList = $this->map->getElementsByTagName('node');
		//walk the xml tree looking for link attributes
		$a = $this->args;
		foreach($nodeList as $element) {
			if($element->nodeType == XML_ELEMENT_NODE) {
				$element->removeAttribute('LINK');
				$a['child'] = Title::newFromText($element->getAttribute('TEXT'));
				$a['parent'] = Title::newFromText($element->parentNode->getAttribute('TEXT'));
				if ($a['child']->exists()) {
					$element->setAttribute('LINK', $this->renderer->enter('linkExist',$a)); //this does need some rendering
					$element->setAttribute('COLOR', $this->renderer->enter('existColour',$a));
				} else {
					$element->setAttribute('LINK', $this->renderer->enter('linkNoExist',$a));
					$element->setAttribute('COLOR', $this->renderer->enter('notExistColour',$a));
				}
				if (isset($this->args['fold'])) {
					$element->removeAttribute('FOLDED');
					$numchildren = $element->childNodes->length;
					$level = 1;
					$p = $element->parentNode;
					while (!$p->isSameNode($this->root)) {
						$level += 1;
						$p = $p->parentNode;
					}
					if ($level != 1) {
						$element->setAttribute('FOLDED',$this->foldtype($level,$numchildren));
					} else {
						$element->setAttribute('FOLDED',"false"); // don't fold the root it is very confusing
					}
				}
			}
		}
	}

	function getMap() {
		return $this->map->saveXML($this->map->getElementsByTagName('map')->item(0));
	}

	function newNodeFromTitle($title, $node) {
		$element = $this->map->createElement('node');
		$this->args['child']=Title::newFromDBkey($title);
		$element->setAttribute('ID',$this->renderer->enter('nodeID',$this->args));
		$element->setAttribute('TEXT',$this->renderer->enter('nodeText',$this->args));
		return $node->appendChild($element);
	}

	//private functions
	function recursiveBuildMap($node, $title, $level, $ancestry = array()) {
		//append this title as a node
		$children = $this->ng->getChildNodes($title);
		$ancestry[] = $title;
		$newnode = $this->newNodeFromTitle($title, $node);
		if ($level < $this->args['depth']) {
			foreach ((array) $children as $t) {
				if (!in_array($t,$ancestry)) {
					$this->recursiveBuildMap($newnode, $t, $level+1, $ancestry);
				} else {
					$this->backlink($newnode, $t);
				}
			}
		}
	}
	 
	function foldtype($level,$count) {
		global $wgSemanticGraphSettings;
		switch ($this->args['fold']) {
			case 'level1':
				if ($level>=2 && $count > 0) return "true";
				break;
			case 'level2':
				if ($level>=3 && $count > 0) return "true";
				break;
			case 'level3':
				if ($level>=4 && $count > 0) return "true";
				break;
			case 'none':
				return "false";
				break;
			case 'large':
				if ($count > $wgSemanticGraphSettings->maxToDispUnfolded) return "true";
				break;
		}
		return "false";
	}
	 
	function backlink($newnode, $t) {
		$element = $this->map->createElement('arrowlink');
		$element->setAttribute('DESTINATION',$t); //this is potentially not unique behaviour may be unpredictable
		$element->setAttribute('ENDARROW','default');
		$element->setAttribute('STARTARROW','none');
		$element->setAttribute('ID','arrowto'.$t);
		$element->setAttribute('STARTINCLINATION','0;90;'); //X;Y; from an origin in top left
		$element->setAttribute('ENDINCLINATION','0;90;');
		$newnode->appendChild($element);
	}

}
?>