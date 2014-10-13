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
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ltiprovider/lib.php');
require_once($CFG->dirroot.'/local/ltiprovider/edit_form.php');

$id = optional_param('id', -1, PARAM_INT);    // user id; -1 if creating new tool
$courseid = optional_param('courseid', 0, PARAM_INT);   // course id (defaults to Site)
$delete    = optional_param('delete', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);

if ($id > 0) {
    if (! ($tool = $DB->get_record('local_ltiprovider', array('id'=>$id)))) {
        print_error('invalidtoolid', 'local_ltiprovider');
    }
    $courseid = $tool->courseid;
} else {
    $tool = new stdClass();
    $tool->id = -1;
    $tool->courseid = $courseid;
}

if (! ($course = $DB->get_record('course', array('id'=>$courseid)))) {
    print_error('invalidcourseid', 'error');
}

$PAGE->set_url('/local/ltiprovider/edit.php', array('id' => $id, 'courseid' => $courseid));

context_helper::preload_course($course->id);
if (!$context = context_course::instance($course->id)) {
    print_error('nocontext');
}

require_login($course);
require_capability('local/ltiprovider:manage', $context);

$returnurl = new moodle_url('/local/ltiprovider/index.php', array('courseid' => $courseid));

$strheading = get_string('providetool', 'local_ltiprovider');
$PAGE->set_context($context);

if ($delete and $tool->id) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
        local_ltiprovider_delete_tool($tool);
        redirect($returnurl);
    }
    $strheading = get_string('deletetool', 'local_ltiprovider');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($COURSE->fullname);

    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/local/ltiprovider/edit.php', array('id'=>$tool->id, 'delete'=>1, 'confirm'=>1, 'sesskey'=>sesskey()));
    $message = get_string('delconfirm', 'local_ltiprovider');
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

if ((!empty($hide) or !empty($show)) and $tool->id and confirm_sesskey()) {
    if (!empty($hide)) {
        $disabled = 1;
    } else {
        $disabled = 0;
    }
    $DB->set_field('local_ltiprovider', 'disabled', $disabled, array('id' => $tool->id));
    redirect($returnurl);
}

$PAGE->navbar->add(get_string('pluginname', 'local_ltiprovider'), new moodle_url('/local/ltiprovider/index.php', array('courseid'=>$course->id)));
$PAGE->navbar->add($strheading);
$PAGE->set_title($strheading);
$PAGE->set_heading($course->fullname . ': '.$strheading);

$editform = new edit_form(null, compact('context', 'courseid'));

$userprofileupdate = get_config('local_ltiprovider', 'userprofileupdate');
if ($userprofileupdate != -1) {
    $tool->userprofileupdate = $userprofileupdate;
}

$editform->set_data($tool);

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {

    if ($data->id > 0) {
        // Update
        local_ltiprovider_update_tool($data);
    } else {
        // Create new
        local_ltiprovider_add_tool($data);
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
$editform->display();
echo $OUTPUT->footer();