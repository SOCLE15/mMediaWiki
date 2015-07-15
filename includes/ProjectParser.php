<?php 
require_once('RemoteObject.php');
require_once('RemoteRecipe.php');

require_once('Parser.php');
class ProjectParser extends Parser{
		function __construct(){
			parent::__construct();
		}
		protected function jsonToObject($jsonString, $project){
			echo $jsonString.'<br><br>';
			$results = json_decode($jsonString, true);
			if (count($results) > 0) {
				
				$results = $results["query"]["results"];
				$jsonProject = $results[$project->getTitle()]["printouts"];
				$this->extractMembers($project, $jsonProject);
				$this->extractIngredients($project, $jsonProject);
				$this->extractDefinitions($project, $jsonProject);
				$this->extractFuncReqs($project, $jsonProject);
				foreach ($project->getFuncReqs() as $el) {
					$title = $el->getTitle();
					$this->extractTechReq($project, $results[$project->getTitle().'#'.$title], $title);
				}
				$this->extractNLinkRecipesAndTechReqs($project, $results);
			}
			return $project;
		}
		public function extractTechReq($project, $funcReqArray, $funcReqName){
			foreach($funcReqArray["printouts"]["Lié"] as $techReqArray){
				$techReq = new RemoteObject($techReqArray);
				$project->addTechToFunc($techReq, $funcReqName);
			}
		}

		public function extractMembers($project, $jsonProject){
			foreach ($jsonProject["A membre"] as $el) {
				$member = new RemoteObject($el);
				$project->addMember($member);
			}
		}
		public function extractIngredients($project, $jsonProject){
			foreach ($jsonProject["Ingrédient lié"] as $el) {
				$ingredient = new RemoteObject($el);
				$project->addIngredient($ingredient);
			}
		}
		public function extractDefinitions($project, $jsonProject){
			foreach ($jsonProject["Définition liée"] as $el) {
				$definition = new RemoteObject($el);
				$project->addDefinition($definition);
			}
		}
		public function extractFuncReqs($project, $jsonProject){
			foreach($jsonProject["Besoin fonctionnel lié"] as $el){
				$project->addFuncReq($el);
			}
		}
		public function extractNLinkRecipesAndTechReqs($project, $results){
			foreach ($results as $key1 => $value1) {
				$recipe = null;
				foreach ($value1["printouts"]["Catégorie"] as $key => $value) {
					if(array_key_exists("fulltext", $value) && strcmp($value["fulltext"], "Catégorie:Recette") == 0){
						$recipe = new RemoteRecipe($value1);
						break;
					}
				}
				if($recipe != null){
					$techReqLinkedName = $value1["printouts"]["Découle du besoin technique"][0]["fulltext"];
					$project->addRecipeToBF($recipe, $techReqLinkedName);
				}
			}
		}
	}
?>