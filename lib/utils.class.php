<?php

	require_once(CORE . "/class.symphony.php");
	
	require_once(TOOLKIT . '/class.xsltpage.php');
	require_once(TOOLKIT . '/class.administrationpage.php');

	Class Database_Migrations_Utils {
		
		// a switch so that if we're doing operations ourselves (like in the testing functions), we can turn ourselves off.
		public static $CAPTURE_ACTIVE = 1;
		
		public static $SAVE_PATH = '/../data';
		public static $BACKUP_PATH = '/../data-backups';
		public static $FILE_PREFIX = "db-";
		public static $LOCAL_LOG = "queries-run-locally.csv";
		public static $BASELINE = "baseline.sql";
		
		public static function getSavePath(){
			$savePath = DOCROOT . self::$SAVE_PATH;
			
			if(!file_exists($savePath)){
				mkdir($savePath);
			} 
			return $savePath;
		}
		
		public static function getBackupPath(){
			$savePath = DOCROOT . self::$BACKUP_PATH;
			
			if(!file_exists($savePath)){
				mkdir($savePath);
			} 
			return $savePath;
		}
		
		public static function getLocalLogPath(){
			$logPath = DOCROOT . self::$SAVE_PATH . "/" . self::$LOCAL_LOG;
			if(!file_exists($logPath)){
				touch($logPath);
			}
			return $logPath;
		}

		
		public function getCompleteUpdateFileList(){
			
			$fileList=array();
			
			$saveDir = self::getSavePath();
			if (glob($saveDir . "/*.sql") == false) return $fileList;
			
			$fileList = glob($saveDir . "/*.sql");
			
			if(count($fileList)<1)return $fileList;
			
			//ensure alphabetical order)
			sort($fileList);
			
			//remove baseline
			$fileList = array_filter($fileList, function($item){
				if(strpos($item,Database_Migrations_Utils::$BASELINE) === false){
					return true;
				}
				else{
					return false;
				}
			});
			
			return $fileList;
		}


		public static function getPendingUpdateFileList() {
			
			$updateFiles = array();
			
			//load the log file
			$logDir = self::getLocalLogPath();
			$logContents = file_get_contents($logDir);
			$logList = explode("\n",$logContents);
			
			if(count($logList) < 1) return $updateFiles;
			
			$list = self::getCompleteUpdateFileList();
			
			if(count($list) < 2) return $updateFiles;
			
			//search through looking for the relevent ones
			for($i=0; $i < count($list); $i++){
			
				$foundFlag=false;
				for($k=0; $k < count($logList) -1; $k++){
				
					if(self::getFileNameFromPath($list[$i]) == $logList[$k]){
						$foundFlag=true;
						break;
					}
				}
				
				if(!$foundFlag){
					$updateFiles[] = $list[$i];
				}
				
			}
			
			if(count($updateFiles)>0){
				sort($updateFiles);
			}
			
			return $updateFiles;
		
		}
		
		public static function getNewFileName($path,$increment=0){			
			//check that it doesn't exist
			$fileId = date("Y-m-d-His");
			
			//add some trailing sequential numbers ifneeded
			$fileName = $FILE_PREFIX . $fileId ."-" . sprintf("%05s", $increment) . ".sql";			
			
			if(file_exists($path."/".$fileName)){
				return self::getNewFileName($path,$increment+1);
			}
			else{
				return $fileName;
			}
		}
		
		public static function getFileNameFromPath($path){
			return str_replace(self::getSavePath()."/","",$path);
		}
		
		
		public static function instanceIsOutOfDate() {
			
			//count the logged queries and the number of queries, if different then not up to date
			$saveDir = self::getSavePath();
			if (glob($saveDir . "/*.sql") == false) return false;
			
			$sqlCount = count(glob($saveDir . "/*.sql")) - 1;
			
			$logDir = self::getLocalLogPath();
			$logContents = file_get_contents($logDir);
			$logCount = count(explode("\n",$logContents)) -1;
			
			if($logCount < $sqlCount){
				return true;
			}
			else {
				return false;
			}
		}		
		
		public static function generateBackup($ignoreTables = array()){
			$data = self::getBaselineSQL($ignoreTables);
			
			$newFileName = self::getNewFileName(self::getBackupPath());
			
			file_put_contents(self::getBackupPath() . "/". $newFileName, self::getBaselineSQL($ignoreTables));
		}
		
		public static function createBaseline($ignoreTables = array()) {
			file_put_contents(self::getSavePath() . "/". self::$BASELINE, self::getBaselineSQL($ignoreTables));
		}
		
		
		public static function getBaselineSQL($ignoreTables = array()){
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
			return $return;
		}
		
		public static function getTableBackup($table) {
				
			$return = "";
		
			$rowResults = Symphony::Database()->fetch("SELECT * FROM ".$table);
			$numFields = count($rowResults[0]);
			$return.= 'DROP TABLE IF EXISTS '.$table.';';
			
			$createRow = Symphony::Database()->fetch('SHOW CREATE TABLE '.$table);
		
			$return.= "\n\n". $createRow[0]["Create Table"] .";\n\n";

			for ($i = 0; $i < count($rowResults); $i++)  {
				
				$rowInsertDef = "INSERT INTO {$table} (";
				$rowInsertVal = ") VALUES (";
				$rowInsertEnd = ");";
				
				foreach($rowResults[$i] as $k => $v) {
				
					$rowInsertDef .= "`" . $k . "`,";
					$rowInsertVal .= "'" . $v . "',";
					
				}
				
				$rowInsertDef = substr($rowInsertDef,0,-1);
				$rowInsertVal = substr($rowInsertVal,0,-1);
				
				$return .= $rowInsertDef . $rowInsertVal . $rowInsertEnd . "\r\n";

			}
			$return.="\n\n\n";
			
			return $return;
			
		}
		
		public static function appendLogItem($item){
			file_put_contents(Database_Migrations_Utils::getLocalLogPath(), $item."\n", FILE_APPEND);
		}
		
		/* runMultipleQueries
		 *
		 * A quick and dirty method of running multiple mysql queries
		 *
		*/
		public static function runMultipleQueries($query,$disableLogging=false){
		
			//I have no idea how this works but: http://stackoverflow.com/questions/689257/mysql-split-multiquery-string-with-php
			$queryArray = preg_split('/[.+;][\s]*\n/', $query, -1, PREG_SPLIT_NO_EMPTY);
			
			$currentState= self::$CAPTURE_ACTIVE;
			
			//prevent logging
			self::$CAPTURE_ACTIVE = !$disableLogging;
			foreach($queryArray as $singleQuery){
				Symphony::Database()->query($singleQuery);
			}
			self::$CAPTURE_ACTIVE = $currentState;
				
			return;
		}
		
		public static function saveQuery($query) {
			
			if(self::$CAPTURE_ACTIVE) {
				
				$tbl_prefix = Symphony::Configuration()->get('tbl_prefix', 'database');
				
				/* FILTERS */
				//Shamelessly stolen from: https://github.com/remie/CDI/blob/master/lib/class.cdilogquery.php

				// do not register changes to tbl_database_migrations
				if (preg_match("/{$tbl_prefix}database_migrations/i", $query)) return true;
				// only structural changes, no SELECT statements
				if (!preg_match('/^(insert|update|delete|create|drop|alter|rename)/i', $query)) return true;
				// un-tracked tables (sessions, cache, authors)
				if (preg_match("/{$tbl_prefix}(authors|cache|forgotpass|sessions|tracker_activity|symphony_cart|search_index|search_index_entry_keywords|search_index_keywords|search_index_logs)/i", $query)) return true;
				// content updates in tbl_entries (includes tbl_entries_fields_*)
				if (preg_match('/^(insert|delete|update)/i', $query) && preg_match("/({$tbl_prefix}entries)/i", $query)) return true;
				// append query delimeter if it doesn't exist
				if (!preg_match('/;$/', $query)) $query .= ";";
	
				// Replace the table prefix in the query
				// This allows query execution on slave instances with different table prefix.
				// $query = str_replace($tbl_prefix,'tbl_',$query);
				
				if(Symphony::Configuration()->get("enabled", "database-migrations") == "1" && !($query == "") ){
						
					$newFileName = self::getNewFileName(self::getSavePath());
					$newFilePath = self::getSavePath() . "/" . $newFileName ; 
					
					//log the entire thing to file
					file_put_contents($newFilePath, $query, FILE_APPEND);
					
					//add to the item to the log
					self::appendLogItem($newFileName);
				}			
			}
			return true;
		}
		
		private function strMultiFind($headline, $fields) {
			$regexp = '/(' . implode('|',array_values($fields)) . ')/i';
			return (bool) preg_match($regexp, $headline);
		}
	}
	
?>