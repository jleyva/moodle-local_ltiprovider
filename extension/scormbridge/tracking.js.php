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
 * Retrieve the quiz tracking: Attempts and grades
 * Information can be send using Push (postMessage) or retreived by pulling (jsonp)
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '../../../../../config.php');

$quizid = optional_param('quizid', 0, PARAM_INT);
$toolid = optional_param('toolid', 0, PARAM_INT);
$jsonp  = optional_param('jsonp', "", PARAM_RAW);   // Whether to use a function padding for the response.

if (!$quizid and $toolid) {
    if (isset($SESSION->ltiprovider)) {
        $context = $SESSION->ltiprovider->context;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);

            if ($cm->modname == "quiz") {
                $quizid = $cm->instance;
            }
        }
    }
}

if (!$quizid) {
    die;
}

// Check that the user is logged and the LTI session launched.
if (isset($SESSION->ltiprovider) and isloggedin()) {
    $conditions = array("userid" => $USER->id, "quiz" => $quizid);
    $attempts = $DB->get_records('quiz_attempts', $conditions, "id ASC");
    $grades = $DB->get_records('quiz_grades', $conditions, "id ASC");
} else {
    $attempts = array();
    $grades = array();
}

$data = array(
    'grades'   => array_values($grades),
    'attempts' => array_values($attempts)
);

$data = json_encode($data);

// Using JSONP, we use a padding function that must be implemented in the consumer side.
if ($jsonp) {
?>

<?php echo $jsonp; ?>('<?php echo $data; ?>');

<?php
} else {
?>

parent.postMessage('<?php echo $data; ?>', "*");

<?php
}

