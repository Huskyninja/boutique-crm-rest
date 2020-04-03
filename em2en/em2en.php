<?php

	// trying to deal with the timout issue from emfluence & curl taking so long - respond to emflunce right out of the gate "we got it"
	echo 'SUCCESS';	
	ignore_user_abort(true);
	set_time_limit(0);
	header('HTTP/1.0 200 OK', true, 200);
	flush();

	include __DIR__ . '/../lib/functions.php';

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');
	
	$log = 'logs/errorlog' . date('Y-m-d') . '.log';
	$app_name = 'em2en';
	$mail_to = 'someone@example.com';

	$postVars = array();
	
	if (isset($_POST['clientKey'])) {
		$clientKey = trim($_POST['clientKey']);
	} else {
		$log_output = "[" . date('c') . "] POST Error: clientKey not included in POST string.\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'post error', $log_output);
		exit();
	}

	// retrieve the company info from the DB based on the clientKey
	// https://websitebeaver.com/prepared-statements-in-php-mysqli-to-prevent-sql-injection
	
	// ******** Development ********
	$db = new mysqli('xxx', 'xxx', 'xxx', 'xxx');
	
	// ********* Production ********
	// $db = new mysqli('xxx', 'xxx', 'xxx', 'xxx');
	
	if ($db->connect_error) {
		$log_output = "[" . date('c') . "] Database Error: Unable to connect to database." . $db->connect_error . "\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'database error', $log_output);
		exit();
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$db->set_charset("utf8mb4");
	
	$clientKey_safe = mysqli_real_escape_string($db, $clientKey);
	
	$sql = $db->prepare('SELECT * FROM enquire_clients WHERE clientID=?');
	$sql->bind_param('s', $clientKey_safe);
	$sql->execute();
	$result = $sql->get_result();
	
	$rows = $result->num_rows;
	
	if ($rows < 1 || $rows > 1) {
		$log_output = "[" . date('c') . "] Database Error: clientKey has returned an incorrect number of matching values. clientID = " . $clientKey_safe . " rows = " . $rows . ".\n";
		error_log($log_output, 3, $log);
		$db->close();
		
		sendDebugMail($mail_to, $app_name, 'database error', $log_output);
		exit();
	}
	
	$values = $result->fetch_array(MYSQLI_ASSOC);
	
	$company_name = $values['companyName'];
	$subscription_key = $values['subscriptionKey'];

	$result->free();
	$db->close();
	
	// format the post vars
	// first, gather all the required enquire values from the post to assign them to the array, and if they are empty take a dump
	
	if (isset($_POST['firstName'])) {
		$postVars['FirstName'] = trim($_POST['firstName']);
		$postVars['FirstName'] = filter_var($postVars['FirstName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: firstName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['lastName'])) {
		$postVars['LastName'] = trim($_POST['lastName']);
		$postVars['LastName'] = filter_var($postVars['LastName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: lastName not included in POST string.\n", 3, $log);
		exit();
	}

	// next gather all the non-required sherpa values from the post to assign them to the array
		
	if (isset($_POST['email'])) {
		$postVars['Email'] = trim($_POST['email']);
		$postVars['Email'] = filter_var($postVars['Email'], FILTER_SANITIZE_EMAIL);
	}
	
	if(isset($_POST['address1'])) {
		$postVars['AddressLine1'] = trim($_POST['address1']);
		$postVars['AddressLine1'] = filter_var($postVars['AddressLine1'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['address2'])) {
		$postVars['AddressLine2'] = trim($_POST['address2']);
		$postVars['AddressLine2'] = filter_var($postVars['AddressLine2'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['city'])) {
		$postVars['City'] = trim($_POST['city']);
		$postVars['City'] = filter_var($postVars['City'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['state'])) {
		$postVars['State'] = trim($_POST['state']);
		$postVars['State'] = filter_var($postVars['State'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['zip'])) {
		$postVars['ZipCode'] = trim($_POST['zip']);
		$postVars['ZipCode'] = filter_var($postVars['ZipCode'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['phone'])) {
		$postVars['HomePhone'] = trim($_POST['phone']);
		$postVars['HomePhone'] = filter_var($postVars['HomePhone'], FILTER_SANITIZE_STRING);
	}
	
	if (isset($_POST['notes'])) {
		$postVars['CommunityName'] = trim($_POST['notes']);
		$postVars['CommunityName'] = filter_var($postVars['CommunityName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: notes / CommunityName not included in POST string.\n", 3, $log);
		exit();
	}
	
	$postVars['IndividualTypeName'] = 'Prospect';
	// ActivityTypeName appears to be set to a list of options on the enquire side, so we won't set it here
	// $postVars['ActivityTypeName'] = 'Emfluence Web Form';
	
	// finally set the resident contact to the resident contact
	
	$postVars['ContactRelationship'] = 'Contact';
	
	// convery the post vars into the parent array and then format to json

	$jsonQuery = json_encode($postVars);
	
	$url = 'https://api2.enquiresolutions.com/2/Individual/';
	
	// initalize cURL
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonQuery);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	// set the cURL headers
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
			'Content-Type: application/json',                        
			'Content-Length: ' . strlen($jsonQuery),
			'Ocp-Apim-Subscription-Key: ' . $subscription_key,
		)
	);
	
	$result = curl_exec($ch);
    $error = curl_error($ch);
	
	$response = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	
	$enquire_sez = $result;
	
	if (curl_exec($ch) === false) {
		$log_output = "[" . date('c') . "] cURL Error: " . $error . ". \n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'cURL Error', $log_output);
	} else if ($response === 400) {
		$log_output = "[" . date('c') . "] Post Error: The following was data was posted to " . $company_name . "'s enquire account - " .  $jsonQuery . ". enquire returned " . $response . " with the details of " . $enquire_sez . ". \n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, '400 Error', $log_output);
	} else if ($response === 500) {
		$log_output = "[" . date('c') . "] Post Error: The following was data was posted to " . $company_name . "'s enquire account - " .  $jsonQuery . ". enquire returned " . $response . " with the details of " . $enquire_sez . ". \n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, '500 Error', $log_output);
	} else {
		$log_output = "[" . date('c') . "] Notice: The following was data was posted to " . $company_name . "'s enquire account - " .  $jsonQuery . ". enquire returned " . $response . " with the details of " . $enquire_sez . ". \n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'Success', $log_output);
	}
	
	curl_close($ch);

?>