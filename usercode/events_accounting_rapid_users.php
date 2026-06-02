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
$userInput = $_GET['user']; 

// Vulnerable
$sql = "SELECT * FROM users WHERE username = '" . $userInput . "'"; 


// CustomQuery($sql);
// // --- SECURITY TEST END ---

$userInput = $_GET['user'];
$userStatus = 'Active';

// safe
$sql = "SELECT * FROM users WHERE username = :1 AND status = :2";

// Ipinapasa ang mga variables ayon sa pagkakasunod-sunod ng numero
$rs = DB::Query($sql, array($userInput, $userStatus));


$userInput = $_GET['user'];
$userStatus = 'Active';
$userRole = 'Admin';

// safe
$sql = "SELECT * FROM users 
        WHERE username = :username 
          AND status = :status 
          AND role = :role";

// safe
$rs = DB::Query($sql, array(
    ":username" => $userInput,
    ":status"   => $userStatus,
    ":role"     => $userRole
));


// ==========================================
// TEST 1:  XSS (Cross-Site Scripting)
// ==========================================
$userInputXSS = $_GET['search'];
echo "You searched for: " . $userInputXSS; // wrong: direct echo $_GET without htmlspecialchars()

// ==========================================
// TEST 2: Sadyang Command Injection
// ==========================================
$targetIP = $_POST['ip'];
shell_exec("ping -c 4 " . $targetIP); // MALI: Diretsong idinikit ang input sa system command
		;
		
	}
		

}


?>