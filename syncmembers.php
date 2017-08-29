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
 * Force to sync members of current tool
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2017 Antoni Bertran <antoni@tresipunt.com> Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/lib.php');

$id = required_param('id', PARAM_INT);

$tool = $DB->get_record('local_ltiprovider', array('id' => $id), '*', MUST_EXIST);
$courseid = $tool->courseid;
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$params_url = array('id' => $id);

$PAGE->set_url('/local/ltiprovider/syncmembers.php', $params_url );

$context = context_course::instance($course->id);

require_login($course);
require_capability('local/ltiprovider:manage', $context);

$returnurl = new moodle_url('/local/ltiprovider/index.php', array('courseid' => $courseid));

$strheading = get_string('forcesyncmembers', 'local_ltiprovider');
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('pluginname', 'local_ltiprovider'), new moodle_url('/local/ltiprovider/index.php', array('courseid'=>$course->id)));
$PAGE->navbar->add($strheading);
$PAGE->set_title($strheading);
$PAGE->set_heading($course->fullname . ': '.$strheading);

echo $OUTPUT->header();

$userphotos = array();
$consumers = array();
$timenow = time();
$ret = local_ltiprovider_membership_service($tool, $timenow, $userphotos, $consumers);
echo '<p>Response => <pre>'.htmlentities($ret['response']).'</pre></p>';

echo $OUTPUT->footer();
