<?php
require_once('RemoteObject.php');

require_once('Parser.php');
class RecipeParser extends Parser{
		function __construct(){
			parent::__construct();
		}
		protected function jsonToObject($jsonString, $recipe){
			$results = json_decode($jsonString, true);
			if (count($results) > 0) {
				
				$results = $results["query"]["results"];
				$jsonRecipe = $results[$recipe->getTitle()]["printouts"];
				$this->extractMembers($recipe, $jsonRecipe);
				$this->extractIngredients($recipe, $jsonRecipe);
				$this->extractDefinitions($recipe, $jsonRecipe);
				$this->extractTechReqs($recipe, $jsonRecipe);
				$this->extractProjects($recipe, $jsonRecipe);

				$this->extractTheme($recipe, $jsonRecipe);
				$this->extractFather($recipe, $jsonRecipe);
			}
			return $recipe;
		}
		public function extractFather($recipe, $jsonRecipe){
			$father = new RemoteObject($jsonRecipe["Découle du besoin technique"][0]);
			$recipe->setFather($father);
		}
		public function extractTheme($recipe, $jsonRecipe){
			$theme = $jsonRecipe["A thème"][0];
			$recipe->setTheme($theme);
		}

		public function extractMembers($recipe, $jsonRecipe){
			foreach ($jsonRecipe["A membre"] as $el) {
				$member = new RemoteObject($el);
				$recipe->addMember($member);
			}
		}
		public function extractIngredients($recipe, $jsonRecipe){
			foreach ($jsonRecipe["Ingrédient lié"] as $el) {
				$ingredient = new RemoteObject($el);
				$recipe->addIngredient($ingredient);
			}
		}
		public function extractDefinitions($recipe, $jsonRecipe){
			foreach ($jsonRecipe["Définition liée"] as $el) {
				$definition = new RemoteObject($el);
				$recipe->addDefinition($definition);
			}
		}
		public function extractTechReqs($recipe, $jsonRecipe){
			foreach($jsonRecipe["Besoin technique lié"] as $el){
				$techReq = new RemoteObject($el);
				$recipe->addTechReq($techReq);
			}
		}
		public function extractProjects($recipe, $jsonRecipe){
			foreach($jsonRecipe["Projet lié"] as $el){
				$recipe->addProject($el);
			}
		}
	}
?>