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
require_once($CFG->dirroot.'/course/lib.php');

use moodle\local\ltiprovider as ltiprovider;

/**
 * Create a IMS POX body request for sync grades.
 * @param  string $source Sourceid required for the request
 * @param  float $grade User final grade
 * @return string
 */
function local_ltiprovider_create_service_body($source, $grade) {
    return '<?xml version = "1.0" encoding = "UTF-8"?>
<imsx_POXEnvelopeRequest xmlns = "http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
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
 * Creates an unique username
 * @param  string $consumerkey Consumer key
 * @param  string $ltiuserid   External tool user id
 * @return string              The new username
 */
function local_ltiprovider_create_username($consumerkey, $ltiuserid) {

    if ( strlen($ltiuserid) > 0 and strlen($consumerkey) > 0 ) {
        $userkey = $consumerkey . ':' . $ltiuserid;
    } else {
        $userkey = false;
    }

    return 'ltiprovider' . md5($consumerkey . '::' . $userkey);
}

/**
 * Unenrol an user from a course
 * @param  stdclass $tool Tool object
 * @param  stdclass $user User object
 * @return bool       True on unenroll
 */
function local_ltiprovider_unenrol_user($tool, $user) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $tool->courseid), '*', MUST_EXIST);
    $manual = enrol_get_plugin('manual');

    if ($instances = enrol_get_instances($course->id, false)) {
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manual->unenrol_user($instance, $user->id);
                return true;
            }
        }
    }
    return false;
}

/**
 * Enrol a user in a course
 * @param  stdclass  $tool   The tool object
 * @param  stdclass  $user   The user object
 * @param  boolean  $isinstructor  Is instructor or not (learner).
 * @param  boolean $return If we should return information
 * @return mix          Boolean if $return is set to true
 */
function local_ltiprovider_enrol_user($tool, $user, $isinstructor, $return = false) {
    global $DB;

    if (($isinstructor && !$tool->enrolinst) || (!$isinstructor && !$tool->enrollearn)) {
      return $return;
    }

    $course = $DB->get_record('course', array('id' => $tool->courseid), '*', MUST_EXIST);

    $manual = enrol_get_plugin('manual');

    $today = time();
    $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);
    $timeend = 0;
    if ($tool->enrolperiod) {
            $timeend = $today + $tool->enrolperiod;
    }

    // Course role id for the Instructor or Learner
    // TODO Do something with lti system admin (urn:lti:sysrole:ims/lis/Administrator)
    $roleid = $isinstructor ? $tool->croleinst: $tool->crolelearn;

    if ($instances = enrol_get_instances($course->id, false)) {
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {

                // Check if the user enrolment exists
                if (! $ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$user->id))) {
                    // This means a new enrolment, so we have to check enroment starts and end limits and also max occupation

                    // First we check if there is a max enrolled limit
                    if ($tool->maxenrolled) {
                        // TODO Improve this count because unenrolled users from Moodle admin panel are not sync with this table
                        if ($DB->count_records('local_ltiprovider_user', array('toolid'=>$tool->id)) > $tool->maxenrolled) {
                            // We do not use print_error for the iframe issue allowframembedding
                            if ($return) {
                                return false;
                            } else {
                                echo get_string('maxenrolledreached', 'local_ltiprovider');
                                die;
                            }
                        }
                    }

                    $timenow = time();
                    if ($tool->enrolstartdate and $timenow < $tool->enrolstartdate) {
                        // We do not use print_error for the iframe issue allowframembedding
                        if ($return) {
                            return false;
                        } else {
                            echo get_string('enrolmentnotstarted', 'local_ltiprovider');
                            die;
                        }
                    }
                    if ($tool->enrolenddate and $timenow > $tool->enrolenddate) {
                        // We do not use print_error for the iframe issue allowframembedding
                        if ($return) {
                            return false;
                        } else {
                            echo get_string('enrolmentfinished', 'local_ltiprovider');
                            die;
                        }
                    }
                    // TODO, delete created users not enrolled

                    $manual->enrol_user($instance, $user->id, $roleid, $today, $timeend);
                    if ($return) {
                        return true;
                    }
                }
                break;
            }
        }
    }
    return false;
}

/**
 * Populates a standar user record
 * @param  stdClass $user    The user record to be populated
 * @param  stdClass $context The LTI context
 * @param  stdClass $tool    The tool object
 */
