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
 * Version details.
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Change the navigation block and bar only for external users
 * 
 * @global moodle_user $USER
 * @global moodle_database $DB
 * @param navigation_node $nav Current navigation object
 */
function ltiprovider_extends_navigation ($nav) {
	global $USER, $PAGE;
	
	// Check capabilities for tool providers
	if ($PAGE->course->id && $PAGE->course->id != SITEID && has_capability('local/ltiprovider:providetool',$PAGE->context)) {
		$coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE);
		$coursenode->add(get_string('pluginname', 'local_ltiprovider'), new moodle_url('/local/ltiprovider/index.php?courseid='.$PAGE->course->id), $nav::TYPE_SETTING, null, 'ltiprovider'.$PAGE->course->id);
	}
	
	if ($USER->auth == 'nologin' && strpos($USER->username, 'ltiprovider') === 0) {
		$coursenode = $nav->find($PAGE->course->id, $nav::TYPE_COURSE);
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