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
 * Custom styles for a tool.
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

$toolid = required_param('id', PARAM_INT);

$url = $CFG->wwwroot . "/local/ltiprovider/styles.php?id=$toolid";
?>

function local_ltiprovider_loadjscssfile(){
    var url = "<?php echo $url; ?>";
    var fileref=document.createElement("link");
    fileref.setAttribute("rel", "stylesheet");
    fileref.setAttribute("type", "text/css");
    fileref.setAttribute("href", url);
    var head = document.getElementsByTagName("head");
    if (head) {
        head[0].appendChild(fileref)
    }
}

// Waiting DOM ready (hide effect).
YUI().use('node', function(Y) {
      Y.on("domready", function(){
        local_ltiprovider_loadjscssfile();
      });
});

// Without waiting.
local_ltiprovider_loadjscssfile();