function local_ltiprovider_populate($user, $context, $tool) {
    global $CFG;
    $user->firstname = isset($context->info['lis_person_name_given'])? $context->info['lis_person_name_given'] : $context->info['user_id'];
    $user->lastname = isset($context->info['lis_person_name_family'])? $context->info['lis_person_name_family']: $context->info['context_id'];
    $user->email = clean_param($context->getUserEmail(), PARAM_EMAIL);
    $user->city = (!empty($tool->city)) ? $tool->city : "";
    $user->country = (!empty($tool->country)) ? $tool->country : "";
    $user->institution = (!empty($tool->institution)) ? $tool->institution : "";
    $user->timezone = (!empty($tool->timezone)) ? $tool->timezone : "";
    $user->maildisplay = (!empty($tool->maildisplay)) ? $tool->maildisplay : "";
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->confirmed = 1;
    $user->timecreated = time();
    $user->timemodified = time();

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
 * For new created courses we get the fullname, shortname or idnumber according global settings
 * @param  string $field   The course field to get (fullname, shortname or idnumber)
 * @param  stdClass $context The global LTI context
 * @return string          The field
 */
function local_ltiprovider_get_new_course_info($field, $context) {
    global $DB;

    $info = '';

    $setting = get_config('local_ltiprovider', $field . "format");

    switch ($setting) {
        case 0:
            $info = $context->info['context_id'];
            break;
        case '1':
            $info = $context->info['context_title'];
            break;
        case '2':
            $info = $context->info['context_label'];
            break;
        case '3':
            $info = $context->info['oauth_consumer_key'] . ':' . $context->info['context_id'];
            break;
        case '4':
            $info = $context->info['oauth_consumer_key'] . ':' . $context->info['context_title'];
            break;
        case '5':
            $info = $context->info['oauth_consumer_key'] . ':' . $context->info['context_label'];
            break;
    }

    // Special case.
    if ($field == 'shortname') {
        // Add or increase the number at the final of the shortname.
        if ($course = $DB->get_record('course', array ('shortname' => $info))) {
            if ($samecourses = $DB->get_records('course', array ('fullname' => $course->fullname), 'id DESC', 'shortname', '0', '1')) {
                $samecourse = array_shift($samecourses);
                $parts = explode(' ', $samecourse->shortname);
                $number = array_pop($parts);
                if (is_numeric($number)) {
                    $parts[] = $number + 1;
                } else {
                    $parts[] = $number . ' 1';
                }
                $info = implode(' ', $parts);
            }
        }
    }

    return $info;
}

/**
 * Create a ltiprovier tool for a restored course or activity
 *
 * @param  int $courseid  The course id
 * @param  int $contextid The context id
 * @param  stdClass $lticontext The LTI context object
 * @return int           The new tool id
 */
function local_ltiprovider_create_tool($courseid, $contextid, $lticontext) {
    global $CFG, $DB;

    $tool = new stdClass();
    $tool->courseid = $courseid;
    $tool->contextid = $contextid;
    $tool->disabled = 0;
    $tool->forcenavigation = 0;
    $tool->croleinst = 3;
    $tool->crolelearn = 5;
    $tool->aroleinst = 3;
    $tool->arolelearn = 5;
    $tool->secret = get_config('local_ltiprovider', 'globalsharedsecret');
    $tool->encoding = 'UTF-8';
    $tool->institution = "";
    $tool->lang = $CFG->lang;
    $tool->timezone = 99;
    $tool->maildisplay = 2;
    $tool->city = "mycity";
    $tool->country = "ES";
    $tool->hidepageheader = 0;
    $tool->hidepagefooter = 0;
    $tool->hideleftblocks = 0;
    $tool->hiderightblocks = 0;
    $tool->customcss = '';
    $tool->enrolstartdate = 0;
    $tool->enrolperiod = 0;
    $tool->enrolenddate = 0;
    $tool->maxenrolled = 0;
    $tool->userprofileupdate = 1;
    $tool->timemodified = time();
    $tool->timecreated = time();
    $tool->lastsync = 0;
    $tool->enrolinst = 1;
    $tool->enrollearn = 1;
    $tool->sendgrades = (!empty($lticontext->info['lis_outcome_service_url'])) ? 1 : 0;
    $tool->syncmembers = (!empty($lticontext->info['ext_ims_lis_memberships_url'])) ? 1 : 0;
    $tool->syncmode = (!empty($lticontext->info['ext_ims_lis_memberships_url'])) ? 1 : 0;
    $tool->syncperiod = (!empty($lticontext->info['ext_ims_lis_memberships_url'])) ? 86400 : 0;

    $tool->id = $DB->insert_record('local_ltiprovider', $tool);
    return $tool;
}

/**
 * Duplicate a course
 *
 * @param int $courseid
 * @param string $fullname Duplicated course fullname
 * @param int $newcourse Destination course
 * @param array $options List of backup options
 * @return stdClass New course info
 */
 function local_ltiprovider_duplicate_course($courseid, $newcourse, $visible = 1, $options = array(), $useridcreating = null, $context) {
    global $CFG, $USER, $DB;

    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

    if (empty($USER)) {
        // Emulate session.
        cron_setup_user();
    }

    // Context validation.

    if (! ($course = $DB->get_record('course', array('id'=>$courseid)))) {
        throw new moodle_exception('invalidcourseid', 'error');
    }

    $removeoptions = array();
    $removeoptions['keep_roles_and_enrolments'] = true;
    $removeoptions['keep_groups_and_groupings'] = true;
    remove_course_contents($newcourse->id, false, $removeoptions);

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


    // Backup the course.
    $admin = get_admin();

    $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $admin->id);

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

    $rc = new restore_controller($backupid, $newcourse->id,
            backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $admin->id, backup::TARGET_CURRENT_DELETING);

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

    $course = $DB->get_record('course', array('id' => $newcourse->id), '*', MUST_EXIST);
    $course->visible = $visible;
    $course->fullname = $newcourse->fullname;
    $course->shortname = $newcourse->shortname;
    $course->idnumber = $newcourse->idnumber;

    // Set shortname and fullname back.
    $DB->update_record('course', $course);

    if (empty($CFG->keeptempdirectoriesonbackup)) {
        fulldelete($backupbasepath);
    }

    // Delete the course backup file created by this WebService. Originally located in the course backups area.
    $file->delete();

    // We have to unenroll all the user except the one that create the course.
    if (get_config('local_ltiprovider', 'duplicatecourseswithoutusers') and $useridcreating) {
        require_once($CFG->dirroot.'/group/lib.php');
        // Previous to unenrol users, we assign some type of activities to the user that created the course.
        if ($user = $DB->get_record('user', array('id' => $useridcreating))) {
            if ($databases = $DB->get_records('data', array('course' => $course->id))) {
                foreach ($databases as $data) {
                    $DB->execute("UPDATE {data_records} SET userid = ? WHERE dataid = ?", array($user->id,
                                                                                                $data->id));
                }
            }
            if ($glossaries = $DB->get_records('glossary', array('course' => $course->id))) {
                foreach ($glossaries as $glossary) {
                    $DB->execute("UPDATE {glossary_entries} SET userid = ? WHERE glossaryid = ?", array($user->id,
                                                                                                $glossary->id));
                }
            }

            // Same for questions.
            $newcoursecontextid = context_course::instance($course->id);
            if ($qcategories = $DB->get_records('question_categories', array('contextid' => $newcoursecontextid->id))) {
                foreach ($qcategories as $qcategory) {
                    $DB->execute("UPDATE {question} SET createdby = ?, modifiedby = ? WHERE category = ?", array($user->id,
                                                                                                                    $user->id,
                                                                                                                    $qcategory->id));
                }
            }

            // Enrol the user.
            if ($tool = $DB->get_record('local_ltiprovider', array('contextid' => $newcoursecontextid->id))) {
                $roles = explode(',', strtolower($context->info['roles']));
                local_ltiprovider_enrol_user($tool, $user, $roles, true);
            }


            // Now, we unenrol all the users except the one who created the course.
            $plugins = enrol_get_plugins(true);
            $instances = enrol_get_instances($course->id, true);
            foreach ($instances as $key => $instance) {
                if (!isset($plugins[$instance->enrol])) {
                    unset($instances[$key]);
                    continue;
                }
            }

            $sql = "SELECT ue.*
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON (e.id = ue.enrolid AND e.courseid = :courseid)
                          JOIN {context} c ON (c.contextlevel = :courselevel AND c.instanceid = e.courseid)";
            $params = array('courseid' => $course->id, 'courselevel' => CONTEXT_COURSE);

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if ($ue->userid == $user->id) {
                    continue;
                }

                if (!isset($instances[$ue->enrolid])) {
                    continue;
                }
                $instance = $instances[$ue->enrolid];
                $plugin = $plugins[$instance->enrol];
                if (!$plugin->allow_unenrol($instance) and !$plugin->allow_unenrol_user($instance, $ue)) {
                    continue;
                }
                $plugin->unenrol_user($instance, $ue->userid);
            }
            $rs->close();

            groups_delete_group_members($course->id);
            groups_delete_groups($course->id, false);
            groups_delete_groupings_groups($course->id, false);
            groups_delete_groupings($course->id, false);
        }
    }

    return $course;
}

