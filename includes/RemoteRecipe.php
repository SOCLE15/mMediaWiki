<?php
require_once('RemoteObject.php');
class RemoteRecipe extends RemoteObject{
	private $members;
	function __construct($value){
		Parent::__construct($value);
		$this->members = array();
		foreach($value["A membre"] as $memberArray){
			$member = new RemoteObject($memberArray);
			array_push($this->members, $member);
		}
	}
	public function getMembers(){
		return $this->members;
	}
}