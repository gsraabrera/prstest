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
		// // Place event code here.
// // Use "Add Action" button to add code snippets.

// // --- SECURITY TEST START ---
// // Kukuha ng input sa URL (Halimbawa: ?user=admin' OR '1'='1)
$userInput = $_GET['user']; 

// MALI: Diretsong idinikit ang input sa SQL string (SQL Injection Vulnerability)
$sql = "SELECT * FROM users WHERE username = '" . $userInput . "'"; 

// // Patatakbuhin ang maruming query
// CustomQuery($sql);
// // --- SECURITY TEST END ---

$userInput = $_GET['user'];
$userStatus = 'Active';

// Ligtas, madaling basahin, at walang concatenation (.)
$sql = "SELECT * FROM users WHERE username = :1 AND status = :2";

// Ipinapasa ang mga variables ayon sa pagkakasunod-sunod ng numero
$rs = DB::Query($sql, array($userInput, $userStatus));


$userInput = $_GET['user'];
$userStatus = 'Active';
$userRole = 'Admin';

// Mas madaling i-maintain kahit abutin ng 100 lines ang query mo
$sql = "SELECT * FROM users 
        WHERE username = :username 
          AND status = :status 
          AND role = :role";

// Gumamit ng associative array para itugma ang mga pangalan
$rs = DB::Query($sql, array(
    ":username" => $userInput,
    ":status"   => $userStatus,
    ":role"     => $userRole
));


// ==========================================
// TEST 1: Sadyang XSS (Cross-Site Scripting)
// ==========================================
$userInputXSS = $_GET['search'];
echo "You searched for: " . $userInputXSS; // MALI: Diretsong nag-echo ng $_GET nang walang htmlspecialchars()

// ==========================================
// TEST 2: Sadyang Command Injection
// ==========================================
$targetIP = $_POST['ip'];
shell_exec("ping -c 4 " . $targetIP); // MALI: Diretsong idinikit ang input sa system command
		;
		
	}
		

}


?>