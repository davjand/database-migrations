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
		
			Database_Migrations_Utils::createBaseline();
		
			Symphony::Configuration()->set('track-structure-only', 'yes', $location);
			Symphony::Configuration()->set('enabled', '1', $location);		
			Administration::instance()->saveConfig();
			return true;
		}
		
		public function uninstall($location='database-migrations') {
		
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

				
				// do not register changes to tbl_database_migrations
				if (preg_match("/{$tbl_prefix}database_migrations/i", $query)) return true;
				// only structural changes, no SELECT statements
				if (!preg_match('/^(insert|update|delete|create|drop|alter|rename)/i', $query)) return true;
				// un-tracked tables (sessions, cache, authors)
				if (preg_match("/{$tbl_prefix}(authors|cache|forgotpass|sessions|tracker_activity)/i", $query)) return true;
				// content updates in tbl_entries (includes tbl_entries_fields_*)
				if (preg_match('/^(insert|delete|update)/i', $query) && preg_match("/({$tbl_prefix}entries)/i", $query)) return true;
				// append query delimeter if it doesn't exist
				if (!preg_match('/;$/', $query)) $query .= ";";
	
				// Replace the table prefix in the query
				// This allows query execution on slave instances with different table prefix.
				// $query = str_replace($tbl_prefix,'tbl_',$query);
				
				$this->saveQuery($query);
							
				return true;
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
					
					$newFileName = Database_Migrations_Utils::getNewFileName();
					$newFilePath = Database_Migrations_Utils::getSavePath() . "/" . $newFileName ; 
					
					//log the entire thing to file
					file_put_contents($newFilePath, $query, FILE_APPEND);
					
					//add to the item to the log
					Database_Migrations_Utils::appendLogItem($newFileName);
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