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
 * @param  array  $roles  Roles of the current user
 * @param  boolean $return If we should return information
 * @return mix          Boolean if $return is set to true
 */
function local_ltiprovider_enrol_user($tool, $user, $roles, $return = false) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $tool->courseid), '*', MUST_EXIST);

    $manual = enrol_get_plugin('manual');
    $role =(in_array('instructor', $roles))? 'Instructor' : 'Learner';

    $today = time();
    $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);
    $timeend = 0;
    if ($tool->enrolperiod) {
            $timeend = $today + $tool->enrolperiod;
    }

    // Course role id for the Instructor or Learner
    // TODO Do something with lti system admin (urn:lti:sysrole:ims/lis/Administrator)
    $roleid = ($role == 'Instructor')? $tool->croleinst: $tool->crolelearn;

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
    $user->firstname = isset($context->info['lis_person_name_given'])? $context->info['lis_person_name_given'] : $context->getUserEmail();
    $user->lastname = isset($context->info['lis_person_name_family'])? $context->info['lis_person_name_family']: '';
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
 * Duplicate a course
 *
 * @param int $courseid
 * @param string $fullname Duplicated course fullname
 * @param int $newcourse Destination course
 * @param array $options List of backup options
 * @return stdClass New course info
 */
 function local_ltiprovider_duplicate_course($courseid, $newcourse, $visible = 1, $options = array()) {
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
            backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $admin->id, backup::TARGET_NEW_COURSE);

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

    return $course;
}

/**
 * Duplicates a Moodle module in an existing course
 * @param  int $cmid     Course module id
 * @param  int $courseid Course id
 * @return int           New course module id
 */
function local_ltiprovider_duplicate_module($cmid, $courseid) {
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

    if (empty($CFG->keeptempdirectoriesonbackup)) {
        fulldelete($backupbasepath);
    }
    return $newcmid;

}

if (!function_exists("create_module")) {
    /**
     * Create a module.
     *
     * It includes:
     *      - capability checks and other checks
     *      - create the module from the module info
     *
     * @param object $module
     * @return object the created module info
     */
    function create_module($moduleinfo) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/modlib.php');

        // Check manadatory attributs.
        $mandatoryfields = array('modulename', 'course', 'section', 'visible');
        if (plugin_supports('mod', $moduleinfo->modulename, FEATURE_MOD_INTRO, true)) {
            $mandatoryfields[] = 'introeditor';
        }
        foreach($mandatoryfields as $mandatoryfield) {
            if (!isset($moduleinfo->{$mandatoryfield})) {
                throw new moodle_exception('createmodulemissingattribut', '', '', $mandatoryfield);
            }
        }

        // Some additional checks (capability / existing instances).
        $course = $DB->get_record('course', array('id'=>$moduleinfo->course), '*', MUST_EXIST);
        list($module, $context, $cw) = can_add_moduleinfo($course, $moduleinfo->modulename, $moduleinfo->section);

        // Load module library.
        include_modulelib($module->name);

        // Add the module.
        $moduleinfo->module = $module->id;
        $moduleinfo = add_moduleinfo($moduleinfo, $course, null);

        return $moduleinfo;
    }
}


if (!function_exists("can_add_moduleinfo")) {

    /**
     * Check that the user can add a module. Also returns some information like the module, context and course section info.
     * The fucntion create the course section if it doesn't exist.
     *
     * @param object $course the course of the module
     * @param object $modulename the module name
     * @param object $section the section of the module
     * @return array list containing module, context, course section.
     */
    function can_add_moduleinfo($course, $modulename, $section) {
        global $DB;

        $module = $DB->get_record('modules', array('name'=>$modulename), '*', MUST_EXIST);

        $context = context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        course_create_sections_if_missing($course, $section);
        $cw = get_fast_modinfo($course)->get_section_info($section);

        if (!course_allowed_module($course, $module->name)) {
            print_error('moduledisable');
        }

        return array($module, $context, $cw);
    }

}

