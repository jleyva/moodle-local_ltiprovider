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

$toolid                         = optional_param('id', 0, PARAM_INT);
$lticontextid                   = optional_param('context_id', false, PARAM_RAW);
$custom_create_context          = optional_param('custom_create_context', false, PARAM_RAW);

// Temporary context.
$mycontext = new stdClass();
$mycontext->info = array();
$mycontext->info['context_id'] = optional_param('context_id', false, PARAM_RAW);
$mycontext->info['context_title'] = optional_param('context_title', false, PARAM_RAW);
$mycontext->info['context_label'] = optional_param('context_label', false, PARAM_RAW);
$mycontext->info['oauth_consumer_key'] = optional_param('oauth_consumer_key', false, PARAM_RAW);
$mycontext->info['resource_link_id'] = optional_param('resource_link_id', false, PARAM_RAW);

if (optional_param('custom_lti_message_encoded_base64', 0, PARAM_INT) == 1) {
    $lticontextid = base64_decode($lticontextid);
    $custom_create_context = base64_decode($custom_create_context);
    $blti = new BLTI(false, false, false);
    $mycontext->info = $blti->decodeBase64($mycontext->info);
}

if (!$toolid and !$lticontextid) {
    print_error('invalidtoolid', 'local_ltiprovider');
}

