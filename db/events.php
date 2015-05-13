<?php

$observers = array(
	// questionnaire grade handling
	array(
		'eventname' => '\mod_questionnaire\event\attempt_submitted',
		'includefile' => '/local/ltiprovider/eventlib.php',
		'callback' => '\LTIProvider\Grade::user_send',
	),
	// quiz grade handling
	array(
		'eventname' => '\mod_quiz\event\attempt_submitted',
		'includefile' => '/local/ltiprovider/eventlib.php',
		'callback' => '\LTIProvider\Grade::user_send'
	)
);
