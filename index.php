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
 * List the tool provided in a course
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ltiprovider/lib.php');

$courseid = required_param('courseid', PARAM_INT);

if (! ($course = $DB->get_record('course', array('id'=>$courseid)))) {
    print_error('invalidcourseid', 'error');
}

$PAGE->set_url('/local/ltiprovider/index.php', array('courseid' => $courseid));

context_helper::preload_course($course->id);
if (!$context = context_course::instance($course->id)) {
    print_error('nocontext');
}

require_login($course);
require_capability('local/ltiprovider:view', $context);

// $PAGE->navbar->add(get_string('toolsprovided', 'local_ltiprovider'));
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('toolsprovided', 'local_ltiprovider'));

$tools = $DB->get_records('local_ltiprovider', array('courseid' => $course->id));

$data = array();
foreach ($tools as $tool) {
    if (!$toolcontext = context::instance_by_id($tool->contextid, IGNORE_MISSING)) {
        local_ltiprovider_delete_tool($tool);
        continue;
    }
    $line = array();
    $line[] = $toolcontext->get_context_name();
    $line[] = $tool->secret;
    $line[] = new moodle_url('/local/ltiprovider/tool.php', array('id' => $tool->id));

    if (has_capability('local/ltiprovider:manage', $context)) {
        $buttons = array();

        $buttons[] = html_writer::link(new moodle_url('/local/ltiprovider/edit.php', array('id'=>$tool->id, 'delete'=>1, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/delete'), 'alt'=>get_string('delete'), 'class'=>'iconsmall')));
        $buttons[] =  html_writer::link(new moodle_url('/local/ltiprovider/edit.php', array('id'=>$tool->id, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/edit'), 'alt'=>get_string('edit'), 'class'=>'iconsmall')));

        if ($tool->disabled) {
            $buttons[] = html_writer::link(new moodle_url('/local/ltiprovider/edit.php', array('id'=>$tool->id, 'show'=>1, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/show'), 'alt'=>get_string('show'), 'class'=>'iconsmall')));
        } else {
            $buttons[] = html_writer::link(new moodle_url('/local/ltiprovider/edit.php', array('id'=>$tool->id, 'hide'=>1, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/hide'), 'alt'=>get_string('hide'), 'class'=>'iconsmall')));
        }

        $line[] = implode(' ', $buttons);
    } else {
        $line[] = '';
    }
    $data[] = $line;
}

// Moodle 2.2 and onwards
if (isset($CFG->allowframembedding) and !$CFG->allowframembedding) {
    echo $OUTPUT->box(get_string('allowframembedding', 'local_ltiprovider'));
}

$table = new html_table();
$table->head  = array(
    get_string('name', 'local_ltiprovider'),
    get_string('secret', 'local_ltiprovider'),
    get_string('url', 'local_ltiprovider'),
    get_string('edit'));
$table->size  = array('20%', '20%', '50%', '10%');
$table->align = array('left', 'left', 'left', 'center');
$table->width = '99%';
$table->data  = $data;
echo html_writer::table($table);

echo $OUTPUT->single_button(new moodle_url('/local/ltiprovider/edit.php', array('id' => -1, 'courseid' => $course->id)), get_string('add'));

echo $OUTPUT->footer();
