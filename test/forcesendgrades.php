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
 * Force send grades back (overwritten). You can force by course, tool and userid (paremeters $courseid, $toolid, $userid)
 * Completion check can be omitted too ($omitcompletion)
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');

require_once($CFG->dirroot . "/local/ltiprovider/lib.php");
require_once($CFG->dirroot . "/local/ltiprovider/locallib.php");
require_once($CFG->dirroot . "/local/ltiprovider/ims-blti/OAuth.php");
require_once($CFG->dirroot . "/local/ltiprovider/ims-blti/OAuthBody.php");
require_once($CFG->libdir . "/gradelib.php");
require_once($CFG->dirroot . "/grade/querylib.php");

use moodle\local\ltiprovider as ltiprovider;

ini_set("display_erros", 1);
error_reporting(E_ALL);

$courseid = optional_param('courseid', 0, PARAM_INT);
$toolid = optional_param('toolid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$omitcompletion = optional_param('omitcompletion', 0, PARAM_BOOL);
$printresponse = optional_param('printresponse', 0, PARAM_BOOL);

require_login();
require_capability('moodle/site:config', context_system::instance());

@header('Content-Type: text/plain; charset=utf-8');

if ($tools = $DB->get_records_select('local_ltiprovider', 'disabled = ? AND sendgrades = ?', array(0, 1))) {
    foreach ($tools as $tool) {

        if (!empty($courseid) and $courseid != $tool->courseid) {
            mtrace(" Omitting course $tool->courseid");
            continue;
        }
        if (!empty($toolid) and $toolid != $tool->id) {
            mtrace(" Omitting tool $tool->id");
            continue;
        }

        mtrace(" Starting sync tool for grades id $tool->id course id $tool->courseid");

        if ($omitcompletion) {
            $tool->requirecompletion = 0;
        }

        if ($tool->requirecompletion) {
            mtrace("  Grades require activity or course completion");
        }
        $user_count = 0;
        $send_count = 0;
        $error_count = 0;

        $completion = new completion_info(get_course($tool->courseid));

        if ($users = $DB->get_records('local_ltiprovider_user', array('toolid' => $tool->id))) {
            foreach ($users as $user) {
                if (!empty($userid) and $userid != $user->userid) {
                    mtrace(" Omitting user $user->userid");
                    continue;
                }
                mtrace("   Sending grades for user $user->userid");
                $user_count = $user_count + 1;
                // This can happen is the sync process has an unexpected error
                if ( strlen($user->serviceurl) < 1 ) {
                    mtrace("   Empty serviceurl");
                    continue;
                }
                if ( strlen($user->sourceid) < 1 ) {
                    mtrace("   Empty sourceid");
                    continue;
                }

                $grade = false;
                if ($context = $DB->get_record('context', array('id' => $tool->contextid))) {
                    if ($context->contextlevel == CONTEXT_COURSE) {

                        if ($tool->requirecompletion and !$completion->is_course_complete($user->userid)) {
                            mtrace("   Skipping user $user->userid since he didn't complete the course");
                            continue;
                        }

                        if ($grade = grade_get_course_grade($user->userid, $tool->courseid)) {
                            $grademax = floatval($grade->item->grademax);
                            $grade = $grade->grade;
                        }
                    } else if ($context->contextlevel == CONTEXT_MODULE) {
                        $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);

                        if ($tool->requirecompletion) {
                            $data = $completion->get_data($cm, false, $user->userid);
                            if ($data->completionstate != COMPLETION_COMPLETE_PASS and $data->completionstate != COMPLETION_COMPLETE) {
                                mtrace("   Skipping user $user->userid since he didn't complete the activity");
                                continue;
                            }
                        }

                        $grades = grade_get_grades($cm->course, 'mod', $cm->modname, $cm->instance, $user->userid);
                        if (empty($grades->items[0]->grades)) {
                            $grade = false;
                        } else {
                            $grade = reset($grades->items[0]->grades);
                            if (!empty($grade->item)) {
                                $grademax = floatval($grade->item->grademax);
                            } else {
                                $grademax = floatval($grades->items[0]->grademax);
                            }
                            $grade = $grade->grade;
                        }
                    }

                    if ( $grade === false || $grade === NULL || strlen($grade) < 1) {
                        mtrace("   Invalid grade $grade");
                        continue;
                    }

                    // No need to be dividing by zero
                    if ( $grademax == 0.0 ) $grademax = 100.0;

                    // TODO: Make lastgrade should be float or string - but it is integer so we truncate
                    // TODO: Then remove those intval() calls

                    // Don't double send
                    if ( intval($grade) == $user->lastgrade ) {
                        mtrace("   Last grade send is equal to current grade");
                    }

                    // We sync with the external system only when the new grade differs with the previous one
                    // TODO - Global setting for check this
                    if ($grade >= 0 and $grade <= $grademax) {
                        $float_grade = $grade / $grademax;
                        $body = local_ltiprovider_create_service_body($user->sourceid, $float_grade);

                        try {
                            $response = ltiprovider\sendOAuthBodyPOST('POST', $user->serviceurl, $user->consumerkey, $user->consumersecret, 'application/xml', $body);
                        } catch (Exception $e) {
                            mtrace(" Exception".$e->getMessage());
                            $error_count = $error_count + 1;
                            mtrace("Invalid $response " . $response);
                            continue;
                        }

                        if ($printresponse) {
                            mtrace("   Remote system response: \n    $response\n");
                        }

                        // TODO - Check for errors in $retval in a correct way (parsing xml)
                        if (strpos(strtolower($response), 'success') !== false) {

                            $DB->set_field('local_ltiprovider_user', 'lastgrade', intval($grade), array('id' => $user->id));
                            mtrace(" User grade sent to remote system. userid: $user->userid grade: $float_grade");
                            $send_count = $send_count + 1;
                        } else {
                            mtrace(" User grade send failed. userid: $user->userid grade: $float_grade: " . $response);
                            $error_count = $error_count + 1;
                        }
                    } else {
                        mtrace(" User grade for user $user->userid out of range: grade = ".$grade);
                        $error_count = $error_count + 1;
                    }
                } else {
                    mtrace(" Invalid context: contextid = ".$tool->contextid);
                }
            }
        }
        mtrace(" Completed sync tool id $tool->id course id $tool->courseid users=$user_count sent=$send_count errors=$error_count");
    }
}
