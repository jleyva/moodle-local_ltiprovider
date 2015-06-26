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
 * Launch destination url. Main entry point for the external system.
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

require_once('../lib.php');

if ($tools = $DB->get_records('local_ltiprovider', array('disabled' => 0))) {
    foreach ($tools as $tool) {
        set_config('membershipslastsync-' . $tool->id, 0, 'local_ltiprovider');
        $tool->lastsync = 0;
        $DB->update_record('local_ltiprovider', $tool);
    }
}

if ($users = $DB->get_records('local_ltiprovider_user')) {
    foreach ($users as $user) {
        $user->lastsync = 0;
        $DB->update_record('local_ltiprovider_user', $user);
    }
}

@header('Content-Type: text/plain; charset=utf-8');

$start = time();
echo "Starting LTI provider cron";
local_ltiprovider_cron();
$end = time();
echo "LTI provider cron finished, duration: " . ($end - $start) . " secs";
