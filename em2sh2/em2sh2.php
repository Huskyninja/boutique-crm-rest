<?php

	include __DIR__ . '/../lib/functions.php';

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');
	
	$log = 'logs/errorlog' . date('Y-m-d') . '.log';
	$app_name = 'em2sh2';
	$mail_to = 'someone@example.com';

	$postVars = array();
	
	// $log_output = "[" . date('c') . "] Using development environment.\n";
	// error_log($log_output, 3, $log);
	// sendDebugMail($mail_to, $app_name, 'using dev', $log_output);
	
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
	
	// ********* Production ********
	// $db = new mysqli('xxx', 'xxx', 'xxx', 'xxx');
	
	// ******** Development ********
	$db = new mysqli('xxx', 'xxx', 'xxx', 'xxx');
	
	if ($db->connect_error) {
		$log_output = "[" . date('c') . "] Database Error: Unable to connect to database." . $db->connect_error . "\n";
		error_log($log_output, 3, $log);
		// sendDebugMail('database error', $log_output, $log);
		sendDebugMail($mail_to, $app_name, 'database error', $log_output);
		exit();
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$db->set_charset("utf8mb4");
	
	$clientKey_safe = mysqli_real_escape_string($db, $clientKey);
	
	$sql = $db->prepare('SELECT * FROM sherpa_2_clients WHERE clientID=?');
	$sql->bind_param('s', $clientKey_safe);
	$sql->execute();
	$result = $sql->get_result();
	
	$rows = $result->num_rows;
	
	if ($rows < 1) {
		$log_output = "[" . date('c') . "] Database Error: clientKey has returned an incorrect number of matching values. clientID = " . $clientKey . " rows = " . $rows . ".\n";
		error_log($log_output, 3, $log);
		$db->close();
		// sendDebugMail('database error', $log_output, $log);
		sendDebugMail($mail_to, $app_name, 'database error', $log_output);
		exit();
	}
	
	if ($rows > 1) {
		
		if (isset($_POST['memo'])) {
			
			$community_name = trim($_POST['memo']);
			$community_name = filter_var($community_name, FILTER_SANITIZE_STRING);
			
			$sql_bycomm = $db->prepare('SELECT * FROM sherpa_clients WHERE clientID=? AND communityName=?');
			$sql_bycomm->bind_param('ss', $clientKey_safe, $community_name);
			$sql_bycomm->execute();
			
			$result = $sql_bycomm->get_result();
			$rows = $result->num_rows;
			
			if ($rows < 1 || $rows > 1) {
				$log_output = "[" . date('c') . "] There is a Client ID and a Community Name, but an incorrect number of rows was returned. clientID = " . $clientKey_safe . " communityName = " . $community_name . " rows = " . $rows . ".\n";
				error_log($log_output, 3, $log);
				$db->close();
				sendDebugMail($mail_to, $app_name, 'database error', $log_output);
				exit();
			}			
			
		} else {
			$log_output = "[" . date('c') . "] More than one row was returned, but no community name was attached to the post. clientID = " . $clientKey_safe . " rows = " . $rows . "\n";
			error_log($log_output, 3, $log);
			$db->close();
			sendDebugMail($mail_to, $app_name, 'database error', $log_output);
			exit();
		}
		
	}
	
	$values = $result->fetch_array(MYSQLI_ASSOC);
	
	$company_name = $values['companyName'];
	$company_id = $values['companyID'];
	$community_id = $values['communityID'];
	$auth_token = $values['authToken'];

	$result->free();
	$db->close();
	
	// format the post vars
	// first, gather all the required sherpa values from the post to assign them to the array, and if they are empty take a dump

	if (isset($_POST['firstName'])) {
		$postVars['primaryContactFirstName'] = trim($_POST['firstName']);
		$postVars['primaryContactFirstName'] = filter_var($postVars['primaryContactFirstName'], FILTER_SANITIZE_STRING);
		// $postVars['PrimaryContactFirstName'] = trim(htmlentities(strip_tags($_POST['firstName'])));
	} else {
		error_log("[" . date('c') . "] POST Error: firstName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['lastName'])) {
		$postVars['primaryContactLastName'] = trim($_POST['lastName']);
		$postVars['primaryContactLastName'] = filter_var($postVars['primaryContactLastName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: lastName not included in POST string.\n", 3, $log);
		exit();
	}

	// next gather all the non-required sherpa values from the post to assign them to the array
		
	if (isset($_POST['email'])) {
		$postVars['primaryContactEmail'] = trim($_POST['email']);
		$postVars['primaryContactEmail'] = filter_var($postVars['primaryContactEmail'], FILTER_SANITIZE_EMAIL);
	}
	
	if(isset($_POST['address1'])) {
		$postVars['primaryContactAddress1'] = trim($_POST['address1']);
		$postVars['primaryContactAddress1'] = filter_var($postVars['primaryContactAddress1'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['address2'])) {
		$postVars['primaryContactAddress2'] = trim($_POST['address2']);
		$postVars['primaryContactAddress2'] = filter_var($postVars['primaryContactAddress2'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['city'])) {
		$postVars['primaryContactCity'] = trim($_POST['city']);
		$postVars['primaryContactCity'] = filter_var($postVars['primaryContactCity'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['state'])) {
		$postVars['primaryContactState'] = trim($_POST['state']);
		$postVars['primaryContactState'] = filter_var($postVars['primaryContactState'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['zip'])) {
		$postVars['primaryContactPostalCode'] = trim($_POST['zip']);
		$postVars['primaryContactPostalCode'] = filter_var($postVars['primaryContactPostalCode'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['country'])) {
		$postVars['primaryContactCountry'] = trim($_POST['country']);
		$postVars['primaryContactCountry'] = filter_var($postVars['primaryContactCountry'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['phone'])) {
		$postVars['primaryContactHomePhone'] = trim($_POST['phone']);
		$postVars['primaryContactHomePhone'] = filter_var($postVars['primaryContactHomePhone'], FILTER_SANITIZE_STRING);
	}
	
	// now fill in the blanks for the rest of sherpa's required fields
	
	$postVars['vendorName'] = 'Company Website';
	$postVars['sourceCategory'] = 'Internet';
	$postVars['sourceName'] = 'Internet';
	$postVars['referralDateTime'] = date('Y-m-d\zH:i:s');
	$postVars['residentContactFirstName'] = '';
	$postVars['residentContactLastName'] = '';
	
	// finally set the resident contact to the resident contact
	
	$postVars['primaryContactResidentRelationship'] = 'self';
	
	// convery the post vars into the parent array and then format to json
	
	$tempQuery = $postVars;
	$jsonQuery = json_encode($tempQuery);
	
	// ********* Development ********
	$base_url = 'https://sandbox.sherpacrm.com/v1';
	
	// ********* Production ********
	// $base_url = 'https:/members.sherpacrm.com/v1';
	
	$url = $base_url . '/companies/' . $company_id . '/communities/' . $community_id . '/leads';
	
	// initalize cURL
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonQuery);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	// set the cURL headers
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
			'Content-Type: application/json',
			'Authorization: Bearer ' . $auth_token,
			'Content-Length: ' . strlen($jsonQuery),
		)
	);
	
	// sendDebugMail($mail_to, $app_name, 'WTF', $community_id);
	
	$result = curl_exec($ch);
    $error = curl_error($ch);

	$response = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	if ($response === 500) {
		preg_match("/\<title.*\>(.*)\<\/title\>/isU", $result, $find_title);
		$sherpa_sez = trim($find_title[1]);
	} else {
		$sherpa_sez = $result;
	}
	
	if (curl_exec($ch) === false) {
		$log_output = "[" . date('c') . "] cURL Error: " . $error . ".\n";
		error_log($log_output, 3, $log);
		// sendDebugMail('error', $log_output, $log);
		sendDebugMail($mail_to, $app_name, 'error', $log_output);
	} else {
		$log_output = "[" . date('c') . "] Notice: The following was data was posted to " . $company_name . "'s Sherpa account ";
		if (isset($community_name) && !empty($community_name)) {
			$log_output .= "under the " . $community_name . " community ";
		}
		$log_output .= "- " .  $jsonQuery . ". Sherpa returned " . $response . " with the details of " . $sherpa_sez . ".\n";
		error_log($log_output, 3, $log);
		// sendDebugMail('success', $log_output, $log);
		sendDebugMail($mail_to, $app_name, 'success', $log_output);
	}
	
	curl_close($ch);
	
?>