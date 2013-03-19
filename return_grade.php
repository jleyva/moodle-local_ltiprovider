<?php
/**
 * function for sync grades
 * @return void
 */
defined('MOODLE_INTERNAL') or die;
use moodle\local\ltiprovider as ltiprovider;

function local_ltiprovider_return_grade($tool, $single_user) {
	global $DB, $CFG;
	require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuth.php");
	require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuthBody.php");
	require_once($CFG->libdir.'/gradelib.php');
	require_once($CFG->dirroot.'/grade/querylib.php');

	mtrace('Running return_grade for ltiprovider');
	if (!$single_user) {
		mtrace(" No user in call.  Gathering user list for $tool->id");
		$users = $DB->get_records('local_ltiprovider_user', array('toolid' => $tool->id));
	} else { $users[] = $single_user; }

	mtrace(" Starting sync tool id $tool->id course id $tool->courseid");
	$user_count = 0;
	$send_count = 0;
	$error_count = 0;
	$grade = false;
	$timenow = time();

	if ($users) {
		foreach ($users as $user) {
			$user_count = $user_count + 1;
			// This can happen is the sync process has an unexpected error
			if ( strlen($user->serviceurl) < 1 ) continue;
			if ( strlen($user->sourceid) < 1 ) continue;
			if ($user->lastsync > $tool->lastsync  && !$single_user) {
					mtrace("Skipping user {$user->id}");
					continue;
			}

			if ($context = $DB->get_record('context', array('id' => $tool->contextid))) {
					if ($context->contextlevel == CONTEXT_COURSE) {

							if ($grade = grade_get_course_grade($user->userid, $tool->courseid)) {
									$grademax = floatval($grade->item->grademax);
									$grade = $grade->grade;
							}
					} else if ($context->contextlevel == CONTEXT_MODULE) {

							$cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
							$grades = grade_get_grades($cm->course, 'mod', $cm->modname, $cm->instance, $user->userid);
							if (empty($grades->items[0]->grades)) {
									$grade = false;
							} else {
									$grade = reset($grades->items[0]->grades);
									$grademax = floatval($grade->item->grademax);
									$grade = $grade->grade;
							}
					}

					if ( $grade === false || $grade === NULL || strlen($grade) < 1) continue;

					// No need to be dividing by zero
					if ( $grademax == 0.0 ) $grademax = 100.0;

					// TODO: Make lastgrade should be float or string - but it is integer so we truncate
					// TODO: Then remove those intval() calls

					// Don't double send
					if ( intval($grade) == $user->lastgrade ) continue;

					// We sync with the external system only when the new grade differs with the previous one
					// TODO - Global setting for check this
					if ($grade > 0 and $grade <= $grademax) {
							$float_grade = $grade / $grademax;
							$body = ltiprovider_create_service_body($user->sourceid, $float_grade);

							try {
									$response = ltiprovider\sendOAuthBodyPOST('POST', $user->serviceurl, $user->consumerkey, $user->consumersecret, 'application/xml', $body);
							} catch (Exception $e) {
									mtrace(" ".$e->getMessage());
									$error_count = $error_count + 1;
									continue;
							}

							// TODO - Check for errors in $retval in a correct way (parsing xml)
							if (strpos(strtolower($response), 'success') !== false) {

									$DB->set_field('local_ltiprovider_user', 'lastsync', $timenow, array('id' => $user->id));
									$DB->set_field('local_ltiprovider_user', 'lastgrade', intval($grade), array('id' => $user->id));
									mtrace(" User grade sent to remote system. userid: $user->userid grade: $float_grade");
									$send_count = $send_count + 1;
							} else {
									mtrace(" User grade send failed: ".$response.$user->serviceurl);
									$error_count = $error_count + 1;
							}
					} else {
							mtrace(" User grade out of range: grade = ".$grade);
							$error_count = $error_count + 1;
					}
			} else {
					mtrace(" Invalid context: contextid = ".$tool->contextid);
			}
	}
	}
}

/**
 * Create a IMS POX body request for sync grades.
 * @param  string $source Sourceid required for the request
 * @param  float $grade User final grade
 * @return string
 */
function ltiprovider_create_service_body($source, $grade) {
		return '<?xml version = "1.0" encoding = "UTF-8"?>
<imsx_POXEnvelopeRequest xmlns = "http://www.imsglobal.org/lis/oms1p0/pox">
	<imsx_POXHeader>
		<imsx_POXRequestHeaderInfo>
			<imsx_version>V1.0</imsx_version>
			<imsx_messageIdentifier>'.(time()).'</imsx_messageIdentifier>
		</imsx_POXRequestHeaderInfo>
	</imsx_POXHeader>
	<imsx_POXBody>
		<replaceResultRequest>
			<resultRecord>
				<sourcedGUID>
					<sourcedId>'.$source.'</sourcedId>
				</sourcedGUID>
				<result>
					<resultScore>
						<language>en-us</language>
						<textString>'.$grade.'</textString>
					</resultScore>
				</result>
			</resultRecord>
		</replaceResultRequest>
	</imsx_POXBody>
</imsx_POXEnvelopeRequest>';
}
