<?php

	ini_set('log_errors', 1);
	ini_set('error_log', 'logs/errorlog' . date('Y-m-d') . '.log');

	function sendDebugMail($to, $subject_line, $outcome, $message) {
		
		$log = 'logs/errorlog' . date('Y-m-d') . '.log';
		
		$subject =  $subject_line . ' ' . $outcome;
		
		$header = 'From: admin@example.com' . "\r\n";
		$header .= 'Reply-To: admin@example.com' . "\r\n";
		$header .= 'X-Mailer: PHP/' . phpversion();
		$retval = mail($to,$subject,$message,$header);
		
		if ($retval == false) {
			error_log("[" . date('c') . "] Mail Error: Could not send mail. \n", 3, $log);
		}
		return true;
	}

?>