/**
 * Duplicates a Moodle module in an existing course
 * @param  int $cmid     Course module id
 * @param  int $courseid Course id
 * @return int           New course module id
 */
function local_ltiprovider_duplicate_module($cmid, $courseid, $newidnumber, $lticontext) {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    require_once($CFG->libdir . '/filelib.php');

    if (empty($USER)) {
        // Emulate session.
        cron_setup_user();
    }

    $course     = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_id('', $cmid, 0, true, MUST_EXIST);
    $cmcontext  = context_module::instance($cm->id);
    $context    = context_course::instance($course->id);


    if (!plugin_supports('mod', $cm->modname, FEATURE_BACKUP_MOODLE2)) {
        $url = course_get_url($course, $cm->sectionnum, array('sr' => $sectionreturn));
        print_error('duplicatenosupport', 'error', $url, $a);
    }

    // backup the activity
    $admin = get_admin();

    $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $admin->id);

    $backupid       = $bc->get_backupid();
    $backupbasepath = $bc->get_plan()->get_basepath();

    $bc->execute_plan();

    $bc->destroy();

    // restore the backup immediately

    $rc = new restore_controller($backupid, $courseid,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $admin->id, backup::TARGET_CURRENT_ADDING);

    if (!$rc->execute_precheck()) {
        $precheckresults = $rc->get_precheck_results();
        if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }
            print_r($precheckresults);
            die();
        }
    }

    $rc->execute_plan();

    $newcmid = null;
    $tasks = $rc->get_plan()->get_tasks();
    foreach ($tasks as $task) {
        if (is_subclass_of($task, 'restore_activity_task')) {
            if ($task->get_old_contextid() == $cmcontext->id) {
                $newcmid = $task->get_moduleid();
                break;
            }
        }
    }

    $rc->destroy();

    if ($module = $DB->get_record('course_modules', array('id' => $newcmid))) {
        $module->idnumber = $newidnumber;
        $DB->update_record('course_modules', $module);
    }

    $newtoolid = 0;
    if ($tools = $DB->get_records('local_ltiprovider', array('contextid' => $cmcontext->id))) {
        $newcmcontext = context_module::instance($newcmid);
        foreach ($tools as $tool) {
            $tool->courseid = $course->id;
            $tool->contextid = $newcmcontext->id;
            $newtoolid = $DB->insert_record('local_ltiprovider', $tool);
        }
    }

    if (!$newtoolid) {
        $tool = local_ltiprovider_create_tool($course->id, $newcmcontext->id, $lticontext);
    }

    if (empty($CFG->keeptempdirectoriesonbackup)) {
        fulldelete($backupbasepath);
    }
    return $newcmid;

}

