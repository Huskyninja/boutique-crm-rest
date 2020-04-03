<?php

	/**
	 * @file
	 *   Script for sending Enquire prospects from their v3 API to Emfluence's /contacts/save endpoint
	 *
	 * This script can be updated to connect different customer / community groups.
	 * Global variables are used to store these values can be updated as follows:
	 *
	 *  $GLOBALS['app_name']
	 *    @string
	 *      This value is used for reporting.
	 *  $GLOBALS['mail_to']
	 *    @string
	 *      This value is used for reporting. For multiple addresses, delineate with a comma.
	 *  $GLOBALS['enquire_key']
	 *    @string
	 *      The development key to access the Enquire v3 API. Requires subscription from Enquire.
	 *  $GLOBALS['enquire_portal_id']
	 *    @string
	 *      The customer's enquire id. Provided by Enquire.
	 *  $GLOBALS['emfluence_key']
	 *    @string
	 *      The API key for the Emfluence customer. Provided by Emfluence.
	 *  $GLOBALS['emfluence_group_ids']
	 *    @array
	 *      Mapping for Enquire Community ID (as key) to the Emfluence Group ID (as value). Can be left as an empty array?
	 *      Multiple Group ID value fields should be comma delineated.
	 *
	 * Do not adjust any of these $GLOBAL values unless absolutely necessary. These values are used to control
	 * the API endpoints for both Enquire and Emfluence. Please refer to their respective documentation.
	 *
	 *  $GLOBALS['enquire_new_url']
	 *    @string
	 *      Creates a list of new Enquire prospects that is used as the primary list. More information:
	 *      https://developer.enquiresolutions.com/docs/services/55c386fcdfba5605f00bcdef/operations/individual-new?
	 *  $GLOBALS['enquire_individual_url']
	 *    @string
	 *      Used to gather user details based on the list of new Enquire prospects. More information:
	 *      https://developer.enquiresolutions.com/docs/services/55c386fcdfba5605f00bcdef/operations/56c76e859cfde21ef89f6f09?
	 *  $GLOBALS['enquire_case_url']
	 *    @string
	 *      Used to gather full case information from the case id gathered from the user details list. More information:
	 *      https://developer.enquiresolutions.com/docs/services/55c386fcdfba5605f00bcdef/operations/569543e8dfba5620c0677b75?
	 *  $GLOBALS['emfluence_url']
	 *    @string
	 *      The Emfluence endpoint used to create leads. More information:
	 *      https://apidocs.emailer.emfluence.com/v1/endpoints/contacts/save
	 */

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');
	
	// set globals
	
	// app globals - please do not adjust $GLOBALS['log'] unless necessary
	$GLOBALS['app_name'] = 'Queue-Enflusher';
	$GLOBALS['log'] = 'logs/errorlog' . date('Y-m-d') . '.log';
	$GLOBALS['mail_to'] = 'someone@example.com';

	// enquire globals
	$GLOBALS['enquire_key'] = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
	$GLOBALS['enquire_portal_id'] = '111';
	// enquire endpoints - new
	$GLOBALS['enquire_new_url'] = 'https://api2.enquiresolutions.com/v3/individual/new';
	// enquire endpoints - individual
	$GLOBALS['enquire_individual_url'] = 'https://api2.enquiresolutions.com/v3/Individual/';
	// enquire endpoints - case
	$GLOBALS['enquire_case_url'] = 'https://api2.enquiresolutions.com/v3/Case/';
	
	// emfluence globals
	$GLOBALS['emfluence_key'] = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
	$GLOBALS['emfluence_url'] = 'https://api.emailer.emfluence.com/v1/contacts/save';
	
	$GLOBALS['emfluence_group_ids'] = array(
		'1111' => '11111',
		'2222' => '22222', 
		'3333' => '33333',
	);
	
	// start
	// get the set of "new" prospect entries in queue
	
	// construct the full "new" endpoint
	$new_url = $GLOBALS['enquire_new_url'] . '?PortalId=' . $GLOBALS['enquire_portal_id'];
	
	// GET the "new" prospect list w curl
	$new_ch = curl_init();
	curl_setopt($new_ch, CURLOPT_URL, $new_url);
	curl_setopt($new_ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($new_ch, CURLOPT_HTTPHEADER, array(
		'Ocp-Apim-Subscription-Key: ' . $GLOBALS['enquire_key'],
	));
	
	$new_details_response = curl_exec($new_ch);
	$new_http_status = curl_getinfo($new_ch, CURLINFO_HTTP_CODE);
	
	// handle curl errors
	// since this is the main connection, if there are errors we don't have a list so just die
	if (curl_errno($new_ch)) {
		$message = 'CURL ERROR FOR NEW DATA: ' . curl_error($new_ch);
		sendLogMessage($message);
		logToFile($message);
		sendDebugMail('CURL ERROR FOR NEW DATA', $message);
		curl_close($new_ch);
		die();
	}
	
	// handle http errors
	// since this is the main connection, if there are errors we don't have a list so just die
	if ($new_http_status != '200') {
		$message = 'Unable to connect to New endpoint. Curl returned ' . $new_http_status . ' ' . $new_details_response;
		sendLogMessage($message);
		logToFile($message);
		sendDebugMail($new_http_status, $message);
		curl_close($new_ch);
		die();
	}
	
	curl_close($new_ch);
	
	// parse the "new" list array from the json
	$new_details = json_decode($new_details_response, true);

	// reset the operational array base since it is layered and layered
	$new_users = $new_details['result']['individuals'];
	
	// set the counters for reporting
	$q = 0;
	$errors = 0;
	
	// "init" the killable arrays
	$group_ids = array();
	$quick_query = array();
	
	// loop through all the users from the "new" list
	foreach ($new_users as $new_user) {
		
		// check to make sure they have an ID, and if they do not set and error & hope that all their info is in the "new" list
		if (isset($new_user['individualid']) && !empty($new_user['individualid'])) {
			
			$user_detail = enflusher_get_user_details($new_user['individualid']);
			
			if (isset($user_detail['result']['individuals']['casenumber']) && !empty($user_detail['result']['individuals']['casenumber'])) {
				
				$case_details = enflusher_get_case_details($user_detail['result']['individuals']['casenumber']);
				
			} else {
				$message = 'Expected an Case Number from the list of user details, but got nothing.';
				sendLogMessage($message);
				logToFile($message);
				$errors = $errors + 1;
			}
			
		} else if (isset($new_user[2]['casenumber']) && !empty($new_user[2]['casenumber'])) {
			
			$case_details = enflusher_get_case_details($new_user[2]['casenumber']);
			
		} else {
			$message = 'Expected an Individual ID from the list of new users, but got nothing.';
			sendLogMessage($message);
			logToFile($message);
			$errors = $errors + 1;
		}
		
		// if the user details returned with an error, increment the counter
		// handling of errors is done in populating the emfluence query array
		if ($user_detail === false) {
			$errors = $errors + 1;
		}
		
		// if the case details returned with an error, increment the counter
		// handling of errors is done in populating the emfluence query array
		if ($case_details === false) {
			$errors = $errors + 1;
		}
		
		// reset the emfluence query array
		unset($quick_query);
		$quick_query = array();
		
		/*
		 *
		 * populate the emfluence query array
		 *
		 * if a value is empty or not set (by the primary and secondary value), the emfluence query value will be an empty string
		 * so it is possible to send emfluence a query string with no values set, and this will process without issue
		 * done to ensure that the system does not blow up with one bad query - emfluence simply rejects the query if it doesn't meet requirements
		 * we could kill the individual POST if no email is set in the query array (as emfluence requirers an email value)
		 *
		*/
		
		// set emfluence firstName
		if (isset($case_details['cases'][0]['Individuals'][1]['FirstName']) && !empty($case_details['cases'][0]['Individuals'][1]['FirstName'])) {
			$quick_query['firstName'] = $case_details['cases'][0]['Individuals'][1]['FirstName'];
		} else if (isset($new_user['properties'][1]['value']) && !empty($new_user['properties'][1]['value'])) {
			$quick_query['firstName'] = $new_user['properties'][1]['value'];
		} else {
			$quick_query['firstName'] = '';
		}
		
		// set emfluence lastName
		if (isset($case_details['cases'][0]['Individuals'][1]['LastName']) && !empty($case_details['cases'][0]['Individuals'][1]['LastName'])) {
			$quick_query['lastName'] = $case_details['cases'][0]['Individuals'][1]['LastName'];
		} else if (isset($new_user['properties'][3]['value']) && !empty($new_user['properties'][3]['value'])) {
			$quick_query['lastName'] = $new_user['properties'][3]['value'];
		} else {
			$quick_query['lastName'] = '';
		}
		
		// set emfluence email
		if(isset($case_details['cases'][0]['Individuals'][1]['Email']) && !empty($case_details['cases'][0]['Individuals'][1]['Email'])) {
			$quick_query['email'] = $case_details['cases'][0]['Individuals'][1]['Email'];
		} else if (isset($user_detail['result']['individuals']['properties'][7]['value']) && !empty($user_detail['result']['individuals']['properties'][7]['value'])) {
			$quick_query['email'] = $user_detail['result']['individuals']['properties'][7]['value'];
		} else {
			$quick_query['email'] = '';
		}
		
		// set emfluence phone
		if (isset($case_details['cases'][0]['Individuals'][1]['HomePhoneFormat']) && !empty($case_details['cases'][0]['Individuals'][1]['HomePhoneFormat'])) {
			$quick_query['phone'] = $case_details['cases'][0]['Individuals'][1]['HomePhoneFormat'];
		} else if (isset($user_detail['result']['individuals']['properties'][4]['value']) && !empty($user_detail['result']['individuals']['properties'][4]['value'])) {
			$quick_query['phone'] = $user_detail['result']['individuals']['properties'][4]['value'];
		} else {
			$quick_query['phone'] = '';
		}
		
		// set emfluence community ids
		if (isset($user_detail['result']['individuals']['community'][0]['Id']) && !empty($user_detail['result']['individuals']['community'][0]['Id'])) {
			
			$community_id = $user_detail['result']['individuals']['community'][0]['Id'];
			
			unset($group_ids);
			$group_ids = array();
			
			$emfluence_group_ids = $GLOBALS['emfluence_group_ids'];
			
			foreach ($emfluence_group_ids as $key => $value) {
				
				if ($community_id == $key) {
					$group_ids[] = $value;
				}
				
			}
			

			
			$quick_query['groupIDs'] = $group_ids;
			
		}
		
		// construct the emfluence json
		$json_query = json_encode($quick_query);
		
		// POST to emfluence the json using curl
		$emfluence_ch = curl_init();
		curl_setopt($emfluence_ch, CURLOPT_URL, $GLOBALS['emfluence_url']);
		curl_setopt($emfluence_ch, CURLOPT_POSTFIELDS, $json_query);
		curl_setopt($emfluence_ch, CURLOPT_POST, 1);
		curl_setopt($emfluence_ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($emfluence_ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($emfluence_ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($emfluence_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($emfluence_ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($emfluence_ch, CURLOPT_HTTPHEADER, array(   
			'Authorization: '. $GLOBALS['emfluence_key'],
			'Content-Type: application/json'                        
		));
		
		$emfluence_response = curl_exec($emfluence_ch);
		$emfluence_http_status = curl_getinfo($emfluence_ch, CURLINFO_HTTP_CODE);
		
		// handle curl errors for emfluence
		if (curl_errno($emfluence_ch)) {
			$message = 'CURL ERROR SENDING ENTRY TO EMFLUENCE: ' . curl_error($emfluence_ch) . ' Failed to send the following data: ' . $json_query;
			sendLogMessage($message);
			logToFile($message);
			sendDebugMail('CURL ERROR SENDING ENTRY TO EMFLUENCE', $message);
			curl_close($emfluence_ch);
			$errors = $errors + 1;
		}
		
		// handle http errors from emfluence
		if ($emfluence_http_status != '200') {
			$message = 'Unable to connect Emfluence. Curl returned ' . $emfluence_http_status . ' ' . $emfluence_response . ' Failed to send the following data: ' . $json_query;
			sendLogMessage($message);
			logToFile($message);
			sendDebugMail($emfluence_http_status, $message);
			curl_close($emfluence_ch);
			$errors = $errors + 1;
		}
		
		curl_close($emfluence_ch);
		
		// set the log messages
		$message = 'The following was posted to emfluence ' . $json_query;
		sendLogMessage($message);
		logToFile($message);
		$message = 'Emfluence responded with the following: ' . $emfluence_response;
		sendLogMessage($message);
		logToFile($message);

		// increment the loop counter
		$q = $q + 1;
		// echo $q . '<br>';
		
	}
	
	// set the end messages
	$message = 'Finished processing ' . $q . ' records. There were ' . $errors . ' errors reported.';
	sendLogMessage($message);
	logToFile($message);
	sendDebugMail('processing finished', $message);
	
	// print to screen so we can ensure a 200 if needed
	echo $message;
	
	/*
	 * Get user details from enfluence.
	 *
	 * @param string
	 *   The individual id.
	 *
	 * @returns array
	 *   The user details.
	 *
	 */
	function enflusher_get_user_details($user_id) {
		
		// construct the full user details endpoint
		$user_url = $GLOBALS['enquire_individual_url'] . $user_id . '?PortalId=' . $GLOBALS['enquire_portal_id'];
		
		// connect with curl and GET the user detials
		$user_ch = curl_init();
		curl_setopt($user_ch, CURLOPT_URL, $user_url);
		curl_setopt($user_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($user_ch, CURLOPT_HTTPHEADER, array(
			'Ocp-Apim-Subscription-Key: ' . $GLOBALS['enquire_key'],
		));
		$user_detail_response = curl_exec($user_ch);
		$user_http_status = curl_getinfo($user_ch, CURLINFO_HTTP_CODE);
		
		// handle errors
		// return false on curl error
		if (curl_errno($user_ch)) {
			$message = 'CURL ERROR FOR USER DATA: ' . curl_error($user_ch);
			sendLogMessage($message);
			logToFile($message);
			sendDebugMail('CURL ERROR ON USER RECORD', $message);
			curl_close($user_ch);
			return false;
		}
		
		// return false on http error
		if ($user_http_status != '200') {
			$message = 'Unable to connect to Individual endpoint. Curl returned ' . $user_http_status . ' ' . $user_detail_response;
			sendLogMessage($message);
			logToFile($message);
			sendDebugMail($user_http_status, $message);
			curl_close($user_ch);
			return false;
		}
		
		curl_close($user_ch);
		
		// parse array from the json data
		$user_detail = json_decode($user_detail_response, true);
		
		// and return the parsed json array
		return $user_detail;
		
	}

	/*
	 * Get case details from enfluence.
	 * 
	 * @param string
	 *   The case number.
	 *
	 * @returns array
	 *   The case details.
	 */
	function enflusher_get_case_details($case_number) {
		
		$case_url = $GLOBALS['enquire_case_url'] . '?PortalId=' . $GLOBALS['enquire_portal_id'] . '&CaseNumber=' . $case_number;
		
		$case_ch = curl_init();
		curl_setopt($case_ch, CURLOPT_URL, $case_url);
		curl_setopt($case_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($case_ch, CURLOPT_HTTPHEADER, array(
			'Ocp-Apim-Subscription-Key: ' . $GLOBALS['enquire_key'],
		));
		
		$case_detail_response = curl_exec($case_ch);
		$case_http_status = curl_getinfo($case_ch, CURLINFO_HTTP_CODE);
		
		if (curl_errno($case_ch)) {
			$message = 'CURL ERROR FOR CASE DATA: ' . curl_error($case_ch);
			sendLogMessage($message);
			logToFile($message);
			sendDebugMail('CURL ERROR FOR CASE DATA', $message);
			curl_close($case_ch);
			return false;
		}
		
		if ($case_http_status != '200') {
			$message = 'Unable to connect to Case endpoint. Curl returned ' . $case_http_status . ' ' . $case_detail_response;
			sendLogMessage($message);
			logToFile($message);
			sendDebugMail($user_http_status, $message);
			curl_close($case_ch);
			return false;
		}
		
		curl_close($case_ch);
		
		$case_details = json_decode($case_detail_response, true);
		
		return $case_details;
		
	}
	
	/*
	 * not yet being used - don't think I want to send around giant arrays more than I need to.
	 */
	function enflasher_set_json_query($case, $user) {
		
		$base_query = array();
		
		/*
		 *
		 * populate the emfluence query array
		 *
		 * if a value is empty or not set (by the primary and secondary value), the emfluence query value will be an empty string
		 * so it is possible to send emfluence a query string with no values set, and this will process without issue
		 * done to ensure that the system does not blow up with one bad query - emfluence simply rejects the query if it doesn't meet requirements
		 * we could kill the individual POST if no email is set in the query array (as emfluence requirers an email value)
		 *
		*/
		
		// set emfluence firstName
		if (isset($case['cases'][0]['Individuals'][1]['FirstName']) && !empty($case['cases'][0]['Individuals'][1]['FirstName'])) {
			$base_query['firstName'] = $case['cases'][0]['Individuals'][1]['FirstName'];
		} else if (isset($user['properties'][1]['value']) && !empty($user['properties'][1]['value'])) {
			$base_query['firstName'] = $user['properties'][1]['value'];
		} else {
			$base_query['firstName'] = '';
		}
		
		// set emfluence lastName
		if (isset($case['cases'][0]['Individuals'][1]['LastName']) && !empty($case['cases'][0]['Individuals'][1]['LastName'])) {
			$base_query['lastName'] = $case['cases'][0]['Individuals'][1]['LastName'];
		} else if (isset($user['properties'][3]['value']) && !empty($user['properties'][3]['value'])) {
			$base_query['lastName'] = $user['properties'][3]['value'];
		} else {
			$base_query['lastName'] = '';
		}
		
		// set emfluence email
		if(isset($case['cases'][0]['Individuals'][1]['Email']) && !empty($case['cases'][0]['Individuals'][1]['Email'])) {
			$base_query['email'] = $case['cases'][0]['Individuals'][1]['Email'];
		} else if (isset($user['result']['individuals']['properties'][7]['value']) && !empty($user['result']['individuals']['properties'][7]['value'])) {
			$base_query['email'] = $user['result']['individuals']['properties'][7]['value'];
		} else {
			$base_query['email'] = '';
		}
		
		// set emfluence phone
		if (isset($case['cases'][0]['Individuals'][1]['HomePhoneFormat']) && !empty($case['cases'][0]['Individuals'][1]['HomePhoneFormat'])) {
			$base_query['phone'] = $case['cases'][0]['Individuals'][1]['HomePhoneFormat'];
		} else if (isset($user['result']['individuals']['properties'][4]['value']) && !empty($user['result']['individuals']['properties'][4]['value'])) {
			$base_query['phone'] = $user['result']['individuals']['properties'][4]['value'];
		} else {
			$base_query['phone'] = '';
		}
		
		// set emfluence community ids
		if (isset($user['result']['individuals']['community'][0]['Id']) && !empty($user['result']['individuals']['community'][0]['Id'])) {
			
			$community_id = $user['result']['individuals']['community'][0]['Id'];
			
			$group_ids = array();
			
			$emfluence_group_ids = $GLOBALS['emfluence_group_ids'];
			
			foreach ($emfluence_group_ids as $key => $value) {
				
				if ($community_id == $key) {
					$group_ids[] = $value;
				}
				
			}
			
			$base_query['groupIDs'] = $group_ids;
			
		}
		
		// construct the emfluence json
		return json_encode($base_query);		
		
	}

	/*
	 * Send a message to the log.
	 *
	 * @param string
	 *   The message to send to the log.
	 */
	function sendLogMessage($message) {
		error_log($GLOBALS['app_name'] . " Message: " .  $message);
	}
	
	/*
	 * Write to a log file when running as cron job.
	 *
	 * @param string
	 *   The base message to send to file.
	 */
	function logToFile($message) {
		$main_dir = dirname(__FILE__);
		$file = $main_dir . '/logs/cron-log-' . date('Y-m-d') . '.log';
		$message = '[' . date('c') . '] ' . $GLOBALS['app_name'] . ' Message: ' . $message;
		$fd = fopen($file, 'a');
		fwrite($fd, $message . "\n");
		fclose($fd);
	}
	
	/*
	 * Send a debug email.
	 *
	 * @param string
	 *   A short descripotion of the error.
	 *
	 * @param string
	 *   The debug message.
	 *
	 * @returns bool
	 *   Generic true.
	 */
	function sendDebugMail($outcome, $message) {
		
		// get the global values & set the recipent and the subject line
		$to = $GLOBALS['mail_to'];
		$subject =  $GLOBALS['app_name'] . ' ' . $outcome;
		
		// set the email header
		$header = 'From: admin@example.com' . "\r\n";
		$header .= 'Reply-To: admin@example.com' . "\r\n";
		$header .= 'X-Mailer: PHP/' . phpversion();
		
		// send the mail
		$retval = mail($to,$subject,$message,$header);
		
		// error handle
		if ($retval == false) {
			$message = 'Mail Error: Could not send mail.';
			sendLogMessage($message, 3, $log);
			logToFile($message);
		}
		
		// generic bool
		return true;
		
	}
