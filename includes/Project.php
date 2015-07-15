<?php
require_once('ProjectParser.php');
require_once('FunctionalRequirement.php');
require_once 'Images/GraphViz.php'; 
/*$socle = new Project("SOCLE LAAS15");
$socle->retrieveData();
$socle->createGraph();*/

//TODO : create an internalisation class w. strings
//TODO : link members and recipes

/*
*Project class has ingredients, definitions, functional requirements and members
*In this class you will find the project model and few functions to render a project's graph
*You should also give a look at the ProjectParser class 
*/
class Project{
	private $objectsToQuery = "[[%PROJET%]] OR [[-Has subobject::%PROJET%]] OR [[Category:Recette]] [[Projet lié::%PROJET%]] ";
	private $parametersToQuery = "|?A membre|?Lié|?Besoin fonctionnel lié|?Découle du besoin technique|?Ingrédient lié|?Définition liée|?Catégorie";
	private $title;
	private $definitions;
	private $ingredients;
	private $funcReqs;
	private $members;

	function __construct($projectName = ''){
		$this->title = $projectName;
		$this->definitions = array();
		$this->members = array();
		$this->ingredients = array();
		$this->funcReqs = array();
	}
	public function addIngredient($ingredient){
		array_push($this->ingredients,$ingredient);
	}
	public function addDefinition($definition){
		array_push($this->definitions,$definition);
	}
	public function addMember($member){
		array_push($this->members,$member);
	}
	public function addFuncReq($funcReqTitle){
		$funcReq = new FunctionalRequirement($funcReqTitle);
		$this->funcReqs[$funcReqTitle] = $funcReq;
	}
	public function addTechToFunc($techReq, $funcReqTitle){
		$this->funcReqs[$funcReqTitle]->addTechReq($techReq);
	}

	public function getFuncReqs(){
		return $this->funcReqs;
	}
	public function getQuery(){
		$query = str_replace("%PROJET%", $this->title, $this->objectsToQuery);
		$query .= $this->parametersToQuery;
		return $query;
	}
	public function getFuncReqByTitle($title){
		$ret = null;
		foreach($this->funcReqs as $funcReq){
			if(strcmp($funcReq->getTitle(), $title)){
				$ret = $funcReq;
				break;
			}
		}
		return $ret;
	}
	public function getTitle(){
		return $this->title;
	}
	/*
	*Creates a project type parser and ask for data from the mediawiki api
	*The query used exists is in this class but you should NOT change it as everything is tight linked
	*@args 
	*@return 
	*/
	public function retrieveData(){
		$mParser = new ProjectParser;
		$mParser->retrieveInfoForObject($this);
	}
	/*
	*Add and link recipe with the corresponding technical requirement in a functional requirement (the one where exists the func. requirement)
	*The functional requirement MUST already exists in the functional requirement 
	*You only need to specify the title of the technical requirement
	*@args $recipe to add (RemoteObject type) $title of the Technical requirement to link with
	*@return 
	*/
	public function addRecipeToBF($recipe, $techReqTitle){
		foreach ($this->funcReqs as $funcReq) { 
			if($funcReq->hasTechReqByTitle($techReqTitle)){
				$funcReq->linkRecipeWithTechReqTitle($recipe, $techReqTitle);
				break;
			}
		}
	}
	/*
	*Create a graph and fill it with the ingredients, definitions and members
	*Functional requirement are add as well but what's under rely on the functional requirement class
	* (as technical requirement and  recipes are linked with the project through the func. requirements)
	*@args 
	*@return code for the graph 
	*/
	public function createGraph(){
		$graph = new Image_GraphViz();
		$graph->addNode($this->title);
		foreach ($this->members as $member) {$this->addAndLinkNodeForRemoteObject($graph,$member,"A comme membre");}
		foreach ($this->definitions as $definition) {$this->addAndLinkNodeForRemoteObject($graph,$definition,"A comme définition");}
		foreach ($this->ingredients as $ingredient) {$this->addAndLinkNodeForRemoteObject($graph,$ingredient,"A comme ingrédient");}
		foreach ($this->funcReqs as $funcReq) {$this->addAndLinkNodeForFuncReq($graph,$funcReq);}
		return $graph->parse();
		//$graph->image(); 
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
	*create functional requirements and call the method graphyourself in Functional Requirement which take flame
	*@args $graph and $functionalRequirement
	*
	*/
	public function addAndLinkNodeForFuncReq($graph, $funcReq){
		$title = $funcReq->getTitle();
		$graph->addNode($title, array( 'shape' => 'box') ); 
		$graph->addEdge(array($this->title => $title), array('label' => "A comme besoin fonctionnel",'color' => 'red'));
		$funcReq->graphYourself($graph);
	}
	/*	
	public function setDefinitions($resultsArray){
		$this->definitions = $resultsArray["Définition liée"];
	}
	public function setIngredients($resultsArray){
		$this->ingredients = $resultsArray["Ingrédient lié"];
	}
	public function setFuncReqs($funcReqs){
		$this->funcReqs = $funcReqs;
	}
	public function setMembers($members){
		$this->members = $members;
	}*/
}
?>