if (!function_exists("add_moduleinfo")) {
    /**
     * Add course module.
     *
     * The function does not check user capabilities.
     * The function creates course module, module instance, add the module to the correct section.
     * It also trigger common action that need to be done after adding/updating a module.
     *
     * @param object $moduleinfo the moudle data
     * @param object $course the course of the module
     * @param object $mform this is required by an existing hack to deal with files during MODULENAME_add_instance()
     * @return object the updated module info
     */
    function add_moduleinfo($moduleinfo, $course, $mform = null) {
        global $DB, $CFG;

        $moduleinfo->course = $course->id;
        $moduleinfo = set_moduleinfo_defaults($moduleinfo);

        if (!empty($course->groupmodeforce) or !isset($moduleinfo->groupmode)) {
            $moduleinfo->groupmode = 0; // Do not set groupmode.
        }

        if (!course_allowed_module($course, $moduleinfo->modulename)) {
            print_error('moduledisable', '', '', $moduleinfo->modulename);
        }

        // First add course_module record because we need the context.
        $newcm = new stdClass();
        $newcm->course           = $course->id;
        $newcm->module           = $moduleinfo->module;
        $newcm->instance         = 0; // Not known yet, will be updated later (this is similar to restore code).
        $newcm->visible          = $moduleinfo->visible;
        $newcm->visibleold       = $moduleinfo->visible;
        $newcm->groupmode        = $moduleinfo->groupmode;
        $newcm->groupingid       = $moduleinfo->groupingid;
        $newcm->groupmembersonly = $moduleinfo->groupmembersonly;
        $completion = new completion_info($course);
        if ($completion->is_enabled()) {
            $newcm->completion                = $moduleinfo->completion;
            $newcm->completiongradeitemnumber = $moduleinfo->completiongradeitemnumber;
            $newcm->completionview            = $moduleinfo->completionview;
            $newcm->completionexpected        = $moduleinfo->completionexpected;
        }
        if(!empty($CFG->enableavailability)) {
            $newcm->availablefrom             = $moduleinfo->availablefrom;
            $newcm->availableuntil            = $moduleinfo->availableuntil;
            $newcm->showavailability          = $moduleinfo->showavailability;
        }
        if (isset($moduleinfo->showdescription)) {
            $newcm->showdescription = $moduleinfo->showdescription;
        } else {
            $newcm->showdescription = 0;
        }

        if (!$moduleinfo->coursemodule = add_course_module($newcm)) {
            print_error('cannotaddcoursemodule');
        }

        if (plugin_supports('mod', $moduleinfo->modulename, FEATURE_MOD_INTRO, true)) {
            $introeditor = $moduleinfo->introeditor;
            unset($moduleinfo->introeditor);
            $moduleinfo->intro       = $introeditor['text'];
            $moduleinfo->introformat = $introeditor['format'];
        }

        $addinstancefunction    = $moduleinfo->modulename."_add_instance";
        $returnfromfunc = $addinstancefunction($moduleinfo, $mform);
        if (!$returnfromfunc or !is_number($returnfromfunc)) {
            // Undo everything we can.
            $modcontext = context_module::instance($moduleinfo->coursemodule);
            delete_context(CONTEXT_MODULE, $moduleinfo->coursemodule);
            $DB->delete_records('course_modules', array('id'=>$moduleinfo->coursemodule));

            if (!is_number($returnfromfunc)) {
                print_error('invalidfunction', '', course_get_url($course, $cw->section));
            } else {
                print_error('cannotaddnewmodule', '', course_get_url($course, $cw->section), $moduleinfo->modulename);
            }
        }

        $moduleinfo->instance = $returnfromfunc;

        $DB->set_field('course_modules', 'instance', $returnfromfunc, array('id'=>$moduleinfo->coursemodule));

        // Update embedded links and save files.
        $modcontext = context_module::instance($moduleinfo->coursemodule);
        if (!empty($introeditor)) {
            $moduleinfo->intro = file_save_draft_area_files($introeditor['itemid'], $modcontext->id,
                                                          'mod_'.$moduleinfo->modulename, 'intro', 0,
                                                          array('subdirs'=>true), $introeditor['text']);
            $DB->set_field($moduleinfo->modulename, 'intro', $moduleinfo->intro, array('id'=>$moduleinfo->instance));
        }

        // Course_modules and course_sections each contain a reference to each other.
        // So we have to update one of them twice.
        $sectionid = course_add_cm_to_section($course, $moduleinfo->coursemodule, $moduleinfo->section);

        // Make sure visibility is set correctly (in particular in calendar).
        // Note: allow them to set it even without moodle/course:activityvisibility.
        set_coursemodule_visible($moduleinfo->coursemodule, $moduleinfo->visible);

        if (isset($moduleinfo->cmidnumber)) { // Label.
            // Set cm idnumber - uniqueness is already verified by form validation.
            set_coursemodule_idnumber($moduleinfo->coursemodule, $moduleinfo->cmidnumber);
        }

        // Set up conditions.
        if ($CFG->enableavailability) {
            condition_info::update_cm_from_form((object)array('id'=>$moduleinfo->coursemodule), $moduleinfo, false);
        }

        $eventname = 'mod_created';

        add_to_log($course->id, "course", "add mod",
                   "../mod/$moduleinfo->modulename/view.php?id=$moduleinfo->coursemodule",
                   "$moduleinfo->modulename $moduleinfo->instance");
        add_to_log($course->id, $moduleinfo->modulename, "add",
                   "view.php?id=$moduleinfo->coursemodule",
                   "$moduleinfo->instance", $moduleinfo->coursemodule);

        $moduleinfo = edit_module_post_actions($moduleinfo, $course, 'mod_created');

        return $moduleinfo;
    }
}

