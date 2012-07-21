<?php

	require_once(EXTENSIONS . "/database_migrations/lib/utils.class.php");
	require_once(EXTENSIONS . "/database_migrations/lib/fixtures.class.php");
	
	class contentExtensionDatabase_migrationsIndex extends AdministrationPage {

		public function build()
		{
			parent::build();
			$this->setPageType('form');
			$this->setTitle('');
			
		}

		public function about() {
		
		}

		public function view()
		{
			$this->__indexPage();
		}
		
		private function __indexPage() {
			
			if($_GET["action"] == "do") {
		
				$fileList = Database_Migrations_Utils::getDatabaseUpdateList();
				
				Database_Migrations_Utils::runMultipleQueries(file_get_contents(Database_Migrations_Utils::$SAVE_PATH . "baseline.sql"));
				
				for($i=0;$i<count($fileList);$i++) {
					Database_Migrations_Utils::runMultipleQueries(file_get_contents(Database_Migrations_Utils::$SAVE_PATH . $fileList[$i]));
				}				
				
				header("Location: " . SYMPHONY_URL . $_GET["redirect"]);
			
			}
			elseif($_GET["action"] == "baseline") {
				Database_Migrations_Utils::createBaseline(array("sym_authors", "sym_cache", "sym_database_migrations", "sym_extensions", "sym_extensions_delegates", "sym_sessions"));
			}
			elseif($_GET["action"] == "test") {
				Database_Migrations_Fixtures::truncateSection("TestNewSection");
			}
			else {
				$xslt = new XSLTPage();
				$xslt->setXSL(EXTENSIONS . '/database_migrations/content/index.xsl', true);
				$this->Form->setValue($xslt->generate());				
				
			}		
		
		}


	}

?>