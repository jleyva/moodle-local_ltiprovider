<?php
/*
 * This is designed to be set in a course to allow the user to send
 * a grade to the tool consumer on demand.
 * Simply add a URL, point to this file, and everything else just works.
 */

require_once('../../config.php');
defined('MOODLE_INTERNAL') or die;
use moodle\local\ltiprovider as ltiprovider;
require_once($CFG->dirroot.'/local/ltiprovider/return_grade.php');
// this isn't as clean as a sourcedId, but it should be close enough:
// each user should only be enrolled in each course exactly once.
// would be nice to put the ltiprovider user object in $USER, like what
// has happened with the ltiprovider tool object.
$user = $DB->get_record_select(
	'local_ltiprovider_user',
	'userid = ? and toolid = ?',
	array($USER->id, $SESSION->ltiprovider->id)

);
$result = local_ltiprovider_return_grade($SESSION->ltiprovider, $user);?>
<head>
</head>
<body>
<?php
	if (
		// TODO:  This isn't as clean as I'd like it to be.
		$result['errors'] === 0
	) {
		if ($result['sent'] !== 1) {
			echo(
				'No grade was sent.  This usually indicates the previous grade sent ' .
				'matches the current grade within this tool.'
			);
		} else {
			echo('Your grade has been submitted.  Please click "done" below.');
		}
	} else {
		echo(
			'There was a problem submitting your grade.  ' .
			'Please email support the following information:<br/>' .
			'User id: ' . $USER->id . '<br/>' .
			'Course id: ' . $SESSION->ltiprovider->courseid . '<br/>' .
			'LTI Provider id: ' . $SESSION->ltiprovider->id . '<br/>' .
			'Server time: ' . date('c')
		);
	}
?>
