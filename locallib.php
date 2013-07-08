<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * General plugin functions.
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die;
require_once($CFG->dirroot.'/local/ltiprovider/ims-blti/blti_util.php');

use moodle\local\ltiprovider as ltiprovider;

/**
 * Create a IMS POX body request for sync grades.
 * @param  string $source Sourceid required for the request
 * @param  float $grade User final grade
 * @return string
 */
function loca_ltiprovider_create_service_body($source, $grade) {
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

/**
 * Populates a standar user record
 * @param  stdClass $user    The user record to be populated
 * @param  stdClass $context The LTI context
 * @param  stdClass $tool    The tool object
 */
function local_ltiprovider_populate($user, $context, $tool) {
    global $CFG;
    $user->firstname = optional_param('lis_person_name_given', '', PARAM_TEXT);
    $user->lastname = optional_param('lis_person_name_family', '', PARAM_TEXT);
    $user->email = clean_param($context->getUserEmail(), PARAM_EMAIL);
    $user->city = $tool->city;
    $user->country = $tool->country;
    $user->institution = $tool->institution;
    $user->timezone = $tool->timezone;
    $user->maildisplay = $tool->maildisplay;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->confirmed = 1;

    $user->lang = $tool->lang;
    if (! $user->lang and isset($_POST['launch_presentation_locale'])) {
        $user->lang = optional_param('launch_presentation_locale', '', PARAM_LANG);
    }
    if (! $user->lang) {
        // TODO: This should be changed for detect the course lang
        $user->lang = current_language();
    }
}

/**
 * Compares two users
 * @param  stdClass $newuser    The new user
 * @param  stdClass $olduser    The old user
 * @return bolol                True if both users are the same
 */
function local_ltiprovider_user_match($newuser, $olduser) {
    if ( $newuser->firstname != $olduser->firstname )
        return false;
    if ( $newuser->lastname != $olduser->lastname )
        return false;
    if ( $newuser->email != $olduser->email )
        return false;
    if ( $newuser->city != $olduser->city )
        return false;
    if ( $newuser->country != $olduser->country )
        return false;
    if ( $newuser->institution != $olduser->institution )
        return false;
    if  ($newuser->timezone != $olduser->timezone )
        return false;
    if ( $newuser->maildisplay != $olduser->maildisplay )
        return false;
    if ( $newuser->mnethostid != $olduser->mnethostid )
        return false;
    if ( $newuser->confirmed != $olduser->confirmed )
        return false;
    if ( $newuser->lang != $olduser->lang )
        return false;
    return true;
}

/**
 * Duplicate a course
 *
 * @param int $courseid
 * @param string $fullname Duplicated course fullname
 * @param string $shortname Duplicated course shortname
 * @param int $categoryid Duplicated course parent category id
 * @param int $visible Duplicated course availability
 * @param array $options List of backup options
 * @return stdClass New course info
 */
 function local_ltiprovider_duplicate_course($courseid, $fullname, $shortname, $categoryid, $visible = 1, $options = array()) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

    // Context validation.

    if (! ($course = $DB->get_record('course', array('id'=>$courseid)))) {
        throw new moodle_exception('invalidcourseid', 'error');
    }

    $backupdefaults = array(
        'activities' => 1,
        'blocks' => 1,
        'filters' => 1,
        'users' => 0,
        'role_assignments' => 0,
        'comments' => 0,
        'userscompletion' => 0,
        'logs' => 0,
        'grade_histories' => 0
    );

    $backupsettings = array();
    // Check for backup and restore options.
    if (!empty($options)) {
        foreach ($options as $option) {

            // Strict check for a correct value (allways 1 or 0, true or false).
            $value = clean_param($option['value'], PARAM_INT);

            if ($value !== 0 and $value !== 1) {
                throw new moodle_exception('invalidextparam', 'webservice', '', $option['name']);
            }

            if (!isset($backupdefaults[$option['name']])) {
                throw new moodle_exception('invalidextparam', 'webservice', '', $option['name']);
            }

            $backupsettings[$option['name']] = $value;
        }
    }

    // Check if the shortname is used.
    if ($foundcourses = $DB->get_records('course', array('shortname'=>$shortname))) {
        foreach ($foundcourses as $foundcourse) {
            $foundcoursenames[] = $foundcourse->fullname;
        }

        $foundcoursenamestring = implode(',', $foundcoursenames);
        throw new moodle_exception('shortnametaken', '', '', $foundcoursenamestring);
    }

    // Backup the course.

    $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $USER->id);

    foreach ($backupsettings as $name => $value) {
        $bc->get_plan()->get_setting($name)->set_value($value);
    }

    $backupid       = $bc->get_backupid();
    $backupbasepath = $bc->get_plan()->get_basepath();

    $bc->execute_plan();
    $results = $bc->get_results();
    $file = $results['backup_destination'];

    $bc->destroy();

    // Restore the backup immediately.

    // Check if we need to unzip the file because the backup temp dir does not contains backup files.
    if (!file_exists($backupbasepath . "/moodle_backup.xml")) {
        $file->extract_to_pathname(get_file_packer(), $backupbasepath);
    }

    // Create new course.
    $newcourseid = restore_dbops::create_new_course($fullname, $shortname, $categoryid);

    $rc = new restore_controller($backupid, $newcourseid,
            backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $USER->id, backup::TARGET_NEW_COURSE);

    foreach ($backupsettings as $name => $value) {
        $setting = $rc->get_plan()->get_setting($name);
        if ($setting->get_status() == backup_setting::NOT_LOCKED) {
            $setting->set_value($value);
        }
    }

    if (!$rc->execute_precheck()) {
        $precheckresults = $rc->get_precheck_results();
        if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }

            $errorinfo = '';

            foreach ($precheckresults['errors'] as $error) {
                $errorinfo .= $error;
            }

            if (array_key_exists('warnings', $precheckresults)) {
                foreach ($precheckresults['warnings'] as $warning) {
                    $errorinfo .= $warning;
                }
            }

            throw new moodle_exception('backupprecheckerrors', 'webservice', '', $errorinfo);
        }
    }

    $rc->execute_plan();
    $rc->destroy();

    $course = $DB->get_record('course', array('id' => $newcourseid), '*', MUST_EXIST);
    $course->fullname = $fullname;
    $course->shortname = $shortname;
    $course->visible = $visible;

    // Set shortname and fullname back.
    $DB->update_record('course', $course);

    if (empty($CFG->keeptempdirectoriesonbackup)) {
        fulldelete($backupbasepath);
    }

    // Delete the course backup file created by this WebService. Originally located in the course backups area.
    $file->delete();

    return $course;
}