function local_ltiprovider_update_user_profile_image($userid, $url) {
    global $CFG, $DB;

    require_once("$CFG->libdir/filelib.php");
    require_once("$CFG->libdir/gdlib.php");

    $fs = get_file_storage();
    try {
        $context = context_user::instance($userid, MUST_EXIST);
        $fs->delete_area_files($context->id, 'user', 'newicon');

        $filerecord = array('contextid'=>$context->id, 'component'=>'user', 'filearea'=>'newicon', 'itemid'=>0, 'filepath'=>'/');
        if (!$iconfiles = $fs->create_file_from_url($filerecord, $url, array('calctimeout' => false,
                                                                                'timeout' => 5,
                                                                                'skipcertverify' => true,
                                                                                'connecttimeout' => 5))) {
            return "Error downloading profile image from $url";
        }

        if ($iconfiles = $fs->get_area_files($context->id, 'user', 'newicon')) {
            // Get file which was uploaded in draft area
            foreach ($iconfiles as $file) {
                if (!$file->is_directory()) {
                    break;
                }
            }
            // Copy file to temporary location and the send it for processing icon
            if ($iconfile = $file->copy_content_to_temp()) {
                // There is a new image that has been uploaded
                // Process the new image and set the user to make use of it.
                $newpicture = (int)process_new_icon($context, 'user', 'icon', 0, $iconfile);
                // Delete temporary file
                @unlink($iconfile);
                // Remove uploaded file.
                $fs->delete_area_files($context->id, 'user', 'newicon');
                $DB->set_field('user', 'picture', $newpicture, array('id' => $userid));
                return true;
            } else {
                // Something went wrong while creating temp file.
                // Remove uploaded file.
                $fs->delete_area_files($context->id, 'user', 'newicon');
                return "Error creating the downloaded profile image from $url";
            }
        } else {
            return "Error converting downloaded profile image from $url";
        }
    } catch (Exception $e) {
        return "Error downloading profile image from $url";
    }
    return "Error downloading profile image from $url";
}