if (!$toolid and $lticontextid) {
    // Check if there is more that one course for this LTI context id.
    $idnumber = local_ltiprovider_get_new_course_info('idnumber', $mycontext);
    if ($DB->count_records('course', array('idnumber' => $idnumber)) > 1) {
        print_error('cantdeterminecontext', 'local_ltiprovider');
    }
    if ($course = $DB->get_record('course', array('idnumber' => $idnumber))) {
        // Look for a course created for this LTI context id.
        if ($coursecontext = context_course::instance($course->id)) {
            if ($DB->count_records('local_ltiprovider', array('contextid' => $coursecontext->id)) > 1) {
                print_error('cantdeterminecontext', 'local_ltiprovider');
            }
            $toolid = $DB->get_field('local_ltiprovider', 'id', array('contextid' => $coursecontext->id));

            // Now check if we are accessing a resource/activity instead a course.
            $resource_link_id = $mycontext->info['resource_link_id'];
            if ($resource_link_id) {
                if ($cm = $DB->get_record('course_modules', array('idnumber' => $resource_link_id, 'course' => $course->id), '*', IGNORE_MULTIPLE)) {
                    $cmcontext  = context_module::instance($cm->id);

                    $toolinstances = $DB->count_records('local_ltiprovider', array('contextid' => $cmcontext->id));
                    // More than one tool for the same resource/activity.
                    if ($toolinstances and $toolinstances  > 1) {
                        print_error('cantdeterminecontext', 'local_ltiprovider');
                    }
                    if ($toolinstances) {
                        $toolid = $DB->get_field('local_ltiprovider', 'id', array('contextid' => $cmcontext->id));
                    }
                }
            }
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

        // Check if the remote user can create contexts, checking the remote role.
        $cancreate = false;
        $rolesallowedcreatecontexts = get_config('local_ltiprovider', 'rolesallowedcreatecontexts');
        if ($rolesallowedcreatecontexts) {
            $rolesallowedcreatecontexts = explode(',', strtolower($rolesallowedcreatecontexts));
            $roles = explode(',', strtolower($context->info['roles']));

            foreach ($roles as $rol) {
                if (in_array($rol, $rolesallowedcreatecontexts)) {
                    $cancreate = true;
                    break;
                }
            }

        }

        require_once("$CFG->dirroot/course/lib.php");
        $newcourse = new stdClass();
        $newcourse->fullname  = local_ltiprovider_get_new_course_info('fullname', $context);
        $newcourse->shortname = local_ltiprovider_get_new_course_info('shortname', $context);
        $newcourse->idnumber  = local_ltiprovider_get_new_course_info('idnumber', $context);

        $categories = $DB->get_records('course_categories', null, '', 'id', 0, 1);
        $category = array_shift($categories);
        $newcourse->category  = $category->id;

        // Course exists?? First try idnumber.
        $course = $DB->get_record('course', array('idnumber' => $newcourse->idnumber));

        // Then try shortname.
        if (!$course) {
            $course = $DB->get_record('course', array('shortname' => $newcourse->shortname));
        }

        if (!$cancreate and !$course) {
            print_error('rolecannotcreatecontexts', 'local_ltiprovider');
        }

        if (!$course) {
            $course = create_course($newcourse);

            $coursecontext = context_course::instance($course->id);

            // Create the tool that provide the full course.
            $tool = local_ltiprovider_create_tool($course->id, $coursecontext->id, $context);

            // Are we using another course as template?
            // We have a setting for storing courses to be restored when the cron job is executed.
            $custom_context_template  = $context->info['custom_context_template'];
            $tplcourse = $DB->get_record('course', array('idnumber' => $custom_context_template), '*', IGNORE_MULTIPLE);

            if ($custom_context_template and $tplcourse) {

                $username = local_ltiprovider_create_username($context->info['oauth_consumer_key'], $context->info['user_id']);
                $userrestoringid = $DB->get_field('user', 'id', array('username' => $username));;

                $newcourse = new stdClass();
                $newcourse->id = $tplcourse->id;
                $newcourse->destinationid = $course->id;
                $newcourse->userrestoringid = $userrestoringid;
                $newcourse->context = new stdClass;
                $newcourse->context->info['roles'] = $context->info['roles'];
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
                if ($section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => 0))) {
                    $section->summary = get_string("coursebeingrestored", "local_ltiprovider");
                    $DB->update_record('course_sections', $section);
                } else {
                    $DB->insert_record('course_sections', $section);
                }
                rebuild_course_cache($course->id);
            }
        } else {
            $coursecontext = context_course::instance($course->id);

            if (!$tool = $DB->get_record('local_ltiprovider', array('contextid' => $coursecontext->id))) {
                print_error('cantdeterminecontext', 'local_ltiprovider');
            }
        }
    }

    // Check that we can perform enrolments
    if (enrol_is_enabled('manual')) {
        $manual = enrol_get_plugin('manual');
    } else {
        print_error('nomanualenrol', 'local_ltiprovider');
    }

    // Transform to utf8 all the post and get data

    foreach ($_POST as $key => $value) {
        $_POST[$key] = core_text::convert($value, $tool->encoding);
    }
    foreach ($_GET as $key => $value) {
        $_GET[$key] = core_text::convert($value, $tool->encoding);
    }

    // We need an username without extended chars
    // Later accounts add the ConsumerKey - we silently upgrade old accounts
    // Might want a flag for this -- Chuck
    $username = local_ltiprovider_create_username($context->info['oauth_consumer_key'], $context->info['user_id']);
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
        // Trigger event.
        $event = \core\event\user_created::create(
            array(
                'objectid' => $user->id,
                'relateduserid' => $user->id,
                'context' => context_user::instance($user->id)
                )
             );
        $event->trigger();
    } else {
        $user = new stdClass();
        local_ltiprovider_populate($user, $context, $tool);
        if ( local_ltiprovider_user_match($user, $dbuser) ) {
            $user = $dbuser;
        } else {
            $user = $dbuser;
            $userprofileupdate = get_config('local_ltiprovider', 'userprofileupdate');
            if ($userprofileupdate == -1) {
                // Check the tool setting.
                $userprofileupdate = $tool->userprofileupdate;
            }
            if ($userprofileupdate) {
                local_ltiprovider_populate($user, $context, $tool);
                $DB->update_record('user', $user);

                // Trigger event.
                $event = \core\event\user_updated::create(
                    array(
                        'objectid' => $user->id,
                        'relateduserid' => $user->id,
                        'context' => context_user::instance($user->id)
                        )
                     );
                $event->trigger();
            }
        }
    }

    // Update user image.
    if (!empty($context->info['user_image']) or !empty($context->info['custom_user_image'])) {
        $userimageurl = (!empty($context->info['user_image'])) ? $context->info['user_image'] : $context->info['custom_user_image'];
        local_ltiprovider_update_user_profile_image($user->id, $userimageurl);
    }

    // Enrol user in course and activity if needed
    if (! $moodlecontext = $DB->get_record('context', array('id' => $tool->contextid))) {
        print_error("invalidcontext");
    }

    if ($moodlecontext->contextlevel == CONTEXT_COURSE) {
        $courseid = $moodlecontext->instanceid;
        $urltogo = $CFG->wwwroot.'/course/view.php?id='.$courseid;
        // Check if we have to redirect to a specific module in the course.
        $resource_link_id               = $context->info['resource_link_id'];
        if ($resource_link_id) {
            if ($cm = $DB->get_record('course_modules', array('idnumber' => $resource_link_id, 'course' => $courseid), '*', IGNORE_MULTIPLE)) {
                if ($cm = get_coursemodule_from_id(false, $cm->id, $courseid)) {
                    $urltogo = new moodle_url('/mod/' .$cm->modname. '/view.php', array('id' => $cm->id));
                }
            }
            // Detect it we must create the resource.
            if (!$cm) {
                $resource_link_title        = $context->info['resource_link_title'];
                $resource_link_description  = (isset($context->info['resource_link_description'])) ? $context->info['resource_link_description'] : false;
                $resource_link_type         = (isset($context->info['custom_resource_link_type'])) ? $context->info['custom_resource_link_type'] : false;
                if (!$resource_link_title) {
                    $resource_link_title  = $context->info['custom_resource_link_title'];
                }
                if (!$resource_link_description && isset($context->info['custom_resource_link_description'])) {
                    $resource_link_description  = $context->info['custom_resource_link_description'];
                }

                // Minimun for creating a module, title and type.
                if ($resource_link_title and $resource_link_type) {

                    // Check if the remote user can create modules, checking the remote role.
                    $rolesallowedcreateresources = get_config('local_ltiprovider', 'rolesallowedcreateresources');
                    if ($rolesallowedcreateresources) {
                        $rolesallowedcreateresources = explode(',', strtolower($rolesallowedcreateresources));
                        $roles = explode(',', strtolower($context->info['roles']));
                        $cancreate = false;

                        foreach ($roles as $rol) {
                            if (in_array($rol, $rolesallowedcreateresources)) {
                                $cancreate = true;
                                break;
                            }
                        }

                        if ($cancreate) {
                            require_once($CFG->dirroot . '/course/lib.php');
                            $moduleinfo = new stdClass();

                            // Always mandatory generic values to any module
                            // TODO, check for valid types.
                            $moduleinfo->modulename = $resource_link_type;
                            $moduleinfo->section = 1;
                            $moduleinfo->course = $courseid;
                            $moduleinfo->visible = true;
                            $moduleinfo->cmidnumber = $resource_link_id;

                            // Sometimes optional generic values for some modules
                            $moduleinfo->name= $resource_link_title;

                            // Optional intro editor (depends of module)
                            if ($resource_link_description) {
                                $draftid_editor = 0;
                                $USER = $user;
                                file_prepare_draft_area($draftid_editor, null, null, null, null);
                                $moduleinfo->introeditor = array('text'=> $resource_link_description, 'format'=>FORMAT_HTML, 'itemid'=>$draftid_editor);
                            }

                            // Add extra module info.
                            $modinfofile = $CFG->dirroot . '/local/ltiprovider/modinfo/' . $moduleinfo->modulename . '.php';
                            if (file_exists($modinfofile)) {
                                require_once($modinfofile);
                                foreach ($extramodinfo as $key => $val) {
                                    $moduleinfo->{$key} = $val;
                                }
                            }

                            $modinfo = create_module($moduleinfo);

                            if ($modinfo) {
                                $urltogo = new moodle_url('/course/modedit.php', array('update' => $modinfo->coursemodule));
                            }
                        } else {
                            print_error('rolecannotcreateresources', 'local_ltiprovider');
                        }
                    }
                }
            }
        }

        // Duplicate an existing resource on SSO.
        $custom_resource_link_copy_id = (!empty($context->info['custom_resource_link_copy_id'])) ? $context->info['custom_resource_link_copy_id'] : false;
        if ($custom_resource_link_copy_id) {
            if (!$cm = $DB->get_record('course_modules', array('idnumber' => $custom_resource_link_copy_id), '*', IGNORE_MULTIPLE)) {
                print_error('invalidresourcecopyid', 'local_ltiprovider');
            }
            $newcmid = local_ltiprovider_duplicate_module($cm->id, $courseid, $resource_link_id, $context);
            if ($cm = get_coursemodule_from_id(false, $newcmid)) {
                $urltogo = new moodle_url('/mod/' .$cm->modname. '/view.php', array('id' => $cm->id));
            }
        }

    } else if ($moodlecontext->contextlevel == CONTEXT_MODULE) {
        $cmid = $moodlecontext->instanceid;
        $cm = get_coursemodule_from_id(false, $moodlecontext->instanceid, 0, false, MUST_EXIST);
        $courseid = $cm->course;
        $urltogo = $CFG->wwwroot.'/mod/'.$cm->modname.'/view.php?id='.$cm->id;
    } else {
        print_error("invalidcontext");
    }

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

    // Enrol the user in the course
    $isinstructor = $context->isInstructor();
    local_ltiprovider_enrol_user($tool, $user, $isinstructor);

    if ($moodlecontext->contextlevel == CONTEXT_MODULE) {
        $role = $isinstructor ? 'instructor' : 'learner';

        // Enrol the user in the activity
        if (($tool->aroleinst and $isinstructor) or ($tool->arolelearn and !$isinstructor)) {
            $roleid = $isinstructor ? $tool->aroleinst : $tool->arolelearn;
            role_assign($roleid, $user->id, $tool->contextid);
        }
    }

    // Login user
    $sourceid =     (!empty($context->info['lis_result_sourcedid'])) ? $context->info['lis_result_sourcedid'] : '';
    $serviceurl =   (!empty($context->info['lis_outcome_service_url'])) ? $context->info['lis_outcome_service_url'] : '';

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
        $userlog->consumerkey = $context->info['oauth_consumer_key'];
        // TODO Do not store secret here
        $userlog->consumersecret = $secret;
        $userlog->lastsync = 0;
        $userlog->lastgrade = 0;
        $userlog->lastaccess = time();
        $userlog->membershipsurl = (!empty($context->info['ext_ims_lis_memberships_url'])) ? $context->info['ext_ims_lis_memberships_url']: '';
        $userlog->membershipsid =  (!empty($context->info['ext_ims_lis_memberships_id']))  ? $context->info['ext_ims_lis_memberships_id'] : '';
        $DB->insert_record('local_ltiprovider_user', $userlog);
    }

    $tool->context = $moodlecontext;

    $indexes = array('custom_force_navigation', 'custom_hide_left_blocks', 'custom_hide_right_blocks',
                        'custom_hide_page_header', 'custom_hide_page_footer', 'custom_custom_css', 'custom_show_blocks' );

    foreach ($indexes as $i) {
        if (empty($context->info[$i])) {
            $context->info[$i] = '';
        }
    }

    // Override some settings.
    if ($custom_force_navigation = $context->info['custom_force_navigation']) {
        $tool->forcenavigation = 1;
    }
    if ($custom_hide_left_blocks = $context->info['custom_hide_left_blocks']) {
        $tool->hideleftblocks = 1;
    }
    if ($custom_hide_right_blocks = $context->info['custom_hide_right_blocks']) {
        $tool->hiderightblocks = 1;
    }
    if ($custom_hide_page_header = $context->info['custom_hide_page_header']) {
        $tool->hidepageheader = 1;
    }
    if ($custom_hide_page_footer = $context->info['custom_hide_page_footer']) {
        $tool->hidepagefooter = 1;
    }
    if ($custom_custom_css = $context->info['custom_custom_css']) {
        $tool->customcss = $custom_custom_css;
    }
    if ($custom_show_blocks = $context->info['custom_show_blocks']) {
        $tool->showblocks = $custom_show_blocks;
    }

    $SESSION->ltiprovider = $tool;

    complete_user_login($user);

    // Trigger login event.
    $event = \core\event\user_loggedin::create(
        array(
          'userid' => $user->id,
          'objectid' => $user->id,
          'other' => array('username' => $user->username),
        ));
    $event->trigger();

    // Moodle 2.2 and onwards.
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
