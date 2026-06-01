<?php
class eventclass_accounting_rapid_users  extends TableEventsBase {
	
	function init() {
		$this->events = array(
	'CopyOnLoad' => true 
);
		$this->fieldValues = array(
	'filterLimit' => array(
		 
	),
	'mapIcon' => array(
		 
	),
	'viewCustom' => array(
		 
	),
	'lookupWhere' => array(
		 
	),
	'viewFileText' => array(
		 
	),
	'defaultValue' => array(
		 
	),
	'autoUpdateValue' => array(
		 
	),
	'uploadFolder' => array(
		 
	),
	'viewPluginInit' => array(
		 
	),
	'editPluginInit' => array(
		 
	) 
);
			}
		function CopyOnLoad( &$values, &$where, $pageObject ) {
		// Place event code here.
// Use "Add Action" button to add code snippets.

// --- SECURITY TEST START ---
// Kukuha ng input sa URL (Halimbawa: ?user=admin' OR '1'='1)
$userInput = $_GET['user']; 

// MALI: Diretsong idinikit ang input sa SQL string (SQL Injection Vulnerability)
$sql = "SELECT * FROM users WHERE username = '" . $userInput . "'"; 

// Patatakbuhin ang maruming query
CustomQuery($sql);
// --- SECURITY TEST END ---
		;
		
	}
		

}


?>