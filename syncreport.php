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
require_once($CFG->dirroot.'/local/ltiprovider/classes/table_syncreport.php');

$id = required_param('id', PARAM_INT);
$search_firstname = optional_param('search_firstname', '', PARAM_TEXT);
$search_lastname = optional_param('search_lastname', '', PARAM_TEXT);
$sifirst  = optional_param('sifirst', null, PARAM_NOTAGS);
$silast   = optional_param('silast', null, PARAM_NOTAGS);


$tool = $DB->get_record('local_ltiprovider', array('id' => $id), '*', MUST_EXIST);
$courseid = $tool->courseid;
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$params_url = array('id' => $id);
if (!empty($search_firstname)) {
    $params_url['search_firstname'] = $search_firstname;
}
if (!empty($search_surname)) {
    $params_url['search_surname'] = $search_surname;
}
if (!empty($sifirst)) {
    $params_url['sifirst'] = $sifirst;
}
if (!empty($silast)) {
    $params_url['silast'] = $silast;
}

$PAGE->set_url('/local/ltiprovider/syncreport.php', $params_url );

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
$PAGE->requires->jquery();
$PAGE->requires->js('/local/ltiprovider/js/syncreport.js');
$PAGE->requires->strings_for_js(array('youhavetoselectauser'), 'local_ltiprovider');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradessent', 'local_ltiprovider'));

if ($tool->requirecompletion) {
    echo $OUTPUT->notification(get_string('notifycompletion', 'local_ltiprovider'), 'notifymessage');
}

$filterparams = new stdClass();
$filterparams->firstname = $search_firstname;
$filterparams->sifirst = $sifirst;
$filterparams->lastname = $search_lastname;
$filterparams->silast = $silast;
$table = new local_ltiprovider_table_syncreport('local_ltiprovider_syncreport', $tool, $filterparams);
echo $table->syncreport_search_form($id, $search_firstname, $search_lastname, $sifirst, $silast);

$table->out(50, true);

//Button to send grade to selected users
$forcesendurl_participant = new \moodle_url('test/forcesendgrades.php', array('toolid' => $tool->id,
    'printresponse' => 1, 'selected_users' => 1, 'user_force_grade' => ''));
$action = new component_action('click', 'ltiprovider_send_syncreport');
echo $OUTPUT->single_button($forcesendurl_participant, get_string('forcesendgradesselectedusers', 'local_ltiprovider'),
    'post', array('actions' => array($action), 'formid' => 'ltiprovider_send_syncreport_form'));

//Button to send grade to all users
$forcesendurl = new \moodle_url('test/forcesendgrades.php', array('toolid' => $tool->id, 'printresponse' => 1));
echo $OUTPUT->single_button($forcesendurl, get_string('forcesendgradesallusers', 'local_ltiprovider'));

if ($tool->requirecompletion) {
    $forcesendurl = new \moodle_url('test/forcesendgrades.php', array('toolid' => $tool->id, 'printresponse' => 1, 'omitcompletion' => 1));
    echo $OUTPUT->single_button($forcesendurl, get_string('forcesendgradesallusersomittingcompletion', 'local_ltiprovider'));
}

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
