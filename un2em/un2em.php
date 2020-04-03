<?php

	echo '200';

	include __DIR__ . '/../lib/functions.php';

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');
	
	$log = 'logs/errorlog' . date('Y-m-d') . '.log';
	$app_name = 'un2en';
	$mail_to = 'someone@example.com';

	$postVars = array();
	
	if (isset($_POST['page_id'])) {
		$page_id = trim($_POST['page_id']);
	} else {
		$log_output = "[" . date('c') . "] POST Error: Page ID not included in POST string.\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'post error', $log_output);
		exit();
	}
	
	if (isset($_POST['page_url'])) {
		$page_url = $_POST['page_url'];
		$page_url = filter_var($page_url, FILTER_SANITIZE_URL);
	} else {
		$page_url = 'unknown url';
	}
	
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
	
	$page_id_safe = mysqli_real_escape_string($db, $page_id);
	
	$sql = $db->prepare('SELECT * FROM unbounce_clients WHERE page_id=?');
	$sql->bind_param('s', $page_id_safe);
	$sql->execute();
	$result = $sql->get_result();
	
	$rows = $result->num_rows;
	
	if ($rows < 1 || $rows > 1) {
		$log_output = "[" . date('c') . "] Database Error: Page ID has returned an incorrect number of matching values. page_id = " . $page_id_safe . " rows = " . $rows . ".\n";
		error_log($log_output, 3, $log);
		$db->close();
		sendDebugMail($mail_to, $app_name, 'database error', $log_output);
		exit();
	}
	
	$values = $result->fetch_array(MYSQLI_ASSOC);
	
	$company_name = $values['client_name'];
	$auth_token = $values['auth_token'];
	$group_id = $values['group_id'];
	
	$result->free();
	$db->close();
	
	// https://documentation.unbounce.com/hc/en-us/articles/203510044-Using-a-Webhook
	
	function stripslashes_deep($value) {
		$value = is_array($value) ?
			array_map('stripslashes_deep', $value) :
		stripslashes($value);
		return $value;
	}
	
	if (get_magic_quotes_gpc()) {
		$unescaped_post_data = stripslashes_deep($_POST);
	} else {
		$unescaped_post_data = $_POST;
	}

	$form_data = json_decode($unescaped_post_data['data_json']);
	
	// end https://documentation.unbounce.com/hc/en-us/articles/203510044-Using-a-Webhook
	
	// email is required
	if (isset($form_data->email[0])) {
		$postVars['email'] = $form_data->email[0];
		$postVars['email'] = filter_var($postVars['email'], FILTER_SANITIZE_EMAIL);
	} else {
		error_log("[" . date('c') . "] POST Error: email not included in JSON string.\n", 3, $log);
		exit();
	}
	
	
	if (isset($form_data->name[0])) {
		$postVars['firstName'] = $form_data->name[0];
		$postVars['firstName'] = filter_var($postVars['firstName'], FILTER_SANITIZE_STRING);
	}
	
	if (isset($form_data->phone[0])) {
		$postVars['phone'] = $form_data->phone[0];
		$postVars['phone'] = filter_var($postVars['phone'], FILTER_SANITIZE_STRING);
	}
	
	if (isset($form_data->notes[0])) {
		$postVars['notes'] = $form_data->notes[0];
		$postVars['notes'] = filter_var($postVars['notes'], FILTER_SANITIZE_STRING);
	}
	
	$postVars['groupIDs'] = array($group_id);
	
	// convery the post vars into the parent array and then format to json
	
	$jsonQuery = json_encode($postVars);
	
	$url = 'https://api.emailer.emfluence.com/v1/contacts/save';
	
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
			'Authorization: ' . $auth_token,
			'Content-Type: application/json',
		)
	);
	
	$result = curl_exec($ch);
    $error = curl_error($ch);
	
	$response = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	
	$emfluence_sez = $result;
	
	if (curl_exec($ch) === false) {
		$log_output = "[" . date('c') . "] cURL Error: " . $error . ".\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'error', $log_output);
	} else {
		$log_output = "[" . date('c') . "] Notice: The following was data was posted from " . $page_url . " to " . $company_name . "'s Emfluence account - " .  $jsonQuery . ". Emfluence returned " . $response . " with the details of " . $emfluence_sez . ".\n";
		error_log($log_output, 3, $log);
		sendDebugMail($mail_to, $app_name, 'success', $log_output);
	}
	
	curl_close($ch);

?>