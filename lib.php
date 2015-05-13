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
 * Function for backwards compatibility (<2.4)
 * For 2.4 and onwards the correct way for calling this hook is local_ltiprovider_extends_navigation
 *
 * @param navigation_node $nav Current navigation object
 */
function ltiprovider_extends_navigation ($nav) {
    local_ltiprovider_extends_navigation($nav);
}

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

    // Check capabilities for tool providers
    // Only for Moodle < 2.3 versions
    if ($CFG->version < 2012062500 and $PAGE->course->id and $PAGE->course->id != SITEID
        and has_capability('local/ltiprovider:view', $PAGE->context)) {
        $ltiurl = new moodle_url('/local/ltiprovider/index.php', array('courseid' => $PAGE->course->id));
        $coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE);
        $coursenode->add(get_string('pluginname', 'local_ltiprovider'), $ltiurl, $nav::TYPE_CONTAINER, null, 'ltiprovider'.$PAGE->course->id);
    }

    if (isset($USER) and isset($USER->auth) and strpos($USER->username, 'ltiprovider') === 0) {
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

            // Special case, user policy, we don't have to do nothing to avoid infinites loops.
            if (strpos($ME, 'user/policy.php')) {
                return;
            }

            if ($urltogo) {
                redirect($urltogo);
            }
        }

        // Delete all the navigation nodes except the course one
        if ($coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE)) {
            foreach (array('myprofile', 'users', 'site', 'home', 'myhome', 'mycourses', 'courses', '1') as $nodekey) {
                if ($node = $nav->get($nodekey)) {
                    $node->remove();
                }
            }
            $nav->children->add($coursenode);
        }

        // Custom CSS
        if (isset($SESSION->ltiprovider) and !$PAGE->requires->is_head_done()) {
            $PAGE->requires->css(new moodle_url('/local/ltiprovider/styles.php', array('id' => $SESSION->ltiprovider->id)));
        } else {
            $url = new moodle_url('/local/ltiprovider/styles.js.php',
                                    array('id' => $SESSION->ltiprovider->id, 'rand' => rand(0, 1000)));
            $PAGE->requires->js($url);
        }
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

    $tool->sendgrades = (isset($tool->sendgrades)) ? 1 : 0;
    $tool->forcenavigation = (isset($tool->forcenavigation)) ? 1 : 0;
    $tool->hidepageheader = (isset($tool->hidepageheader)) ? 1 : 0;
    $tool->hidepagefooter = (isset($tool->hidepagefooter)) ? 1 : 0;
    $tool->hideleftblocks = (isset($tool->hideleftblocks)) ? 1 : 0;
    $tool->hiderightblocks = (isset($tool->hiderightblocks)) ? 1 : 0;
    $tool->syncmembers = (isset($tool->syncmembers)) ? 1 : 0;

    $tool->id = $DB->insert_record('local_ltiprovider', $tool);

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

    $tool->sendgrades = (isset($tool->sendgrades)) ? 1 : 0;
    $tool->forcenavigation = (isset($tool->forcenavigation)) ? 1 : 0;
    $tool->hidepageheader = (isset($tool->hidepageheader)) ? 1 : 0;
    $tool->hidepagefooter = (isset($tool->hidepagefooter)) ? 1 : 0;
    $tool->hideleftblocks = (isset($tool->hideleftblocks)) ? 1 : 0;
    $tool->hiderightblocks = (isset($tool->hiderightblocks)) ? 1 : 0;
    $tool->syncmembers = (isset($tool->syncmembers)) ? 1 : 0;

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
 * Checks if a course linked to a tool is missing, is so, delete the lti entries
 * @param  stdclass $tool Tool record
 * @return bool      True if the course was missing
 */
