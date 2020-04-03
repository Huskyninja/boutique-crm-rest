<?php

	include __DIR__ . '/../lib/functions.php';

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');
	
	$log = 'logs/errorlog' . date('Y-m-d') . '.log';
	$app_name = 'wp2y';
	$mail_to = 'someone@example.com';

	$postVars = array();
	
	if (isset($_POST['APIURL'])) {
		$postVars['APIURL'] = trim($_POST['APIURL']);
		$postVars['APIURL'] = filter_var($postVars['APIURL'], FILTER_SANITIZE_URL);
	} else {
		error_log("[" . date('c') . "] POST Error: APIURL not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['SourceName'])) {
		$postVars['SourceName'] = trim($_POST['SourceName']);
		$postVars['SourceName'] = filter_var($postVars['SourceName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: SourceName not included in POST string.\n", 3, $log);
		exit();
	}
	
	$postVars['ExtReference'] = strtotime("now");
	
	if (isset($_POST['PropertyCode'])) {
		$postVars['PropertyCode'] = trim($_POST['PropertyCode']);
		$postVars['PropertyCode'] = filter_var($postVars['PropertyCode'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: PropertyCode not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['DbUserName'])) {
		$postVars['DbUserName'] = trim($_POST['DbUserName']);
		$postVars['DbUserName'] = filter_var($postVars['DbUserName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: DbUserName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['DbPassword'])) {
		$postVars['DbPassword'] = trim($_POST['DbPassword']);
		$postVars['DbPassword'] = filter_var($postVars['DbPassword'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: DbPassword not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['DbName'])) {
		$postVars['DbName'] = trim($_POST['DbName']);
		$postVars['DbName'] = filter_var($postVars['DbName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: DbName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['Server'])) {
		$postVars['Server'] = trim($_POST['Server']);
		$postVars['Server'] = filter_var($postVars['Server'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: Server not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['Platform'])) {
		$postVars['Platform'] = trim($_POST['Platform']);
		$postVars['Platform'] = filter_var($postVars['Platform'], FILTER_SANITIZE_STRING);
	} else {
		$postVars['Platform'] = 'SQL Server';
	}
	
	if (isset($_POST['FirstName'])) {
		$postVars['FirstName'] = trim($_POST['FirstName']);
		$postVars['FirstName'] = filter_var($postVars['FirstName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: FirstName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['LastName'])) {
		$postVars['LastName'] = trim($_POST['LastName']);
		$postVars['LastName'] = filter_var($postVars['LastName'], FILTER_SANITIZE_STRING);
	} else {
		error_log("[" . date('c') . "] POST Error: LastName not included in POST string.\n", 3, $log);
		exit();
	}
	
	if (isset($_POST['Email'])) {
		$postVars['Email'] = trim($_POST['Email']);
		$postVars['Email'] = filter_var($postVars['Email'], FILTER_SANITIZE_EMAIL);
	}
	
	if (isset($_POST['HomePhone'])) {
		$postVars['HomePhone'] = trim($_POST['HomePhone']);
		$postVars['HomePhone'] = filter_var($postVars['HomePhone'], FILTER_SANITIZE_STRING);
	}
	
	$xml_data = '<?xml version="1.0" encoding="utf-8"?>';
	$xml_data .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
	$xml_data .= '<soap:Body>';
	$xml_data .= '<ImportGuest xmlns="YSI.Portal.SeniorHousing.WebServices">';
	$xml_data .= '<DbUserName>' . $postVars['DbUserName'] . '</DbUserName>';
	$xml_data .= '<DbPassword>' . $postVars['DbPassword'] . '</DbPassword>';
	$xml_data .= '<DbName>' . $postVars['DbName'] . '</DbName>';
	$xml_data .= '<Server>' . $postVars['Server'] . '</Server>';
	$xml_data .= '<Platform>' . $postVars['Platform'] . '</Platform>';
	$xml_data .= '<Xmldoc>';
	$xml_data .= '<Leads xmlns="">';
	$xml_data .= '<Lead>';
	$xml_data .= '<SourceName>' . $postVars['SourceName'] . '</SourceName>';
	$xml_data .= '<ExtReference>' . $postVars['ExtReference'] . '</ExtReference>';
	$xml_data .= '<PropertyCode>' . $postVars['PropertyCode'] . '</PropertyCode>';
	$xml_data .= '<FirstName>' . $postVars['FirstName'] . '</FirstName>';
	$xml_data .= '<LastName>' . $postVars['LastName'] . '</LastName>';
	if (isset($postVars['HomePhone'])) {
		$xml_data .= '<HomePhone>' . $postVars['HomePhone'] . '</HomePhone>';
	}
	if (isset($postVars['Email'])) {
		$xml_data .= '<Email>' . $postVars['Email'] . '</Email>';
	}
	$xml_data .= '</Lead>';
	$xml_data .= '</Leads>';
	$xml_data .= '</Xmldoc>';
	$xml_data .= '</ImportGuest>';
	$xml_data .= '</soap:Body>';
	$xml_data .= '</soap:Envelope>';
	
	// initalize cURL
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $postVars['APIURL']);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(       
			'Content-Type: text/xml',                        
			'SOAPAction="YSI.Portal.SeniorHousing.WebServices/ImportGuest"',
			'Content-Length: ' . strlen($xml_data),
		)
	);
	
	$result = curl_exec($ch);
    $error = curl_error($ch);
	
	$response = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	
	$yardi_sez = $result;

	if (curl_exec($ch) === false) {
		$log_output = "[" . date('c') . "] cURL Error: " . $error . ".\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'error', $log_output);
	} else {
		$log_output = "[" . date('c') . "] Notice: The following was data was posted to Yardi: " . $xml_data . ". Yardi returned " . $response . " with the details of " . $yardi_sez . ".\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'success', $log_output);
	}
	
	curl_close($ch);
	
?>