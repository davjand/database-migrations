<?php
require_once("../extension.driver.php");

class IntegrationTest extends PHPUnit_Framework_TestCase {

	public function testInstall() {
		
		$drv = new Extension_Database_Migrations();
		$drv->install("database-integration-test");
		
		$this->assertEquals("no",Symphony::Configuration()->get("track-structure-only", "database-integration-test"));
		$this->assertEquals("0",Symphony::Configuration()->get("db-version", "database-integration-test"));
		$this->assertEquals("0",Symphony::Configuration()->get("num-files", "database-integration-test"));
		
		return $drv;
		
	}
	
	/**
	 * @depends testInstall
	 */
	public function testUninstall($drv) {
	
		$drv->uninstall("database-integration-test");
		
		$this->assertEquals("",Symphony::Configuration()->get("track-structure-only", "database-integration-test"));
		$this->assertEquals("",Symphony::Configuration()->get("db-version", "database-integration-test"));		
		$this->assertEquals("",Symphony::Configuration()->get("num-files", "database-integration-test"));	
		
	}
	
	
	public function testQueryHook() {
	
		$mock = $this->getMock("Extension_Database_Migrations", array("processQuery"));
		
		$mock->expects($this->any())
				->method("processQuery");
				->will($this->throwException(new Exception("test-query-hook"));
				

		try {
			$ret = Symphony::Database()->query("SELECT * FROM information_schema.tables");
		}
		catch(Exception $e) {
			if($e->getMessage() == "test-query-hook") {
				$this->assertTrue();
			}			
		}
				
	}

	
}







?>