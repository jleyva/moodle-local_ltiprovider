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
require_once($CFG->dirroot.'/local/ltiprovider/locallib.php');
require_once($CFG->dirroot.'/local/ltiprovider/ims-blti/blti.php');

$toolid                 = optional_param('id', 0, PARAM_INT);
$lticontextid           = optional_param('contextid', false, PARAM_RAW);
$custom_create_context  = optional_param('custom_create_context', false, PARAM_BOOL);

if (!$toolid and !$lticontextid) {
    print_error('invalidtoolid', 'local_ltiprovider');
}

if (!$toolid and $lticontextid) {
    // Check if there is more that one course for this LTI context id.
    if ($DB->count_records('course', array('idnumber' => $lticontextid)) > 1) {
        print_error('cantdeterminecontext', 'local_ltiprovider');
    }
    if ($course = $DB->get_record('course', array('idnumber' => $lticontextid))) {
        // Look for a course created for this LTI context id.
        if ($coursecontext = get_context_instance(CONTEXT_COURSE, $course->id)) {
            if ($DB->count_records('local_ltiprovider', array('contextid' => $coursecontext->id)) > 1) {
                print_error('cantdeterminecontext', 'local_ltiprovider');
            }
            $toolid = $DB->get_field('local_ltiprovider', 'id', array('contextid' => $coursecontext->id));
        }
    }
}

$secret = '';
// We may expect a valid tool / context id or custom parameters.
if ($tool = $DB->get_record('local_ltiprovider', array('id'=>$toolid))) {
    if ($tool->disabled) {
        print_error('tooldisabled', 'local_ltiprovider');
    }
    $secret = $tool->secret;
} else if ($custom_create_context) {
    $secret = get_config('local_ltiprovider', 'globalsharedsecret');
}

if (!$secret) {
    print_error('invalidtoolid', 'local_ltiprovider');
}

// Do not set session, do not redirect
$context = new BLTI($secret, false, false);

