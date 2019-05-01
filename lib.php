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
require_once($CFG->dirroot.'/local/ltiprovider/locallib.php');

use moodle\local\ltiprovider as ltiprovider;

/**
 * Display the LTI settings in the course settings block
 * For 2.3 and onwards
 *
 * @param  settings_navigation $nav     The settings navigatin object
 * @param  stdclass            $context Course context
 */
function local_ltiprovider_extend_settings_navigation(settings_navigation $nav, $context) {
    if ($context->contextlevel >= CONTEXT_COURSE and ($branch = $nav->get('courseadmin'))
        and has_capability('local/ltiprovider:view', $context)) {
        $ltiurl = new moodle_url('/local/ltiprovider/index.php', array('courseid' => $context->instanceid));
        $branch->add(get_string('pluginname', 'local_ltiprovider'), $ltiurl, $nav::TYPE_CONTAINER, null, 'ltiprovider'.$context->instanceid);
    }
}

/**
 * Change the navigation block and bar only for external users
 * Force course or activity navigation and modify CSS also
 * Please note that this function is only called in pages where the navigation block is present
 *
 * @global moodle_user $USER
 * @global moodle_database $DB
 * @param navigation_node $nav Current navigation object
 */
function local_ltiprovider_extend_navigation ($nav) {
    global $CFG, $USER, $PAGE, $SESSION, $ME;

    if (isset($USER) and isset($USER->auth) and strpos($USER->username, 'ltiprovider') === 0) {
        // Force course or activity navigation.
        if (isset($SESSION->ltiprovider) and $SESSION->ltiprovider->forcenavigation) {
            $context = $SESSION->ltiprovider->context;
            $urltogo = '';
            if ($context->contextlevel == CONTEXT_COURSE and $PAGE->course->id != $SESSION->ltiprovider->courseid) {
                $urltogo = new moodle_url('/course/view.php', array('id' => $SESSION->ltiprovider->courseid));
            } else if ($context->contextlevel == CONTEXT_MODULE and $PAGE->context->id != $context->id) {
                $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
                $urltogo = new moodle_url('/mod/'.$cm->modname.'/view.php', array('id' => $cm->id));
            }

            // Special case, user policy, we don't have to do nothing to avoid infinites loops.
            if (strpos($ME, 'user/policy.php')) {
                return;
            }

            if ($urltogo) {
                local_ltiprovider_call_hook("navigation", $nav);
                if (!$PAGE->requires->is_head_done()) {
                    $PAGE->set_state($PAGE::STATE_IN_BODY);
                }
                redirect($urltogo);
            }
        }

        // Delete all the navigation nodes except the course one.
        if ($coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE)) {
            foreach (array('myprofile', 'users', 'site', 'home', 'myhome', 'mycourses', 'courses', '1') as $nodekey) {
                if ($node = $nav->get($nodekey)) {
                    $node->remove();
                }
            }
            $nav->children->add($coursenode);
        }

        // Custom CSS.
        if (isset($SESSION->ltiprovider) and !$PAGE->requires->is_head_done()) {
            $PAGE->requires->css(new moodle_url('/local/ltiprovider/styles.php', array('id' => $SESSION->ltiprovider->id)));
        } elseif (isset($SESSION->ltiprovider) && isset($SESSION->ltiprovider->id)) {
            $url = new moodle_url('/local/ltiprovider/styles.js.php',
                                    array('id' => $SESSION->ltiprovider->id, 'rand' => rand(0, 1000)));
            $PAGE->requires->js($url);
        }

        local_ltiprovider_call_hook("navigation", $nav);
    }
}

/**
 * Add new tool.
 *
 * @param  object $tool
 * @return int
 */
function local_ltiprovider_add_tool($tool) {
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

    if (!isset($tool->sendgrades)) {
        $tool->sendgrades = 0;
    }
    if (!isset($tool->forcenavigation)) {
        $tool->forcenavigation = 0;
    }
    if (!isset($tool->enrolinst)) {
        $tool->enrolinst = 0;
    }
    if (!isset($tool->enrollearn)) {
        $tool->enrollearn = 0;
    }
    if (!isset($tool->hidepageheader)) {
        $tool->hidepageheader = 0;
    }
    if (!isset($tool->hidepagefooter)) {
        $tool->hidepagefooter = 0;
    }
    if (!isset($tool->hideleftblocks)) {
        $tool->hideleftblocks = 0;
    }
    if (!isset($tool->hiderightblocks)) {
        $tool->hiderightblocks = 0;
    }
    if (!isset($tool->syncmembers)) {
        $tool->syncmembers = 0;
    }

    $tool->id = $DB->insert_record('local_ltiprovider', $tool);
    local_ltiprovider_call_hook('save_settings', $tool);

    return $tool->id;
}

