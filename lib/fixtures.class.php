<?php

	require_once(EXTENSIONS . "/database_migrations/lib/utils.class.php");
	require_once(EXTENSIONS . '/symql/lib/class.symql.php');
	
	require_once(TOOLKIT . "/class.entrymanager.php");
	require_once(TOOLKIT . "/class.entry.php");
	
	require_once(TOOLKIT . "/class.fieldmanager.php");
	
	Class Database_Migrations_Fixtures {
	
		public static $SAVE_PATH = "./workspace/fixture-data/";
	
		public static function loadTestFixture($section, $data) {
			$query = new SymQLQuery();
			$query->select("*")->from($section);
			try {
				$rawResult = SymQL::run($query, SymQL::RETURN_ARRAY);
				// algorithm to convert the result into a more useable format
				$entries = array();
				foreach($rawResult["entries"] as $entry) {
					$newEntry = array();
					$newEntry["id"] = $entry["entry"]["_id"];
					$newEntry["fields"] = array();
					foreach($entry["entry"] as $k=>$v) {
						if($k!="_id") {
							$newEntry["fields"][$k] = $v["value"]; 
						}
					}
					$entries[] = $newEntry;
				}
				$serializedString = serialize($entries);
				file_put_contents(self::$SAVE_PATH . "/" . $section . ".data", $serializedString);
				self::truncateSection($section);				
				self::importData($section, $data);				
			}
			catch(Exception $e) {echo($e->getMessage());}			
		}
	
	
	
		public static function unloadTestFixture($section) {
		
			self::truncateSection($section);
			
			$serializedString = file_get_contents(self::$SAVE_PATH . "/" . $section . ".data");
			$entries = unserialize($serializedString);
			
			self::importData($section, $entries);
		
		}
		
		private static function getSectionIdFromName($sectionName) {
			foreach(SectionManager::fetch() as $section) {
				if($section->get("name") == $sectionName) {
					return $section->get("id");
				}
			}
		}
	
		private static function importData($section, $entries, $overwrite = false) {
			$sectionId = self::getSectionIdFromName($section);
			foreach($entries as $entry) {
				if(!$overwrite) {
					unset($entry["id"]);
				}					
				$newEntry = new Entry($this);
				$newEntry->set('section_id', $sectionId);
				foreach($entry["fields"] as $fieldName => $value) {
					$fieldId = FieldManager::fetchFieldIDFromElementName($fieldName, $sectionId);
					$fieldObj = FieldManager::fetch($fieldId, $sectionId);		
					// some empty stuff to pass references into
					$strA="";$strB="";$strC="";
					$formattedValue = $fieldObj->processRawFieldData($value, $strA, $strB, $strC);			
					$newEntry->setData(FieldManager::fetchFieldIDFromElementName($fieldName, $sectionId), $formattedValue);
				}
				$newEntry->assignEntryId();
				$newEntry->commit();
			}
		
		}
		
		public static function truncateSection($section) {
			$query = new SymQLQuery();
			$query->select("*")->from($section);
			try {
				$rawResult = SymQL::run($query, SymQL::RETURN_ARRAY);
				$entryIds = array();
				foreach($rawResult["entries"] as $entry) {
					$entryIds[] = $entry["entry"]["_id"];
				}				
				EntryManager::delete($entryIds);	
			}
			catch(Exception $e) {}
		}
	
	}

?>