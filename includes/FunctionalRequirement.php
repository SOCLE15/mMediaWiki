<?php

class FunctionalRequirement{
	private $title;
	private $linkedTo;
	private $techReqs;
	function __construct($title){
		$this->title = $title;
		$this->linkedTo = array();
		$this->techReqs = array();
	}
	public function setTitle($title){
		$this->title = $title;
	}
	public function linkRecipeWithTechReqTitle($recipe,$techReqTitle){
		$techReq = $this->techReqByTitle($techReqTitle);
		$this->linkedTo[$techReqTitle] = $recipe;
	}
	public function addTechReq($techReq){
		$this->linkedTo[$techReq->getTitle()] = null;
		$this->techReqs[$techReq->getTitle()] = $techReq;
	}
	public function getTitle(){
		return $this->title;
	}
	public function techReqByTitle($title){
		$ret = null;
		if($this->hasTechReqByTitle($title)){
			$ret = $this->techReqs[$title];
		}
		return $ret;
	}
	public function hasTechReqByTitle($title){
		return array_key_exists($title, $this->techReqs);
	}

	public function graphYourself($graph){
		foreach($this->techReqs as $key => $value){
			$recipe = $this->linkedTo[$key];
			var_dump($this->linkedTo);
			$urlTechReq= '';
			if($value->exists()){
				$urlTechReq= $value->getUrl();
			}
			$graph->addNode($key, array('URL' => $urlTechReq,  'shape' => 'box') );
			$graph->addEdge(array($this->title => $key), array('label' => "A comme besoin technique",'color' => 'green'));
			if($recipe !=null){
				$recipeTitle = $recipe->getTitle();
				$urlRecipe= '';
				if($recipe->exists()){
					$urlRecipe= $recipe->getUrl();
				}
				$graph->addNode($recipeTitle, array('URL' => $urlRecipe,  'shape' => 'box') );
				$graph->addEdge(array($key => $recipeTitle), array('label' => "A comme recette",'color' => 'yellow'));
				foreach($recipe->getMembers as $member){$graph->addEdge(array($recipeTitle => $member->getTitle()), array('label' => "A comme membre", 'color' => 'green'));}
			}
		}
	}

}

