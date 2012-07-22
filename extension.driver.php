<?php
	
	require_once(EXTENSIONS . "/database_migrations/lib/utils.class.php");
	
	Class Extension_Database_Migrations extends Extension {
	
		public function __construct(Array $args) {
			parent::__construct($args);
		}
	
		public function about() {
			return array(
				'name' => 'Database Migrations',
				'version' => '1.0',
				'release-date' => '2011-05-07',
				'author' => array(
					'name' => 'Tom Johnson',
					'website' => 'http://symphony-cms.com/'
				),
				'description' => 'Tracks database changes and generates change scripts.'
			);
		}
	
		public function install($location='database-migrations') {	
			
			$savePath=Database_Migrations_Utils::getSavePath();
			
			if(!file_exists($savePath)){
				mkdir($savePath);
			}
		
			try{
				Symphony::Database()->query("
					CREATE TABLE IF NOT EXISTS `tbl_database_migrations` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`version` VARCHAR(128) NULL,
						PRIMARY KEY  (`id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
				");
			}
			catch(Exception $e){
				echo($e->getMessage());
				return false;
			}		
		
			Database_Migrations_Utils::createBaseline();
		
			Symphony::Configuration()->set('track-structure-only', 'yes', $location);
			Symphony::Configuration()->set('db-version', '0', $location);
			Symphony::Configuration()->set('num-files', '0', $location);		
			Administration::instance()->saveConfig();
			return true;
		}
		
		public function uninstall($location='database-migrations') {
		
			try{
				Symphony::Database()->query("
					DROP TABLE `tbl_database_migrations`
				");
			}
			catch(Exception $e){
				return false;
			}		
		
			Symphony::Configuration()->remove($location);
			Administration::instance()->saveConfig();
			return true;
		}	
	
		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'AppendPageAlert',
					'callback' => 'appendAlert'
				),			
				array(
					'page' => '/frontend/',
					'delegate' => 'PostQueryExecution',
					'callback' => 'processQuery'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'PostQueryExecution',
					'callback' => 'processQuery'
				),
			);
		}
		
		public function processQuery($context) {
			if(Database_Migrations_Utils::$CAPTURE_ACTIVE) {
				$query = $context["query"];
				
				if($this->isChangeQuery($query)) {
					if($this->isStructureChangeQuery($query)) {
						$this->saveQuery($query);
					}
					else {
						if(Symphony::Configuration()->get("track-structure-only", "database-migrations") == "no") {
							$this->saveQuery($query);
						}				
					}
				}
				else {
					//probably a select query
				}
			}
		}
		
		public function appendAlert($context) {
			if(isset($_GET["db-update"])) {
				if($_GET["db-update"] == "success") {
					Administration::instance()->Page->pageAlert(
						__('Your database was updated.'),
						Alert::SUCCESS
					);
				}
				else {
					Administration::instance()->Page->pageAlert(
						__('Database update failed.'),
						Alert::ERROR
					);				
				}
			}
			else{
				if(Database_Migrations_Utils::instanceIsOutOfDate()) {
					Administration::instance()->Page->pageAlert(
						__('Your database is out of date.') . ' <a href="' . SYMPHONY_URL . '/extension/database_migrations?action=do&redirect=' . getCurrentPage() . '">' . __('Update?') . '</a>',
						Alert::ERROR
					);
				}
			}
		}
		
		
		private function saveQuery($query) {
			if(!($query == "")) {
				$newFilePath = Database_Migrations_Utils::getSavePath() . Database_Migrations_Utils::$FILE_PREFIX . Database_Migrations_Utils::getNextIndex() . ".sql";				
				file_put_contents($newFilePath, $query . ";\r\n", FILE_APPEND);
				$insertSql = "INSERT INTO tbl_database_migrations (`version`) VALUES ('" . md5($newFilePath) . "');";
				Symphony::Database()->query($insertSql);
				file_put_contents($newFilePath, $insertSql, FILE_APPEND);
			}

		}
		
		private function isChangeQuery($query) {

			$isChange = false;
			
			//correct type of query
			if(
				$this->strMultiFind($query, array(
						"ALTER",
						"CREATE",
						"DROP",
						"RENAME",
						"TRUNCATE",
						"DELETE",
						"INSERT",
						"REPLACE",
						"UPDATE"			
					))
				){
					$isChange=true;
				}
			
			//Not a 'SHOW' query
			if(substr($query, 0, strlen("SHOW")) === "SHOW"){
				$isChange=false;
			}
			
			return $isChange;
		}
		
		private function isStructureChangeQuery($query) {
			return $this->strMultiFind($query, array (
					"sym_sections",
					"sym_fields",
					"sym_fields_input",
					/*"sym_authors",*/
					/*"sym_forgotpass",*/
					"sym_pages",
					"sym_pages_type",
					"sym_extensions",
					"sym_extensions_delegates"
				));
		}
		
		
		private function strMultiFind($headline, $fields) {
			$regexp = '/(' . implode('|',array_values($fields)) . ')/i';
			return (bool) preg_match($regexp, $headline);
		}
		

		
	}