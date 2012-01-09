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
/**
 * Change the navigation block and bar only for external users
 * Force course or activity navigation and modify CSS also
 * Please note that this function is only called in pages where the navigation block is present
 *
 * @global moodle_user $USER
 * @global moodle_database $DB
 * @param navigation_node $nav Current navigation object
 */
function ltiprovider_extends_navigation ($nav) {
    global $USER, $PAGE, $SESSION;

    // Check capabilities for tool providers
    // TODO Change this for Moodle 2.3:
    // use extend_navigation_course and extend_navigation_user
    if ($PAGE->course->id and $PAGE->course->id != SITEID and has_capability('local/ltiprovider:view', $PAGE->context)) {
        $ltiurl = new moodle_url('/local/ltiprovider/index.php', array('courseid' => $PAGE->course->id));
        $coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE);
        $coursenode->add(get_string('pluginname', 'local_ltiprovider'), $ltiurl, $nav::TYPE_SETTING, null, 'ltiprovider'.$PAGE->course->id);
    }

    if (isset($USER) and isset($USER->auth) and $USER->auth == 'nologin' and strpos($USER->username, 'ltiprovider') === 0) {
        // Force course or activity navigation
        if (isset($SESSION->ltiprovider) and $SESSION->ltiprovider->forcenavigation) {
            $context = $SESSION->ltiprovider->context;
            $urltogo = '';
            if ($context->contextlevel == CONTEXT_COURSE and $PAGE->course->id != $SESSION->ltiprovider->courseid) {
                $urltogo = new moodle_url('/course/view.php', array('id' => $SESSION->ltiprovider->courseid));
            } else if ($context->contextlevel == CONTEXT_MODULE and $PAGE->context->id != $context->id) {
                $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
                $urltogo = new moodle_url('/mod/'.$cm->modname.'/view.php', array('id' => $cm->id));
            }

            if ($urltogo) {
                redirect($urltogo);
            }
        }

        // Delete all the navigation nodes except the course one
        $coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE);
        foreach (array('myprofile', 'users', 'site', 'home', 'myhome', 'mycourses', 'courses', '1') as $nodekey) {
            if ($node = $nav->get($nodekey)) {
                $node->remove();
            }
        }
        $nav->children->add($coursenode);

        // Custom CSS
        if (isset($SESSION->ltiprovider)) {
            $PAGE->requires->css(new moodle_url('/local/ltiprovider/styles.php', array('id' => $SESSION->ltiprovider->id)));
        }
    }
}

/**
 * Add new tool.
 *
 * @param  object $tool
 * @return int
 */
function ltiprovider_add_tool($tool) {
    global $DB;

    if (!isset($tool->disabled)) {
        $tool->disabled = 0;
    }
    if (!isset($tool->timecreated)) {
        $tool->timecreated = time();
    }
    if (!isset($tool->timemodified)) {
        $tool->timemodified = $tool->timecreated;
    }

    $tool->sendgrades = (isset($tool->sendgrades)) ? 1 : 0;
    $tool->forcenavigation = (isset($tool->forcenavigation)) ? 1 : 0;
    $tool->hidepageheader = (isset($tool->hidepageheader)) ? 1 : 0;
    $tool->hidepagefooter = (isset($tool->hidepagefooter)) ? 1 : 0;
    $tool->hideleftblocks = (isset($tool->hideleftblocks)) ? 1 : 0;
    $tool->hiderightblocks = (isset($tool->hiderightblocks)) ? 1 : 0;

    $tool->id = $DB->insert_record('local_ltiprovider', $tool);

    return $tool->id;
}

/**
 * Update existing tool.
 * @param  object $tool
 * @return void
 */
function ltiprovider_update_tool($tool) {
    global $DB;

    $tool->timemodified = time();

    $tool->sendgrades = (isset($tool->sendgrades)) ? 1 : 0;
    $tool->forcenavigation = (isset($tool->forcenavigation)) ? 1 : 0;
    $tool->hidepageheader = (isset($tool->hidepageheader)) ? 1 : 0;
    $tool->hidepagefooter = (isset($tool->hidepagefooter)) ? 1 : 0;
    $tool->hideleftblocks = (isset($tool->hideleftblocks)) ? 1 : 0;
    $tool->hiderightblocks = (isset($tool->hiderightblocks)) ? 1 : 0;

    $DB->update_record('local_ltiprovider', $tool);
}

/**
 * Delete tool.
 * @param  object $tool
 * @return void
 */
function ltiprovider_delete_tool($tool) {
    global $DB;

    $DB->delete_records('local_ltiprovider', array('id'=>$tool->id));
}

/**
 * Cron function for sync grades
 * @return void
 */
function local_ltiprovider_cron() {
    global $DB, $CFG;
    require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuth.php");
    require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuthBody.php");
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/querylib.php');

    // TODO - Add a global setting for this
    $synctime = 60*60;  // Every 1 hour grades are sync
    $timenow = time();

    mtrace('Running cron for ltiprovider');
    if ($tools = $DB->get_records_select('local_ltiprovider', 'disabled = ? AND sendgrades = ?', array(0, 1))) {
        foreach ($tools as $tool) {
            if ($tool->lastsync + $synctime < $timenow) {
                mtrace(" Sync tool id $tool->id course id $tool->courseid");
                if ($users = $DB->get_records('local_ltiprovider_user', array('toolid' => $tool->id))) {
                    foreach ($users as $user) {
                        // This can happen is the sync process has an unexpected error
                        if ($user->lastsync > $tool->lastsync) {
                            continue;
                        }

                        $grade = false;
                        if ($context = $DB->get_record('context', array('id' => $tool->contextid))) {
                            if ($context->contextlevel == CONTEXT_COURSE) {

                                if ($grade = grade_get_course_grade($user->userid, $tool->courseid)) {
                                    $grade = $grade->grade;
                                }
                            } else if ($context->contextlevel == CONTEXT_MODULE) {

                                $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
                                $grades = grade_get_grades($cm->course, 'mod', $cm->modname, $cm->id, $user->userid);
                                if (empty($grades->items[0]->grades)) {
                                    $grade = false;
                                } else {
                                    $grade = reset($grades->items[0]->grades);
                                    $grade = $grade->grade;
                                }
                            }

                            // We sync with the external system only when the new grade differs with the previous one
                            // TODO - Global setting for check this
                            // I assume base 100 grades
                            if ($grade !== false and $grade != $user->lastgrade and $grade > 0 and $grade <= 100) {
                                $grade = $grade / 100;

                                $body = ltiprovider_create_service_body($user->sourceid, $grade);

                                $response = sendOAuthBodyPOST('POST', $user->serviceurl, $user->consumerkey, $user->consumersecret, 'application/xml', $body);
                                // TODO - Check for errors in $retval in a correct way (parsing xml)

                                if (strpos(strtolower($response), 'success') !== false) {

                                    $DB->set_field('local_ltiprovider_user', 'lastsync', $timenow, array('id' => $user->id));
                                    $DB->set_field('local_ltiprovider_user', 'lastgrade', $grade, array('id' => $user->id));
                                    mtrace("User grade send to remote system. userid: $user->userid grade: $grade");
                                } else {
                                    mtrace("User grade send failed: ".$retval);
                                }
                            }
                        }
                    }
                }
                $DB->set_field('local_ltiprovider', 'lastsync', $timenow, array('id' => $tool->id));
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