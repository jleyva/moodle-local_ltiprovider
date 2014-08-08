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
 * Library functions.
 *
 * @package    ltiproviderextension
 * @subpackage scormbridge
 * @copyright  2014 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;


/**
 * SCORM Bridge, we detect changes in quizzes for submitting back to the parent Window that will process SCORM API messages
 *
 * @param  object $nav Global navigation object
 */
function ltiproviderextension_scormbridge_navigation($nav) {
    global $DB, $SESSION, $USER, $PAGE;

    // First we need to check if we are in a LTI session and also if the module is a quiz.
    if (isset($SESSION->ltiprovider)) {
        $context = $SESSION->ltiprovider->context;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);

            if ($cm->modname == "quiz") {
                $url = new moodle_url('/local/ltiprovider/extension/scormbridge/tracking.js.php',
                                        array('quizid' => $cm->instance, 'rand' => rand(0, 1000)));
                $PAGE->requires->js($url);

            }
        }
    }
}