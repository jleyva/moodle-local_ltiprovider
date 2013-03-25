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

require_once('/var/www/moodle/config.php');
require_once($CFG->dirroot.'/local/ltiprovider/ims-blti/blti.php');
require_once($CFG->dirroot.'/local/ltiprovider/ims-blti/blti_util.php');
require_once($CFG->dirroot.'/local/ltiprovider/return_grade.php');

use moodle\local\ltiprovider as ltiprovider;

$parsed_url = parse_url($_POST['tool_url']);
parse_str($parsed_url['query']);
$tool = $DB->get_record_select('local_ltiprovider', 'disabled = ? AND sendgrades = ? AND id = ?', array(0, 1, $id));
// ensure the params are sane.
$context = new BLTI($tool->secret, false, false);
//mtrace($context);
if ($context) {
		$user = $DB->get_record_select('local_ltiprovider_user', 'sourceid = ?', array($_POST['lis_result_sourcedid']));
		local_ltiprovider_return_grade($tool, $user);
		echo "Yes!";
} else {
		//BLTI launch invalid.
		echo "No!";
}



