	<?php 
	
	
	class config{
		//Define db variables
		private $dbdriver ;
		private $dbname ;	
		private $dbuser ;
		private $dbpass ;
		//URL
		private $dbhost ;
		private $baseURL ;
		private $rootBaseURL ;
		private $imageBaseURL ;
		
		//constructor
		function __construct(){
			//initialize parameters
			$this->initialize() ;
		}
		
		public function getDBDriver(){
			return $this->dbdriver ;
		}
		
		public function getDBname(){
		  return $this->dbname ;	
		}
		
		public function getDBuser(){
		  return $this->dbuser ;	
		}
		
		public function getDBpass(){
		  return $this->dbpass ;	
		}
		
		public function getDBhost(){
		  return $this->dbhost ;	
		}
		
		public function getBaseURL(){
			return $this->baseURL ;
		}
		
		public function getRootBaseURL(){
			return $this->rootBaseURL ;
		}
		
		public function getImageBaseURL(){
			return $this->imageBaseURL ;
		}
		
		
		public function checkInternetConnection( $testUrl = 'www.uam.edu.ng' ){
			$connectionStatus = true ;
			if( !$openSocket = @fsockopen( $testUrl, 80 ) ):
				$connectionStatus = false ;
			endif ;
			return $connectionStatus ;
		}
		
		private function initialize(){
			$this->dbdriver = "pdo_mysql" ;
			$this->dbhost = "localhost" ;
			$this->dbuser = "wdlgdgmy_ipess" ;
			$this->dbpass = "wdlgdgmy_ipess" ;
			$this->dbname = "wdlgdgmy_ipess" ;
			$this->baseURL = "http://localhost/";
			$this->rootBaseURL = "/" ;
			
		}
	}
	?>