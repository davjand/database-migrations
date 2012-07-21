<?php

	require_once(CORE . "/class.symphony.php");
	
	require_once(TOOLKIT . '/class.xsltpage.php');
	require_once(TOOLKIT . '/class.administrationpage.php');

	Class Database_Migrations_Utils {
		
		// a switch so that if we're doing operations ourselves (like in the testing functions), we can turn ourselves off.
		public static $CAPTURE_ACTIVE = 1;
		
		public static $SAVE_PATH = "./workspace/migrations/";
		public static $FILE_PREFIX = "db-";

		public static function getDatabaseUpdateList() {
			
			$list = array();

			if ($handle = opendir(self::$SAVE_PATH)) {
				while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..") {
						if(substr($entry, 0, 3) == self::$FILE_PREFIX) {
							$list[] = $entry;
						}
					}
				}
				closedir($handle);
            }		
		
			return $list;
		
		}
		
		public static function getNextIndex() {
			$id = 0;

			$fileList = self::getDatabaseUpdateList();
			for($i=0;$i<count($fileList);$i++) {
				$num = str_replace(array(self::$FILE_PREFIX,".sql"), "", $fileList[$i]);
				if($num > $id && is_numeric($num)) {
					$id = $num;
				}		
			}
			
            return $id+1;
		}
		
		public static function instanceIsOutOfDate() {
		
			$latestFileName = self::$SAVE_PATH . self::$FILE_PREFIX . (self::getNextIndex() - 1) . ".sql";
			$latestVersion = md5($latestFileName);
			
			$latestInstalledVersion = Symphony::Database()->fetchVar("version", 0, "SELECT * FROM tbl_database_migrations ORDER BY id DESC LIMIT 1");
			
			if($latestInstalledVersion == "" || ($latestVersion == $latestInstalledVersion)) {
				return false;
			}
			else {
				return true;
			}
		
		}		
		
		public static function createBaseline($ignoreTables = array()) {

			$return = "";
		
			$tablesRaw = Symphony::Database()->fetch("SHOW TABLES");
			
			$rawTemp = array_keys($tablesRaw[0]);
			$rawKey = $rawTemp[0];
			
			$tables = array();
			foreach($tablesRaw as $raw) {
				$tables[] = $raw[$rawKey];
			}
			

			//cycle through
			foreach($tables as $table) {
				if(!in_array($table, $ignoreTables)) {
					$return .= self::getTableBackup($table);
				}
			}

			file_put_contents(self::$SAVE_PATH . "/baseline.sql", $return);
			
		}
		
		public static function getTableBackup($table) {
				
			$return = "";
		
			$rowResults = Symphony::Database()->fetch("SELECT * FROM ".$table);
			$numFields = count($rowResults[0]);
			$return.= 'DROP TABLE '.$table.';';
			
			$createRow = Symphony::Database()->fetch('SHOW CREATE TABLE '.$table);
		
			$return.= "\n\n". $createRow[0]["Create Table"] .";\n\n";

			for ($i = 0; $i < count($rowResults); $i++)  {
				
				$rowInsertDef = "INSERT INTO {$table} (";
				$rowInsertVal = ") VALUES (";
				$rowInsertEnd = ");";
				
				foreach($rowResults[$i] as $k => $v) {
				
					$rowInsertDef .= $k . ",";
					$rowInsertVal .= "'" . $v . "',";
					
				}
				
				$rowInsertDef = substr($rowInsertDef,0,-1);
				$rowInsertVal = substr($rowInsertVal,0,-1);
				
				$return .= $rowInsertDef . $rowInsertVal . $rowInsertEnd . "\r\n";

			}
			$return.="\n\n\n";
			
			return $return;
			
		}	
		

	}
	
?>