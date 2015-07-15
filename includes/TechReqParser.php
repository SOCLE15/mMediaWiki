<?php
require_once('RemoteObject.php');

require_once('Parser.php');
class TechReqParser extends Parser{
		function __construct(){
			parent::__construct();
		}
		protected function jsonToObject($jsonString, $techReq){
			$results = json_decode($jsonString, true);
			if (count($results) > 0) {
				$results = $results["query"]["results"];
				$jsonTechReq = $results[$techReq->getTitle()]["printouts"];
				$this->extractRecipes($techReq, $jsonTechReq);
				$this->extractIngredients($techReq, $jsonTechReq);
				$this->extractDefinitions($techReq, $jsonTechReq);
				$this->extractTheme($techReq, $jsonTechReq);
			}

			return $techReq;
		}
		public function extractTheme($project, $jsonTechReq){
			if($jsonTechReq["A thème"] != null && $jsonTechReq["A thème"][0]!= null){
				$theme = $jsonTechReq["A thème"][0];
				$project->setTheme($theme);			
			}

		}
		public function extractRecipes($techReq, $jsonTechReq){
			if($jsonTechReq["Recette liée"] != null){
				foreach ($jsonTechReq["Recette liée"] as $el) {
					$recipe = new RemoteObject($el);
					$techReq->addRecipe($recipe);
				}		
			}
			
		}
		public function extractIngredients($techReq, $jsonTechReq){
			if($jsonTechReq["Ingrédient lié"] != null){
				foreach ($jsonTechReq["Ingrédient lié"] as $el) {
					$ingredient = new RemoteObject($el);
					$techReq->addIngredient($ingredient);
				}	
			}

		}
		public function extractDefinitions($techReq, $jsonTechReq){
			if($jsonTechReq["Définition liée"] != null){
				foreach ($jsonTechReq["Définition liée"] as $el) {
					$definition = new RemoteObject($el);
					$techReq->addDefinition($definition);
				}
			}
		}
	}
?>