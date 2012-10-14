<?php
	
	require_once(EXTENSIONS . "/database_migrations/lib/utils.class.php");
	
	Class Extension_Database_Migrations extends Extension {
	
		public function __construct(Array $args) {
			parent::__construct($args);
		}
	
		public function about() {
			return array(
				'name' => 'Database Migrations',
				'version' => '1.0.2',
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
			Symphony::Configuration()->set('allow-blueprints-access','1', $location);
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
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'ExtensionsAddToNavigation',
					'callback'	=> 'mod_navigation'
				)
			);
		}
		
		public function processQuery($context) {
			return Database_Migrations_Utils::saveQuery(trim($context["query"]));	
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
		
		public function mod_navigation($context) {
			
			
			$context['navigation'][200]['children'][] = array(
				'link'		=> '/extension/database_migrations/',
				'name'		=> __('Database Migrations'),
				'visible'	=> 'yes'
			);
			
			if(Symphony::Configuration()->get("allow-blueprints-access", "database-migrations") == "0") {
				$context['navigation'][200] = array();
			}

		}		
		
		

		
	}