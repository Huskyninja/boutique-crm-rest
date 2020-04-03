<?php

	include __DIR__ . '/../lib/functions.php';

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');
	
	$log = 'logs/errorlog' . date('Y-m-d') . '.log';
	$app_name = 'em2sh';
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
	
	$sql = $db->prepare('SELECT * FROM sherpa_clients WHERE clientID=?');
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
		$postVars['PrimaryContactFirstName'] = trim($_POST['firstName']);
		$postVars['PrimaryContactFirstName'] = filter_var($postVars['PrimaryContactFirstName'], FILTER_SANITIZE_STRING);
		// $postVars['PrimaryContactFirstName'] = trim(htmlentities(strip_tags($_POST['firstName'])));
	} else {
		error_log("[" . date('c') . "] POST Error: firstName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['lastName'])) {
		$postVars['PrimaryContactLastName'] = trim($_POST['lastName']);
		$postVars['PrimaryContactLastName'] = filter_var($postVars['PrimaryContactLastName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: lastName not included in POST string.\n", 3, $log);
		exit();
	}

	// next gather all the non-required sherpa values from the post to assign them to the array
		
	if (isset($_POST['email'])) {
		$postVars['PrimaryContactEmail'] = trim($_POST['email']);
		$postVars['PrimaryContactEmail'] = filter_var($postVars['PrimaryContactEmail'], FILTER_SANITIZE_EMAIL);
	}
	
	if(isset($_POST['address1'])) {
		$postVars['PrimaryContactAddress1'] = trim($_POST['address1']);
		$postVars['PrimaryContactAddress1'] = filter_var($postVars['PrimaryContactAddress1'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['address2'])) {
		$postVars['PrimaryContactAddress2'] = trim($_POST['address2']);
		$postVars['PrimaryContactAddress2'] = filter_var($postVars['PrimaryContactAddress2'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['city'])) {
		$postVars['PrimaryContactCity'] = trim($_POST['city']);
		$postVars['PrimaryContactCity'] = filter_var($postVars['PrimaryContactCity'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['state'])) {
		$postVars['PrimaryContactState'] = trim($_POST['state']);
		$postVars['PrimaryContactState'] = filter_var($postVars['PrimaryContactState'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['zip'])) {
		$postVars['PrimaryContactPostalCode'] = trim($_POST['zip']);
		$postVars['PrimaryContactPostalCode'] = filter_var($postVars['PrimaryContactPostalCode'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['country'])) {
		$postVars['PrimaryContactCountry'] = trim($_POST['country']);
		$postVars['PrimaryContactCountry'] = filter_var($postVars['PrimaryContactCountry'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['phone'])) {
		$postVars['PrimaryContactHomePhone'] = trim($_POST['phone']);
		$postVars['PrimaryContactHomePhone'] = filter_var($postVars['PrimaryContactHomePhone'], FILTER_SANITIZE_STRING);
	}
	
	if(isset($_POST['fax'])) {
		$postVars['PrimaryContactFaxPhone'] = trim($_POST['fax']);
		$postVars['PrimaryContactFaxPhone'] = filter_var($postVars['PrimaryContactFaxPhone'], FILTER_SANITIZE_STRING);
	}
	
	if (isset($_POST['notes'])) {
		$community_id = trim($_POST['notes']);
		$community_id = filter_var($community_id, FILTER_SANITIZE_STRING);
	}
	
	// now fill in the blanks for the rest of sherpa's required fields
	
	$postVars['VendorName'] = 'Company Website';
	$postVars['SourceName'] = 'Company Website';
	$postVars['ReferralType'] = 1;
	$postVars['ReferralDate'] = date('Y-m-d');
	$postVars['AdvisorFirstName'] = '';
	$postVars['AdvisorLastName'] = '';
	$postVars['AdvisorEmail'] = '';
	$postVars['ResidentContactFirstName'] = '';
	$postVars['ResidentContactLastName'] = '';
	
	// finally set the resident contact to the resident contact
	
	$postVars['PrimaryContactResidentRelationship'] = 'Self';
	
	// convery the post vars into the parent array and then format to json
	
	$tempQuery = array('lead' => $postVars);
	$jsonQuery = json_encode($tempQuery);
	
	// ********* Development ********
	$url = 'https://test.sherpacrm.com/api/lead/create';
	
	// ********* Production ********
	// $url = 'https:/members.sherpacrm.com/api/lead/create';
	
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
			'Content-Length: ' . strlen($jsonQuery),
			'company: ' . $company_id,
			'community: ' . $community_id,
			'auth_token: '. $auth_token,
		)
	);
	
	sendDebugMail($mail_to, $app_name, 'WTF', $community_id);
	
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