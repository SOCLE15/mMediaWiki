<?php 

require_once('/Path/to/pear/Services/JSON.php');

abstract class Parser {

	private $apiURL = "http://smw.learning-socle.org/api.php?";
	private $actionASK = "ask";
	private $queryPrefix = "&query=";
	private $actionPrefix = "&action=";
	private $formatPrefix = "&format=";
	private $formatJSON = "json";
	private $jsonService;

	function __construct(){
		$this->jsonService = new Services_JSON();
	}
	public function retrieveInfoForObject($object){
		$jsonString = $this->getObjectAsJson($object);
		$this->jsonToObject($jsonString, $object);
	}
	private function getObjectAsJson($object){
		$mQuery=urlencode($object->getQuery());
		$url=$this->apiURL.$this->actionPrefix.$this->actionASK.$this->queryPrefix.$mQuery.$this->formatPrefix.$this->formatJSON;
		return file_get_contents($url);
	}
	abstract protected function jsonToObject($jsonString, $object);
	
}
?>