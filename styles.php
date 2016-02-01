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

if (! ($tool = $DB->get_record('local_ltiprovider', array('id'=>$toolid)))) {
    print_error('invalidtoolid', 'local_ltiprovider');
}

if ($tool->disabled) {
    print_error('tooldisabled', 'local_ltiprovider');
}

$lifetime = 60*60*24*3;

$css = '';

if ($tool->hidepageheader or $SESSION->ltiprovider->hidepageheader) {
    $css .= '
    #page-header{
     display: none;
    }

    header.navbar {
     display: none;
    }
    ';
}
if ($tool->hidepagefooter or $SESSION->ltiprovider->hidepagefooter) {
    $css .= '
    #page-footer{
     display: none;
    }
    ';
}
if ($tool->hideleftblocks or $SESSION->ltiprovider->hideleftblocks) {
    $css .= '
    #region-pre .block, #block-region-side-pre .block{
     display: none;
    }
    #mod_quiz_navblock {
     display: block !important;
    }
    .empty-region-side-post.used-region-side-pre #region-main.span8 {
     width: inherit;
    }
    ';
}
if ($tool->hiderightblocks or $SESSION->ltiprovider->hiderightblocks) {
    $css .= '
    #region-post, #block-region-side-post {
     display: none;
    }
    ';
}

if ($tool->customcss or $SESSION->ltiprovider->customcss) {

    $css .= $tool->customcss;

    if ($SESSION->ltiprovider->customcss and $SESSION->ltiprovider->customcss != $tool->customcss) {
        $css .= $SESSION->ltiprovider->customcss;
    }
}

header('Content-Disposition: inline; filename="styles.php"');
header('Last-Modified: '. gmdate('D, d M Y H:i:s', time()) .' GMT');
header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .' GMT');
header('Pragma: ');
header('Accept-Ranges: none');
header('Content-Type: text/css; charset=utf-8');
header('Content-Length: '.strlen($css));

echo $css;
die;
