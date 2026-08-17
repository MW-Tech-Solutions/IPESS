<?php
include('config.php') ;
class EntityManager {	
	public static function getEntityManager() {		
		return self::createConnection() ;
	}
	
	private static function createConnection() {
		$connection = NULL ;
		$config = new config() ;
		$dns = 'mysql:host=' . $config->getDBhost() . ';dbname=' . $config->getDBname()  ;
		try{
			$connection =  new PDO($dns, $config->getDBuser(), $config->getDBpass(), array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true));
			// set the PDO error mode to exception
			$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch(PDOException $e) {
			//log error details to a file
		 	$connection = NULL ;
			//echo "Unable to connect to server: ".$e->getMessage();
			//die($e->getMessage()) ;
		}		
		return $connection ;
	}
	
}