// Correct launch request
if ($context->valid) {

    // Are we creating a new context (that means a new course tool)?
    if ($custom_create_context) {

        require_once("$CFG->dirroot/course/lib.php");
        $newcourse = new stdClass();
        $newcourse->fullname  = $context->info['context_title'];
        $newcourse->shortname = $context->info['context_label'];
        $newcourse->idnumber  = $context->info['context_id'];
        $course = create_course($newcourse);

        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

        // Create the tool that provide the full course.
        $tool = new stdClass();
        $tool->courseid = $course->id;
        $tool->contextid = $coursecontext->id;
        $tool->disabled = 0;
        $tool->sendgrades = 1;
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
        $tool->country = ES;
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

        $toolid = $DB->insert_record('local_ltiprovider', $tool);
        $tool->id = $toolid;

        // Are we using another course as template?
        // We have a setting for storing courses to be restored when the cron job is executed.
        $custom_context_template  = optional_param('custom_context_template', false, PARAM_RAW_TRIMMED);
        if ($custom_context_template and ($tplcourse = $DB->get_record('course', array('idnumber' => $custom_context_template), IGNORE_MULTIPLE))) {

            $newcourse = new stdClass();
            $newcouse->id = $tplcourse->id;
            $newcourse->destinationid = $course->id;
            $newcourse->restorestart = 0;
            $aid = $newcourse->id . "-" . $newcourse->destinationid;

            if ($croncourses = get_config('local_ltiprovider', 'croncourses')) {
                $croncourses = unserialize($croncourses);
                if (is_array($croncourses)) {
                    $croncourses[$aid] = $newcourse;
                } else {
                    $croncourses = array($aid => $newcourse);
                }
            } else {
                $croncourses = array($aid => $newcourse);
            }

            $croncourses = serialize($croncourses);
            set_config('croncourses', $croncourses, 'local_ltiprovider');

            // Add the waiting label.
            $section = new stdClass();
            $section->course = $course->id;
            $section->section = 0;
            $section->name = "";
            $section->summary = get_string("coursebeingrestored", "local_ltiprovider");
            $section->summaryformat = 1;
            $section->sequence = 10;
            $section->visible = 1;
            $section->availablefrom = 0;
            $section->availableuntil = 0;
            $section->showavailability = 0;
            $section->groupingid = 0;
            $DB->insert_record('course_sections', $section);
            rebuild_course_cache($course->id);
        }
    }

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
    // Later accounts add the ConsumerKey - we silently upgrade old accounts
    // Might want a flag for this -- Chuck
    $username = 'ltiprovider'.md5($context->getConsumerKey().'::'.$context->getUserKey());
    $dbuser = $DB->get_record('user', array('username' => $username));
    if ( ! $dbuser ) {
        $old_username = 'ltiprovider'.md5($context->getUserKey());
        $dbuser = $DB->get_record('user', array('username' => $old_username));
        if ( $dbuser ) {
            // Probably should log this
            $DB->set_field('user', 'username', $username, array('id' => $dbuser->id));
        }
        $dbuser = $DB->get_record('user', array('username' => $username));
    }



    // Check if the user exists
    $dbuser = $DB->get_record('user', array('username' => $username));
    if (! $dbuser ) {
        $user = new stdClass();

        // clean_param , email username text
        $auth = get_config('local_ltiprovider', 'defaultauthmethod');
        if ($auth) {
            $user->auth = $auth;
        } else {
            $user->auth = 'nologin';
        }

        $user->username = $username;
        $user->password = md5(uniqid(rand(), 1));
        local_ltiprovider_populate($user, $context, $tool);
        $user->id = $DB->insert_record('user', $user);
        // Reload full user
        $user = $DB->get_record('user', array('id' => $user->id));
        events_trigger('user_created', $user);
    } else {
        $user = new stdClass();
        local_ltiprovider_populate($user, $context, $tool);
        if ( local_ltiprovider_user_match($user, $dbuser) ) {
            $user = $dbuser;
        } else {
            $userprofileupdate = get_config('local_ltiprovider', 'userprofileupdate');
            if ($userprofileupdate == -1) {
                // Check the tool setting.
                $userprofileupdate = $tool->userprofileupdate;
            }
            if ($userprofileupdate) {
                $user = $dbuser;
                local_ltiprovider_populate($user, $context, $tool);
                $DB->update_record('user', $user);
                events_trigger('user_updated', $user);
            }
        }
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
    $roles = explode(',', strtolower($_POST['roles']));
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
                            echo get_string('maxenrolledreached', 'local_ltiprovider');
                            die;
                        }
                    }

                    $timenow = time();
                    if ($tool->enrolstartdate and $timenow < $tool->enrolstartdate) {
                        // We do not use print_error for the iframe issue allowframembedding
                        echo get_string('enrolmentnotstarted', 'local_ltiprovider');
                        die;
                    }
                    if ($tool->enrolenddate and $timenow > $tool->enrolenddate) {
                        // We do not use print_error for the iframe issue allowframembedding
                        echo get_string('enrolmentfinished', 'local_ltiprovider');
                        die;
                    }
                    // TODO, delete created users not enrolled

                    $manual->enrol_user($instance, $user->id, $roleid, $today, $timeend);
                }
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
    $serviceurl = optional_param('lis_outcome_service_url', '', PARAM_RAW);

    if ($userlog = $DB->get_record('local_ltiprovider_user', array('toolid' => $tool->id, 'userid' => $user->id))) {
        if ( $userlog->sourceid != $sourceid ) {
            $DB->set_field('local_ltiprovider_user', 'sourceid', $sourceid, array('id' => $userlog->id));
        }
        if ( $userlog->serviceurl != $serviceurl ) {
            $DB->set_field('local_ltiprovider_user', 'serviceurl', $serviceurl, array('id' => $userlog->id));
        }
        $DB->set_field('local_ltiprovider_user', 'lastaccess', time(), array('id' => $userlog->id));
    } else {
        // These data is needed for sending backup outcomes (aka grades)
        $userlog = new stdClass();
        $userlog->userid = $user->id;
        $userlog->toolid = $tool->id;
        // TODO Improve these checks
        $userlog->serviceurl = $serviceurl;
        $userlog->sourceid = $sourceid;
        $userlog->consumerkey = optional_param('oauth_consumer_key', '', PARAM_RAW);
        // TODO Do not store secret here
        $userlog->consumersecret = $secret;
        $userlog->lastsync = 0;
        $userlog->lastgrade = 0;
        $userlog->lastaccess = time();
        $DB->insert_record('local_ltiprovider_user', $userlog);
    }

    add_to_log(SITEID, 'user', 'login', $urltogo, "ltiprovider login", 0, $user->id);
    $tool->context = $context;
    $SESSION->ltiprovider = $tool;
    complete_user_login($user);

    // Moodle 2.2 and onwards
    if (isset($CFG->allowframembedding) and !$CFG->allowframembedding) {
        echo '<html>
        <head>
        </head>
        <body onload="window.open(\''. $urltogo .'\', \'_blank\');"></body>';
        echo get_string('newpopupnotice', 'local_ltiprovider');
        $stropentool = get_string('opentool', 'local_ltiprovider');
        echo "<p><a href=\"$urltogo\" target=\"_blank\">$stropentool</a></p>";
        echo "<p>".get_string('allowframembedding', 'local_ltiprovider')."</p>";
        echo '</html>';
    } else {
        redirect($urltogo);
    }
} else {
    // print_error('invalidcredentials', 'local_ltiprovider');
    echo $context->message;
}
