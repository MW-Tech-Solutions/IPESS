<?php
include('EntityManager.php') ;

abstract class ControlConnection{
	
	protected $entityManager ;
	protected $validator ;
	protected $config ;
	
	public function __construct(){
		//session_start() ;
		$this->entityManager = EntityManager::getEntityManager() ;
		//$this->validator = new InputValidator() ;
		$this->config = new config() ;
	}
	}
	