if (!function_exists('set_moduleinfo_defaults')) {
    /**
     * Set module info default values for the unset module attributs.
     *
     * @param object $moduleinfo the current known data of the module
     * @return object the completed module info
     */
    function set_moduleinfo_defaults($moduleinfo) {
        global $DB;

        if (empty($moduleinfo->coursemodule)) {
            // Add.
            $cm = null;
            $moduleinfo->instance     = '';
            $moduleinfo->coursemodule = '';
        } else {
            // Update.
            $cm = get_coursemodule_from_id('', $moduleinfo->coursemodule, 0, false, MUST_EXIST);
            $moduleinfo->instance     = $cm->instance;
            $moduleinfo->coursemodule = $cm->id;
        }
        // For safety.
        $moduleinfo->modulename = clean_param($moduleinfo->modulename, PARAM_PLUGIN);

        if (!isset($moduleinfo->groupingid)) {
            $moduleinfo->groupingid = 0;
        }

        if (!isset($moduleinfo->groupmembersonly)) {
            $moduleinfo->groupmembersonly = 0;
        }

        if (!isset($moduleinfo->name)) { // Label.
            $moduleinfo->name = $moduleinfo->modulename;
        }

        if (!isset($moduleinfo->completion)) {
            $moduleinfo->completion = COMPLETION_DISABLED;
        }
        if (!isset($moduleinfo->completionview)) {
            $moduleinfo->completionview = COMPLETION_VIEW_NOT_REQUIRED;
        }

        // Convert the 'use grade' checkbox into a grade-item number: 0 if checked, null if not.
        if (isset($moduleinfo->completionusegrade) && $moduleinfo->completionusegrade) {
            $moduleinfo->completiongradeitemnumber = 0;
        } else {
            $moduleinfo->completiongradeitemnumber = null;
        }

        return $moduleinfo;
    }
}

if (!function_exists('include_modulelib')) {
    /**
     * Include once the module lib file.
     *
     * @param string $modulename module name of the lib to include
     */
    function include_modulelib($modulename) {
        global $CFG;
        $modlib = "$CFG->dirroot/mod/$modulename/lib.php";
        if (file_exists($modlib)) {
            include_once($modlib);
        } else {
            throw new moodle_exception('modulemissingcode', '', '', $modlib);
        }
    }
}