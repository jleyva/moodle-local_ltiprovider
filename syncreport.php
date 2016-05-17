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
 * Edit a tool provided in a course
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2016 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

$id = required_param('id', PARAM_INT);

$tool = $DB->get_record('local_ltiprovider', array('id' => $id), '*', MUST_EXIST);
$courseid = $tool->courseid;
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$PAGE->set_url('/local/ltiprovider/syncreport.php', array('id' => $id));

$context = context_course::instance($course->id);

require_login($course);
require_capability('local/ltiprovider:manage', $context);

$returnurl = new moodle_url('/local/ltiprovider/index.php', array('courseid' => $courseid));

$strheading = get_string('gradessyncreport', 'local_ltiprovider');
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('pluginname', 'local_ltiprovider'), new moodle_url('/local/ltiprovider/index.php', array('courseid'=>$course->id)));
$PAGE->navbar->add($strheading);
$PAGE->set_title($strheading);
$PAGE->set_heading($course->fullname . ': '.$strheading);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradessent', 'local_ltiprovider'));

if ($tool->requirecompletion) {
    echo $OUTPUT->notification(get_string('notifycompletion', 'local_ltiprovider'), 'notifymessage');
}

$table = new html_table();
$table->head = array(get_string('fullnameuser'), get_string('time'), get_string('grade'), '', get_string('gradessourceid', 'local_ltiprovider'), get_string('gradesserviceurl', 'local_ltiprovider'));
$table->attributes['class'] = 'admintable generaltable';
$table->data = array();

$users = $DB->get_records_sql("
    SELECT u.*, g.serviceurl, g.sourceid, g.lastgrade, g.lastsync
        FROM {user} u JOIN {local_ltiprovider_user} g
        ON u.id = g.userid
        WHERE g.lastsync > 0 AND g.toolid = :toolid ORDER BY g.lastsync DESC", array('toolid' => $tool->id));

foreach ($users as $user) {
    $forcesendurl = new \moodle_url('test/forcesendgrades.php', array('toolid' => $tool->id, 'userid' => $user->id, 'printresponse' => 1));
    $forcesendbutton = $OUTPUT->single_button($forcesendurl, get_string('forcesendgrades', 'local_ltiprovider'));
    $table->data[] = array(fullname($user), userdate($user->lastsync), $user->lastgrade, $forcesendbutton, $user->serviceurl, s($user->sourceid));
}


echo html_writer::table($table);

$forcesendurl = new \moodle_url('test/forcesendgrades.php', array('toolid' => $tool->id, 'printresponse' => 1));
echo $OUTPUT->single_button($forcesendurl, get_string('forcesendgradesallusers', 'local_ltiprovider'));

if ($tool->requirecompletion) {
    $forcesendurl = new \moodle_url('test/forcesendgrades.php', array('toolid' => $tool->id, 'printresponse' => 1, 'omitcompletion' => 1));
    echo $OUTPUT->single_button($forcesendurl, get_string('forcesendgradesallusersomittingcompletion', 'local_ltiprovider'));
}

echo $OUTPUT->footer();
