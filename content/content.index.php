<?php

	require_once(EXTENSIONS . "/database_migrations/lib/utils.class.php");
	require_once(EXTENSIONS . "/database_migrations/lib/fixtures.class.php");
	
	class contentExtensionDatabase_migrationsIndex extends AdministrationPage {

		public function build()
		{
			parent::build();
			$this->setPageType('form');
			$this->setTitle('Symphony - Database Migrations');
			
		}

		public function about() {
		
		}

		public function view()
		{
			$this->__indexPage();
		}
		
		private function __indexPage() {
			
			//completely refresh the database with a clean install
			if($_GET["action"] == "cleaninstall") {
				
				//Run the baseline
				Database_Migrations_Utils::runMultipleQueries(file_get_contents(Database_Migrations_Utils::getSavePath() . "/". Database_Migrations_Utils::$BASELINE));
				
				//Run all the queries
				$fileList = Database_Migrations_Utils::getCompleteUpdateFileList();
				
				for($i=0;$i<count($fileList);$i++) {
					Database_Migrations_Utils::runMultipleQueries(file_get_contents($fileList[$i]));
				}				
				
				header("Location: " . SYMPHONY_URL . $_GET["redirect"]);
			
			}
			
			//perform a diff
			elseif($_GET["action"] == "update") {
				
				$fileList = array();	
				$fileList = Database_Migrations_Utils::getPendingUpdateFileList();
				
				
				if(count($fileList) > 0){
					//Backup
					//Database_Migrations_Utils::generateBackup();
				
					for($i=0;$i<count($fileList);$i++) {
						//run query & log
						Database_Migrations_Utils::runMultipleQueries(file_get_contents($fileList[$i]),true);
						Database_Migrations_Utils::appendLogItem(Database_Migrations_Utils::getFileNameFromPath($fileList[$i]));
					}
				}				
				header("Location: " . SYMPHONY_URL . $_GET["redirect"]);
			
			}
			
			
			
			elseif($_GET["action"] == "baseline") {
				//Database_Migrations_Utils::createBaseline(array("sym_authors", "sym_cache", "sym_sessions"));
				Database_Migrations_Utils::createBaseline();
			}
			elseif($_GET["action"] == "test") {
				Database_Migrations_Fixtures::truncateSection("TestNewSection");
			}
			else {
				$xmlRoot = new XMLElement('root');
			
				$xslt = new XSLTPage();
				$xslt->setXML($xmlRoot->generate());
				$xslt->setXSL(EXTENSIONS . '/database_migrations/content/index.xsl', true);
				$this->Form->setValue($xslt->generate());				
				
			}		
		
		}


	}

?>