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
 * Launch destination url. Main entry point for the external system.
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ltiprovider/ims-blti/blti.php');

$toolid = required_param('id', PARAM_INT);

if (! ($tool = $DB->get_record('local_ltiprovider', array('id'=>$toolid)))) {
    print_error('invalidtoolid', 'local_ltiprovider');
}

if ($tool->disabled) {
    print_error('tooldisabled', 'local_ltiprovider');
}


// Do not set session, do not redirect
$context = new BLTI($tool->secret, false, false);

// Correct launch request
if ($context->valid) {

    // Check that we can perform enrolments
    if (enrol_is_enabled('manual')) {
        $manual = enrol_get_plugin('manual');
    } else {
        print_error('nomanualenrol', 'local_ltiprovider');
    }

    // Transform to utf8 all the post and get data
    $textlib = textlib_get_instance();
    foreach ($_POST as $key => $value) {
        $_POST[$key] = $textlib->convert($value, $tool->encoding);
    }
    foreach ($_GET as $key => $value) {
        $_GET[$key] = $textlib->convert($value, $tool->encoding);
    }

    // We need an username without extended chars
    $username = 'ltiprovider'.md5($context->getUserKey());

    // Check if the user exists
    $user = $DB->get_record('user', array('username' => $username));
    if (! $user) {
        $user = new stdClass();
        // clean_param , email username text
        $user->auth = 'nologin';
        $user->username = $username;
        $user->password = md5(uniqid(rand(), 1));
        $user->firstname = optional_param('lis_person_name_given', '', PARAM_TEXT);
        $user->lastname = optional_param('lis_person_name_family', '', PARAM_TEXT);
        $user->email = clean_param($context->getUserEmail(), PARAM_EMAIL);
        $user->city = $tool->city;
        $user->country = $tool->country;
        $user->institution = $tool->institution;
        $user->timezone = $tool->timezone;
        $user->maildisplay = $tool->maildisplay;

        $user->lang = $tool->lang;
        if (! $user->lang and isset($_POST['launch_presentation_locale'])) {
            $user->lang = optional_param('launch_presentation_locale', '', PARAM_LANG);
        }
        if (! $user->lang) {
            // TODO: This should be changed for detect the course lang
            $user->lang = current_language();
        }

        $user->id = $DB->insert_record('user', $user);
        // Reload full user
        $user = $DB->get_record('user', array('id' => $user->id));
    }

    // Enrol user in course and activity if needed
    if (! $context = $DB->get_record('context', array('id' => $tool->contextid))) {
        print_error("invalidcontext");
    }

    if ($context->contextlevel == CONTEXT_COURSE) {
        $courseid = $context->instanceid;
        $urltogo = $CFG->wwwroot.'/course/view.php?id='.$courseid;
    } else if ($context->contextlevel == CONTEXT_MODULE) {
        $cmid = $context->instanceid;
        $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
        $courseid = $cm->course;
        $urltogo = $CFG->wwwroot.'/mod/'.$cm->modname.'/view.php?id='.$cm->id;
    } else {
        print_error("invalidcontext");
    }

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

    // Enrol the user in the course
    $roles = explode(',', $_POST['roles']);
    $role =(in_array('Instructor', $roles))? 'Instructor' : 'Learner';

    $today = time();
    $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);
    $timeend = 0;
    if (isset($tool->enrolduration) and $tool->enrolduration) {
        $duration = (int)$tool->enrolduration * 60*60*24; // convert days to seconds
        if ($duration > 0) { // sanity check
            $timeend = $today + $duration;
        }
    }
    // Course role id for the Instructor or Learner
    // TODO Do something with lti system admin (urn:lti:sysrole:ims/lis/Administrator)
    $roleid = ($role == 'Instructor')? $tool->croleinst: $tool->crolelearn;

    if ($instances = enrol_get_instances($course->id, false)) {
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manual->enrol_user($instance, $user->id, $roleid, $today, $timeend);
                break;
            }
        }
    }

    if ($context->contextlevel == CONTEXT_MODULE) {
        // Enrol the user in the activity
        if (($tool->aroleinst and $role == 'Instructor') or ($tool->arolelearn and $role == 'Learner')) {
            $roleid = ($role == 'Instructor')? $tool->aroleinst: $tool->arolelearn;
            role_assign($roleid, $user->id, $tool->contextid);
        }
    }

    // Login user
    $sourceid = optional_param('lis_result_sourcedid', '', PARAM_RAW);

    if ($userlog = $DB->get_record('local_ltiprovider_user', array('toolid' => $tool->id, 'userid' => $user->id))) {
        $DB->set_field('local_ltiprovider_user', 'sourceid', $sourceid, array('id' => $userlog->id));
        $DB->set_field('local_ltiprovider_user', 'lastaccess', time(), array('id' => $userlog->id));
    } else {
        // These data is needed for sending backup outcomes (aka grades)
        $userlog = new stdClass();
        $userlog->userid = $user->id;
        $userlog->toolid = $tool->id;
        // TODO Improve these checks
        $userlog->serviceurl = optional_param('lis_outcome_service_url', '', PARAM_RAW);
        $userlog->sourceid = $sourceid;
        $userlog->consumerkey = optional_param('oauth_consumer_key', '', PARAM_RAW);
        // TODO Do not store secret here
        $userlog->consumersecret = $tool->secret;
        $userlog->lastsync = 0;
        $userlog->lastgrade = 0;
        $userlog->lastaccess = time();
        $DB->insert_record('local_ltiprovider_user', $userlog);
    }

    add_to_log(SITEID, 'user', 'login', $urltogo, "ltiprovider login", 0, $user->id);
    $tool->context = $context;
    $SESSION->ltiprovider = $tool;
    complete_user_login($user);
    redirect($urltogo);
} else {
    //print_error('invalidcredentials', 'local_ltiprovider');
    echo $context->message;
}