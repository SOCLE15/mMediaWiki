<?php
require_once('TechReqParser.php');
require_once 'Images/GraphViz.php'; 
/*$mw = new TechnicalRequirement("Installer et configurer MediaWiki");
$mw->retrieveData();
$mw->createGraph();
*/
//TODO : create an internalisation class w. strings
//TODO : link members and recipes

/*
*Technical requirement class has ingredients, definitions, functional requirements and members
*In this class you will find the tech req model and few functions to render a tech req's graph
*You should also give a look at the TechReqParser class 
*/
class TechnicalRequirement{
	private $objectsToQuery = "[[Category:Besoin technique]] ";
	private $parametersToQuery = "|?A thème|?Recette liée|?Définition liée|?Ingrédient lié";
	private $title;
	private $recipes;
	private $definitions;
	private $ingredients;
	private $theme;

	function __construct($techReqName = ''){
		$this->title = $techReqName;
		$this->definitions = array();
		$this->ingredients = array();
		$this->recipes = array();
	}
	public function addIngredient($ingredient){
		array_push($this->ingredients,$ingredient);
	}
	public function addRecipe($recipe){
		array_push($this->recipes,$recipe);
	}
	public function addDefinition($definition){
		array_push($this->definitions,$definition);
	}
	public function setTheme($theme){
		$this->theme = $theme;
	}
	public function getQuery(){
		$query = str_replace("%BT%", $this->title, $this->objectsToQuery);
		$query .= $this->parametersToQuery;
		return $query;
	}
	public function getTitle(){
		return $this->title;
	}
	/*
	*Creates a technical requirement type parser and ask for data from the mediawiki api
	*The query used exists is in this class but you should NOT change it as everything is tight linked
	*@args 
	*@return 
	*/
	public function retrieveData(){
		$mParser = new TechReqParser;
		$mParser->retrieveInfoForObject($this);
	}
	/*
	*Create a graph and fill it with the ingredients, theme recipes  and definitions 
	*@args 
	*@return code for the graph 
	*/
	public function createGraph(){
		$graph = new Image_GraphViz();
		$graph->addNode($this->title);
		$this->linkWithString($graph,$this->theme,"A comme thème");
		foreach ($this->definitions as $definition) {$this->addAndLinkNodeForRemoteObject($graph,$definition,"A comme définition");}
		foreach ($this->ingredients as $ingredient) {$this->addAndLinkNodeForRemoteObject($graph,$ingredient,"A comme ingrédient");}
		foreach ($this->recipes as $recipe) {$this->addAndLinkNodeForRemoteObject($graph,$recipe, "A comme recette");}
		return $graph->parse();
		//$graph->image(); 
	}
	public function linkWithString($graph, $string, $label){

		$graph->addEdge(array($this->title => $string), array('label' => $label,'color' => 'blue')); 
	}
	/*
	*Add first depth instance of the  RemoteObject class
	*@args the $graph we are dealing w. the $remoteObject to render on the graph and the $label that has to be shown on the edge
	*@return
	*/
	public function addAndLinkNodeForRemoteObject($graph, $remoteObject, $label){
		$url = '';
		if($remoteObject->exists()){
			$url= $remoteObject->getUrl();
		}
		$graph->addNode($remoteObject->getTitle(), array('URL' => $url, 'shape' => 'box') ); 
		$graph->addEdge(array($this->title => $remoteObject->getTitle()), array('label' => $label,'color' => 'blue')); 
	}

	/*	
	public function setDefinitions($resultsArray){
		$this->definitions = $resultsArray["Définition liée"];
	}
	public function setIngredients($resultsArray){
		$this->ingredients = $resultsArray["Ingrédient lié"];
	}
	*/
}
?>