/**
 * Update existing tool.
 * @param  object $tool
 * @return void
 */
function local_ltiprovider_update_tool($tool) {
    global $DB;

    $tool->timemodified = time();

    if (!isset($tool->sendgrades)) {
        $tool->sendgrades = 0;
    }
    if (!isset($tool->forcenavigation)) {
        $tool->forcenavigation = 0;
    }
    if (!isset($tool->enrolinst)) {
        $tool->enrolinst = 0;
    }
    if (!isset($tool->enrollearn)) {
        $tool->enrollearn = 0;
    }
    if (!isset($tool->hidepageheader)) {
        $tool->hidepageheader = 0;
    }
    if (!isset($tool->hidepagefooter)) {
        $tool->hidepagefooter = 0;
    }
    if (!isset($tool->hideleftblocks)) {
        $tool->hideleftblocks = 0;
    }
    if (!isset($tool->hiderightblocks)) {
        $tool->hiderightblocks = 0;
    }
    if (!isset($tool->syncmembers)) {
        $tool->syncmembers = 0;
    }

    local_ltiprovider_call_hook('save_settings', $tool);
    $DB->update_record('local_ltiprovider', $tool);
}

/**
 * Delete tool.
 * @param  object $tool
 * @return void
 */
function local_ltiprovider_delete_tool($tool) {
    global $DB;
    $DB->delete_records('local_ltiprovider_user', array('toolid' => $tool->id));
    $DB->delete_records('local_ltiprovider', array('id' => $tool->id));
}

/**
 * Cron function for sync grades
 * @return void
 */