function local_ltiprovider_check_missing_course($tool) {
    global $DB;

    if (! $course = $DB->get_record('course', array('id' => $tool->courseid))) {
        $DB->delete_records('local_ltiprovider', array('courseid' => $tool->courseid));
        $DB->delete_records('local_ltiprovider_user', array('toolid' => $tool->id));
        mtrace("Tool: $tool->id deleted (courseid: $tool->courseid missing)");
        return true;
    }
    return false;
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
    if ($tools = $DB->get_records('local_ltiprovider')) {
        foreach ($tools as $tool) {
            local_ltiprovider_check_missing_course($tool);
        }
    }

    // Grades service.
    if ($tools = $DB->get_records_select('local_ltiprovider', 'disabled = ? AND sendgrades = ?', array(0, 1))) {
        foreach ($tools as $tool) {
            if ($tool->lastsync + $synctime < $timenow) {
                mtrace(" Starting sync tool id $tool->id course id $tool->courseid");
                $user_count = 0;
                $send_count = 0;
                $error_count = 0;
                if ($users = $DB->get_records('local_ltiprovider_user', array('toolid' => $tool->id))) {
                    foreach ($users as $user) {
                        $user_count = $user_count + 1;
                        // This can happen if the sync process has an unexpected error
                        if ( strlen($user->serviceurl) < 1 ) continue;
                        if ( strlen($user->sourceid) < 1 ) continue;
                        if ($user->lastsync > $tool->lastsync) {
                            mtrace("Skipping user {$user->id}");
                            continue;
                        } else {
                            list($result, $message) = local_ltiprovider_send_grade($tool, $user);
                            if ($result === true) {
                                $send_count++;
                            } else {
                                $error_count++;
                            }
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
                mtrace('Starting sync of tool: ' . $tool->id);
                // We check for all the users, notice that users can access the same tool from different consumers.
                if ($users = $DB->get_records('local_ltiprovider_user', array('toolid' => $tool->id), 'lastaccess DESC')) {
                    $response = "";

                    foreach ($users as $user) {
                        if (!$user->membershipsurl or !$user->membershipsid) {
                            continue;
                        }

                        $consumer = md5($user->membershipsurl . ':' . $user->membershipsid . ':' . $user->consumerkey . ':' . $user->consumersecret);
                        if (in_array($consumer, $consumers)) {
                            // We had syncrhonized with this consumer yet.
                            continue;
                        }
                        $consumers[] = $consumer;

                        $params = array(
                            'lti_message_type' => 'basic-lis-readmembershipsforcontext',
                            'id' => $user->membershipsid,
                            'lti_version' => 'LTI-1p0'
                        );

                        mtrace('Calling memberships url: ' . $user->membershipsurl . ' with body: ' . json_encode($params));

                        try {
                            $response = ltiprovider\sendOAuthParamsPOST('POST', $user->membershipsurl, $user->consumerkey, $user->consumersecret,
                                                            'application/x-www-form-urlencoded', $params);
                        } catch (Exception $e) {
                            mtrace("Exception: " . $e->getMessage());
                            $response = false;
                        }

                        if ($response) {
                            $data = new SimpleXMLElement($response);
                            if(!empty($data->statusinfo)) {
                                if(strpos(strtolower($data->statusinfo->codemajor), 'success') !== false) {
                                    $members = $data->memberships->member;
                                    mtrace(count($members) . ' members received');
                                    $currentusers = array();
                                    foreach ($members as $member) {
                                        $username = local_ltiprovider_create_username($user->consumerkey, $member->user_id);

                                        $userobj = $DB->get_record('user', array('username' => $username));
                                        if (!$userobj) {
                                            // Old format.
                                            $oldusername = 'ltiprovider' . md5($user->consumerkey . ':' . $member->user_id);
                                            $userobj = $DB->get_record('user', array('username' => $oldusername));
                                            if ($userobj) {
                                                $DB->set_field('user', 'username', $username, array('id' => $userobj->id));
                                            }
                                            $userobj = $DB->get_record('user', array('username' => $username));
                                        }

                                        if ($userobj) {
                                            $currentusers[] = $userobj->id;
                                            $userobj->firstname = clean_param($member->person_name_given, PARAM_TEXT);
                                            $userobj->lastname = clean_param($member->person_name_family, PARAM_TEXT);
                                            $userobj->email = clean_param($member->person_contact_email_primary, PARAM_EMAIL);
                                            $userobj->timemodified = time();

                                            $DB->update_record('user', $userobj);
                                            $userphotos[$userobj->id] = $member->user_image;
                                            error_log(print_r($userobj, true));
                                            //events_trigger('user_updated', $userobj);
                                            // updated to cope with Event2 system.
                                            $evt = \core\event\user_updated::create($userobj);
                                            $evt->trigger();

                                        } else {
                                            // New members.
                                            if ($tool->syncmode == 1 or $tool->syncmode == 2) {
                                                // We have to enrol new members so we have to create it.
                                                $userobj = new stdClass();
                                                // clean_param , email username text
                                                $auth = get_config('local_ltiprovider', 'defaultauthmethod');
                                                if ($auth) {
                                                    $userobj->auth = $auth;
                                                } else {
                                                    $userobj->auth = 'nologin';
                                                }

                                                $username = local_ltiprovider_create_username($user->consumerkey, $member->user_id);
                                                $userobj->username = $username;
                                                $userobj->password = md5(uniqid(rand(), 1));
                                                $userobj->firstname = clean_param($member->person_name_given, PARAM_TEXT);
                                                $userobj->lastname = clean_param($member->person_name_family, PARAM_TEXT);
                                                $userobj->email = clean_param($member->person_contact_email_primary, PARAM_EMAIL);
                                                $userobj->city = $tool->city;
                                                $userobj->country = $tool->country;
                                                $userobj->institution = $tool->institution;
                                                $userobj->timezone = $tool->timezone;
                                                $userobj->maildisplay = $tool->maildisplay;
                                                $userobj->mnethostid = $CFG->mnet_localhost_id;
                                                $userobj->confirmed = 1;
                                                $userobj->lang = $tool->lang;
                                                $userobj->timecreated = time();
                                                if (! $userobj->lang) {
                                                    // TODO: This should be changed for detect the course lang
                                                    $userobj->lang = current_language();
                                                }

                                                $userobj->id = $DB->insert_record('user', $userobj);
                                                // Reload full user
                                                $userobj = $DB->get_record('user', array('id' => $userobj->id));

                                                $userphotos[$userobj->id] = $member->user_image;
                                                events_trigger('user_created', $userobj);
                                                $currentusers[] = $userobj->id;
                                            }
                                        }
                                        // 1 -> Enrol and unenrol, 2 -> enrol
                                        if ($tool->syncmode == 1 or $tool->syncmode == 2) {
                                            // Enroll the user in the course. We don't know if it was previously unenrolled.
                                            $roles = explode(',', strtolower($member->roles));
                                            local_ltiprovider_enrol_user($tool, $userobj, $roles, true);
                                        }
                                    }
                                    // Now we check if we have to unenrol users for keep both systems sync.
                                    if ($tool->syncmode == 1 or $tool->syncmode == 3) {
                                        // Unenrol users also.
                                        $context = context_course::instance($tool->courseid);
                                        $eusers = get_enrolled_users($context);
                                        foreach ($eusers as $euser) {
                                            if (!in_array($euser->id, $currentusers)) {
                                                local_ltiprovider_unenrol_user($tool, $euser);
                                            }
                                        }
                                    }
                                } else {
                                    mtrace('Error recived from the remote system: ' . $data->statusinfo->codemajor . ' ' . $data->statusinfo->severity . ' ' . $data->statusinfo->codeminor);
                                }
                            } else {
                                mtrace('Error parsing the XML received' . substr($response, 0, 125) . '... (Displaying only 125 chars)');
                            }
                        } else {
                            mtrace('No response received from ' . $user->membershipsurl);
                        }
                    }
                }
                set_config('membershipslastsync-' . $tool->id, $timenow, 'local_ltiprovider');
            } else {
                $last = format_time((time() - $lastsync));
                mtrace("Tool $tool->id synchronized $last ago");
            }
            mtrace('Finished sync of member using the memberships service');
        }
    }

    // Sync of user photos.
    mtrace("Sync user profile images");
    $counter = 0;
    if ($userphotos) {
        foreach ($userphotos as $userid => $url) {
            if ($url) {
                $result = local_ltiprovider_update_user_profile_image($userid, $url);
                if ($result === true) {
                    $counter++;
                    mtrace("Profile image succesfully downloaded and created from $url");
                } else {
                    mtrace($result);
                }
            }
        }
    }
    mtrace("$counter profile images updated");
}

function local_ltiprovider_send_grade ($tool, $user) {
    // I've set this up this way to potentially use the Event API to trigger a grade send automatically.
    // TODO:  Probably don't need all of these...
    global $DB, $CFG;
    require_once($CFG->dirroot."/local/ltiprovider/locallib.php");
    require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuth.php");
    require_once($CFG->dirroot."/local/ltiprovider/ims-blti/OAuthBody.php");
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/querylib.php');

    $grade = false;
    if ($context = $DB->get_record('context', array('id' => $tool->contextid))) {
        if ($context->contextlevel == CONTEXT_COURSE) {

            if ($grade = grade_get_course_grade($user->userid, $tool->courseid)) {
                $grademax = floatval($grade->item->grademax);
                $grade = $grade->grade;
            }
        } else if ($context->contextlevel == CONTEXT_MODULE) {

            $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
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

        // TODO:  This isn't actually a success or a failure.
        if ( $grade === false || $grade === NULL || strlen($grade) < 1) {
            mtrace(" No grade to return for userid $user->userid");
            return array(false, "No grade to return for userid $user->userid");
        }

        // No need to be dividing by zero
        if ( $grademax == 0.0 ) $grademax = 100.0;

        // TODO: Make lastgrade should be float or string - but it is integer so we truncate
        // TODO: Then remove those intval() calls

        // Don't double send
        // TODO:  This isn't actually a success or a failure.
        if ( intval($grade) == $user->lastgrade ) {
            mtrace(" Current grade matches last grade sent.");
            return array(true, "Current grade matches last grade sent.");
        }

        // We sync with the external system only when the new grade differs with the previous one
        // TODO - Global setting for check this
        if ($grade > 0 and $grade <= $grademax) {
            $float_grade = $grade / $grademax;
            $body = local_ltiprovider_create_service_body($user->sourceid, $float_grade);

            try {
                $response = ltiprovider\sendOAuthBodyPOST('POST', $user->serviceurl, $user->consumerkey, $user->consumersecret, 'application/xml', $body);
            } catch (Exception $e) {
                mtrace(" ".$e->getMessage());
                return array(false, "Problem posting grade:  " . $e->getMessage());
            }

            //// FIXED - Check for errors in $retval in a correct way (parsing xml)
            $xml = simplexml_load_string(str_replace('xmlns', 'ns', $response));
            $imsx_codeMajor = $xml->xpath(
              '/imsx_POXEnvelopeResponse/imsx_POXHeader' .
              '/imsx_POXResponseHeaderInfo/imsx_statusInfo/imsx_codeMajor'
            );
            $imsx_description = $xml->xpath(
              '/imsx_POXEnvelopeResponse/imsx_POXHeader' .
              '/imsx_POXResponseHeaderInfo/imsx_statusInfo/imsx_description'
            );
            mtrace(" Remote system description was " . $imsx_description[0]);
            if(strtolower($imsx_codeMajor[0]) === 'success') {
                $DB->set_field('local_ltiprovider_user', 'lastsync', $timenow, array('id' => $user->id));
                $DB->set_field('local_ltiprovider_user', 'lastgrade', intval($grade), array('id' => $user->id));
                mtrace(" User grade sent to remote system. userid: $user->userid grade: $float_grade");
                return array(true, "Successfully set grade");
            } else {
                mtrace(" User grade send failed: ".$response.$user->serviceurl);
                return array(false, "User grade send failed: ".$response.$user->serviceurl);
            }
        } else {
            mtrace(" User grade out of range: grade = ".$grade);
            return array(false, "$grade is out of range");
        }
    } else {
        mtrace(" Invalid context: contextid = ".$tool->contextid);
        return array(false, "Invalid context: contextid = ".$tool->contextid);
    }
}
