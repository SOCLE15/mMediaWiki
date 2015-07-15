<?php

class RemoteObject{	
	private $title;
	private $url;
	private $exists;
	private $members;
	function __construct($dataArray){
		$this->createFromArray($dataArray);
		$this->members = array();
	}
	public function createFromArray($dataArray){
		$this->title = $dataArray["fulltext"];
		$this->url = $dataArray["fullurl"];
		$this->exists = array_key_exists("exists", $dataArray);// && $dataArray["exists"] == true;
	}
	public function setTitle($title){
		$this->title = $title;
	}
	public function setUrl($url){
		$this->url = $url;
	}
	public function setExists($exists){
		$this->exists = $exists;
	}
	public function getTitle(){
		return $this->title;
	}
	public function getUrl(){
		return $this->url;
	}
	public function exists(){
		return $this->exists;
	}
	public function addMember($member){
		array_push($this->members, $member);
	}
}