function local_ltiprovider_cron() {
    global $DB, $CFG;
    require_once($CFG->dirroot."/local/ltiprovider/locallib.php");
    require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuth.php");
    require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuthBody.php");
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/querylib.php');

    // TODO - Add a global setting for this
    $synctime = 60*60;  // Every 1 hour grades are sync
    $timenow = time();

    mtrace('Running cron for ltiprovider');

    mtrace('Deleting LTI tools assigned to deleted courses');
    $query = "DELETE FROM {local_ltiprovider} where courseid NOT IN (SELECT id FROM {course})";
    $DB->execute($query);
    $query = "DELETE FROM {local_ltiprovider_user} where toolid NOT IN (SELECT id FROM {local_ltiprovider})";
    $DB->execute($query);

    // Grades service.
    if ($tools = $DB->get_records_select('local_ltiprovider', 'disabled = ? AND sendgrades = ?', array(0, 1), '', 'id, lastsync, contextid, courseid')) {
        foreach ($tools as $tool) {
            if ($tool->lastsync + $synctime < $timenow) {
                mtrace(" Starting sync tool for grades id $tool->id course id $tool->courseid");
                if ($tool->requirecompletion) {
                    mtrace("  Grades require activity or course completion");
                }
                $user_count = 0;
                $send_count = 0;
                $error_count = 0;

                $completion = new completion_info(get_course($tool->courseid));

                $query = "SELECT id, userid, lastgrade, serviceurl, sourceid, consumerkey, consumersecret FROM {local_ltiprovider_user} WHERE toolid=? AND lastsync<? AND serviceurl!='' AND serviceurl IS NOT NULL AND sourceid!='' AND sourceid IS NOT NULL";
                if ($users = $DB->get_records_sql($query, array($tool->id, $tool->lastsync))) {
                    foreach ($users as $user) {

                        $data = array(
                            'tool' => $tool,
                            'user' => $user,
                        );
                        local_ltiprovider_call_hook('grades', (object) $data);

                        $user_count = $user_count + 1;

                        $grade = false;
                        if ($context = $DB->get_record('context', array('id' => $tool->contextid))) {
                            if ($context->contextlevel == CONTEXT_COURSE) {

                                if ($tool->requirecompletion and !$completion->is_course_complete($user->userid)) {
                                    mtrace("   Skipping user $user->userid since he didn't complete the course");
                                    continue;
                                }

                                if ($tool->sendcompletion) {
                                    $grade = $completion->is_course_complete($user->userid) ? 1 : 0;
                                    $grademax = 1;
                                } else if ($grade = grade_get_course_grade($user->userid, $tool->courseid)) {
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

                                if ($tool->sendcompletion) {
                                    $data = $completion->get_data($cm, false, $user->userid);
                                    if ($data->completionstate == COMPLETION_COMPLETE_PASS ||
                                            $data->completionstate == COMPLETION_COMPLETE ||
                                            $data->completionstate == COMPLETION_COMPLETE_FAIL) {
                                        $grade = 1;
                                    } else {
                                        $grade = 0;
                                    }
                                    $grademax = 1;
                                } else {
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
                                mtrace("   Skipping, last grade send is equal to current grade");
                                continue;
                            }

                            // We sync with the external system only when the new grade differs with the previous one
                            // TODO - Global setting for check this
                            if ($grade >= 0 and $grade <= $grademax) {
                                $float_grade = $grade / $grademax;
                                $body = local_ltiprovider_create_service_body($user->sourceid, $float_grade);

                                try {
                                    $response = ltiprovider\sendOAuthBodyPOST('POST', $user->serviceurl, $user->consumerkey, $user->consumersecret, 'application/xml', $body);
                                } catch (Exception $e) {
                                    mtrace(" ".$e->getMessage());
                                    $error_count = $error_count + 1;
                                    continue;
                                }

                                // TODO - Check for errors in $retval in a correct way (parsing xml)
                                if (strpos(strtolower($response), 'success') !== false) {

                                    $DB->set_field('local_ltiprovider_user', 'lastsync', $timenow, array('id' => $user->id));
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
                $DB->set_field('local_ltiprovider', 'lastsync', $timenow, array('id' => $tool->id));
            }
        }
    }

    $timenow = time();
    // Automatic course restaurations.
    if ($croncourses = get_config('local_ltiprovider', 'croncourses')) {
        $croncourses = unserialize($croncourses);
        if (is_array($croncourses)) {
            mtrace('Starting restauration of pending courses');

            foreach ($croncourses as $key => $course) {
                mtrace('Starting restoration of ' . $key);

                // We limit the backups to 1 hour, then retry.
                if ($course->restorestart and ($timenow < $course->restorestart + 3600)) {
                    mtrace('Skipping restoration in process for: ' . $key);
                    continue;
                }

                $course->restorestart = time();
                $croncourses[$key] = $course;
                $croncoursessafe = serialize($croncourses);
                set_config('croncourses', $croncoursessafe, 'local_ltiprovider');

                if ($destinationcourse = $DB->get_record('course', array('id' => $course->destinationid))) {
                    // Duplicate course + users.
                    local_ltiprovider_duplicate_course($course->id, $destinationcourse, 1,
                                                        $options = array(array('name'   => 'users',
                                                                                'value' => 1)),
                                                        $course->userrestoringid, $course->context);
                    mtrace('Restoration for ' .$key. ' finished');
                } else {
                    mtrace('Restoration for ' .$key. ' finished (destination course not exists)');
                }

                unset($croncourses[$key]);
                $croncoursessafe = serialize($croncourses);
                set_config('croncourses', $croncoursessafe, 'local_ltiprovider');
            }
        }
    }

    // Membership service.
    $timenow = time();
    $userphotos = array();

    if ($tools = $DB->get_records('local_ltiprovider', array('disabled' => 0, 'syncmembers' => 1))) {
        mtrace('Starting sync of member using the memberships service');
        $consumers = array();

        foreach ($tools as $tool) {
            $lastsync = get_config('local_ltiprovider', 'membershipslastsync-' . $tool->id);
            if (!$lastsync) {
                $lastsync = 0;
            }
            if ($lastsync + $tool->syncperiod < $timenow) {
                $ret = local_ltiprovider_membership_service($tool, $timenow, $userphotos, $consumers);
                $userphotos = $ret['userphotos'];
                $consumers = $ret['consumers'];
            } else {
                $last = format_time((time() - $lastsync));
                mtrace("Tool $tool->id synchronized $last ago");
            }
            mtrace('Finished sync of member using the memberships service');
        }
    }

    local_ltiprovider_membership_service_update_userphotos($userphotos);

}

/**
 * Call a hook present in a subplugin
 *
 * @param  string $hookname The hookname (function without franken style prefix)
 * @param  object $data     Object containing data to be used by the hook function
 * @return bool             Allways false
 */
function local_ltiprovider_call_hook($hookname, $data) {
    $plugins = get_plugin_list_with_function('ltiproviderextension', $hookname);
    if (!empty($plugins)) {
        foreach ($plugins as $plugin) {
            call_user_func($plugin, $data);
        }
    }
    return false;
}
