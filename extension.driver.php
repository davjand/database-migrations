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
			Symphony::Configuration()->set('enabled', '1', $location);		
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
			Symphony::Configuration()->set('enabled', '0', $location);			
		
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
				$query = trim($context["query"]);
				
				$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
				
				/* FILTERS */
				//Shamelessly stolen from: https://github.com/remie/CDI/blob/master/lib/class.cdilogquery.php
				// do not register changes to tbl_cdi_log
				if (preg_match("/{$tbl_prefix}database_migrations/i", $query)) return true;
				// only structural changes, no SELECT statements
				if (!preg_match('/^(insert|update|delete|create|drop|alter|rename)/i', $query)) return true;
				// un-tracked tables (sessions, cache, authors)
				if (preg_match("/{$tbl_prefix}(authors|cache|forgotpass|sessions|tracker_activity)/i", $query)) return true;
				// content updates in tbl_entries (includes tbl_entries_fields_*)
				if (preg_match('/^(insert|delete|update)/i', $query) && preg_match("/({$config->tbl_prefix}entries)/i", $query)) return true;
				// append query delimeter if it doesn't exist
				if (!preg_match('/;$/', $query)) $query .= ";";
	
				// Replace the table prefix in the query
				// This allows query execution on slave instances with different table prefix.
				$query = str_replace($tbl_prefix,'tbl_',$query);
				
				$this->saveQuery($query);			
				return true;
				/*
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
				}*/
			}
		}
		
		public function appendAlert($context) {
			
			//check still installed and worth running
			if(Symphony::Configuration()->get("enabled", "database-migrations") == "1" ){
			
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
							__('Your database is out of date.') . ' <a href="' . SYMPHONY_URL . '/extension/database_migrations?action=update&redirect=' . getCurrentPage() . '">' . __('Update?') . '</a>',
							Alert::ERROR
						);
					}
				}
			}
		}
		
		
		private function saveQuery($query) {
			if(Symphony::Configuration()->get("enabled", "database-migrations") == "1" ){
				if(!($query == "")) {
				
					//We can't MD5 the entire path as this may change
					$newFileName = Database_Migrations_Utils::$FILE_PREFIX . Database_Migrations_Utils::getNextIndex() . ".sql";
					$newFilePath = Database_Migrations_Utils::getSavePath() . "/" . $newFileName; 
									
					file_put_contents($newFilePath, $query . ";\r\n", FILE_APPEND);
					$insertSql = "INSERT INTO tbl_database_migrations (`version`) VALUES ('" . md5($newFileName) . "');";
					Symphony::Database()->query($insertSql);
					file_put_contents($newFilePath, $insertSql, FILE_APPEND);
				}
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