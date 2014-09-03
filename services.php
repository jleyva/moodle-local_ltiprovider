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
 * Main entry for extra services request.
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ltiprovider/locallib.php');
require_once($CFG->dirroot.'/local/ltiprovider/ims-blti/blti.php');

$service                = required_param('custom_service', PARAM_RAW_TRIMMED);
$toolid                 = optional_param('id', 0, PARAM_INT);
$lticontextid           = optional_param('context_id', false, PARAM_RAW);

if (isset($newinfo['custom_lti_message_encoded_base64']) and $newinfo['custom_lti_message_encoded_base64'] == 1) {
    $lticontextid = base64_decode($lticontextid);
    $service = base64_decode($service);
}

if (!$toolid and $lticontextid) {
    // Check if there is more that one course for this LTI context id.
    if ($DB->count_records('course', array('idnumber' => $lticontextid)) > 1) {
        print_error('cantdeterminecontext', 'local_ltiprovider');
    }

    if ($course = $DB->get_record('course', array('idnumber' => $lticontextid))) {
        // Look for a course created for this LTI context id.
        if ($coursecontext = context_course::instance($course->id)) {
            if ($DB->count_records('local_ltiprovider', array('contextid' => $coursecontext->id)) > 1) {
                print_error('cantdeterminecontext', 'local_ltiprovider');
            }
            $toolid = $DB->get_field('local_ltiprovider', 'id', array('contextid' => $coursecontext->id));
        }
    }
}

$secret = '';
// If we dont receive a request for a specific tool, we use the global shared secret.
if ($tool = $DB->get_record('local_ltiprovider', array('id' => $toolid))) {
    if ($tool->disabled) {
        print_error('tooldisabled', 'local_ltiprovider');
    }
    $secret = $tool->secret;
} else {
    $secret = get_config('local_ltiprovider', 'globalsharedsecret');
}

if (!$secret) {
    print_error('invalidtoolid', 'local_ltiprovider');
}

// Do not set session, do not redirect.
$context = new BLTI($secret, false, false);

// Correct launch request.
if ($context->valid) {

    set_time_limit(0);
    raise_memory_limit(MEMORY_EXTRA);

    // Are we creating a new context (that means a new course tool)?
    if ($service == 'create_context') {
        $custom_context_template  = $context->info['custom_context_template'];

        if (!$tplcourse = $DB->get_record('course', array('idnumber' => $custom_context_template), '*', IGNORE_MULTIPLE)) {
            print_error('invalidtplcourse', 'local_ltiprovider');
        }

        require_once("$CFG->dirroot/course/lib.php");
        $newcourse = new stdClass();
        $newcourse->fullname  = local_ltiprovider_get_new_course_info('fullname', $context);
        $newcourse->shortname = local_ltiprovider_get_new_course_info('shortname', $context);
        $newcourse->idnumber  = local_ltiprovider_get_new_course_info('idnumber', $context);

        $categories = $DB->get_records('course_categories', null, '', 'id', 0, 1);
        $category = array_shift($categories);
        $newcourse->category  = $category->id;

        $course = create_course($newcourse);

        $coursecontext = context_course::instance($course->id);

        // Create the tool that provide the full course.
        $tool = local_ltiprovider_create_tool($course->id, $coursecontext->id, $context);

        $username = local_ltiprovider_create_username($context->info['oauth_consumer_key'], $context->info['user_id']);
        $userrestoringid = $DB->get_field('user', 'id', array('username' => $username));

        // Duplicate course + users.
        $course = local_ltiprovider_duplicate_course($tplcourse->id, $course, 1,
                                            $options = array(array('name'   => 'users',
                                                                    'value' => 1)), $userrestoringid, $context);
        echo json_encode($course);

    } else if ($service == 'duplicate_resource') {
        $idnumber = $context->info['custom_resource_link_copy_id'];
        $resource_link_id = $context->info['resource_link_id'];

        if (!$tool) {
            print_error('missingrequiredtool', 'local_ltiprovider');
        }

        if (! $context = $DB->get_record('context', array('id' => $tool->contextid))) {
            print_error("invalidcontext");
        }

        if ($context->contextlevel != CONTEXT_COURSE) {
            print_error('invalidtypetool', 'local_ltiprovider');
        }

        if (!$cm = $DB->get_record('course_modules', array('idnumber' => $idnumber), '*', IGNORE_MULTIPLE)) {
            print_error('invalidresourcecopyid', 'local_ltiprovider');
        }

        $courseid = $context->instanceid;

        $cmid = local_ltiprovider_duplicate_module($cm->id, $courseid, $resource_link_id, $context);
        if ($cm = get_coursemodule_from_id(false, $cmid)) {
            echo json_encode($cm);
        }
    } else if ($service == 'force_logout') {
        // Force logout.
        $authsequence = get_enabled_auth_plugins();
        foreach ($authsequence as $authname) {
            $authplugin = get_auth_plugin($authname);
            $authplugin->logoutpage_hook();
        }

        require_logout();
    }

} else {
    echo $context->message;
}
