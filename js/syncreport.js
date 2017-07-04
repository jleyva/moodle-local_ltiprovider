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
 * Javascript helper function for Sync Report plugin
 *
 * @package   local-ltiprovider
 * @author Antoni Bertran antoni@tresipunt.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(['jquery'], function($) {
    var lastChecked = null;

    $(document).ready(function() {
        var $chkboxes = $('.usercheckbox');
        $chkboxes.click(function(e) {
            if(!lastChecked) {
                lastChecked = this;
                return;
            }

            if(e.shiftKey) {
                var start = $chkboxes.index(this);
                var end = $chkboxes.index(lastChecked);

                $chkboxes.slice(Math.min(start,end), Math.max(start,end)+ 1).prop('checked', lastChecked.checked);

            }

            lastChecked = this;
        });
    });
});

function ltiprovider_send_syncreport() {
    event.preventDefault();
    var checkboxes = document.querySelectorAll('input[name="user_force_grade_checkbox"]:checked'), checks = '';
    Array.prototype.forEach.call(checkboxes, function(el) {
        checks += (checks.length>0?',':'') + el.value;
    });
    if (checks.length>0) {
        var x = document.getElementsByName("user_force_grade");
        var i;
        //should be only one
        for (i = 0; i < x.length; i++) {
            x[i].value = checks;
        }
        document.getElementById('ltiprovider_send_syncreport_form').submit();
    } else {
        alert(M.util.get_string('youhavetoselectauser', 'local_ltiprovider'));
    }
    return false;

}
