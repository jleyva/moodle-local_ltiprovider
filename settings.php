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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // needs this condition or there is error on login page

    $ADMIN->add('root', new admin_category('ltiprovider', get_string('pluginname', 'local_ltiprovider')));
    $ADMIN->add('ltiprovider', new admin_externalpage('ltiprovidersettings', get_string('settings'),
            $CFG->wwwroot.'/admin/settings.php?section=local_ltiprovider', 'local/ltiprovider:manage'));

    $settings = new admin_settingpage('local_ltiprovider', 'LTI Provider');
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext('local_ltiprovider/globalsharedsecret',
        get_string('globalsharedsecret', 'local_ltiprovider'), '', '', PARAM_RAW_TRIMMED));

    $options = array(-1 => get_string('delegate', 'local_ltiprovider'), 0 => get_string('never'), 1 => get_string('always'));
    $settings->add(new admin_setting_configselect('local_ltiprovider/userprofileupdate', get_string('userprofileupdate',
        'local_ltiprovider'), get_string('userprofileupdatehelp', 'local_ltiprovider'), 1, $options));

    $auths = get_plugin_list('auth');
    $authmethods = array();
    foreach ($auths as $auth => $unused) {
        if (is_enabled_auth($auth)) {
            $authmethods[$auth] = get_string('pluginname', "auth_{$auth}");
        }
    }
    $settings->add(new admin_setting_configselect('local_ltiprovider/defaultauthmethod', get_string('defaultauthmethod',
        'local_ltiprovider'), get_string('defaultauthmethodhelp', 'local_ltiprovider'), 'manual', $authmethods));

    $options = array('context_id', 'context_title' , 'context_label', 'consumer_key : context_id', 'consumer_key : context_title' , 'consumer_key : context_label');

    $settings->add(new admin_setting_configselect('local_ltiprovider/fullnameformat', get_string('fullnameformat',
        'local_ltiprovider'), get_string('genericformathelp', 'local_ltiprovider'), 1, $options));

    $settings->add(new admin_setting_configselect('local_ltiprovider/shortnameformat', get_string('shortnameformat',
        'local_ltiprovider'), get_string('genericformathelp', 'local_ltiprovider'), 2, $options));

    $settings->add(new admin_setting_configselect('local_ltiprovider/idnumberformat', get_string('idnumberformat',
        'local_ltiprovider'), get_string('genericformathelp', 'local_ltiprovider'), 0, $options));

    $settings->add(new admin_setting_configcheckbox('local_ltiprovider/duplicatecourseswithoutusers', get_string('duplicatecourseswithoutusers', 'local_ltiprovider'),
                       get_string('duplicatecourseswithoutusershelp', 'local_ltiprovider'), 0));

    $settings->add(new admin_setting_configmultiselect('local_ltiprovider/rolesallowedcreatecontexts', get_string('rolesallowedcreatecontexts', 'local_ltiprovider'),
                   '', array('Administrator'),
                       array(
                           'Student' => 'Student',
                           'Faculty' => 'Faculty',
                           'Member' => 'Member',
                           'Learner' => 'Learner',
                           'Instructor' => 'Instructor',
                           'Mentor' => 'Mentor',
                           'Staff' => 'Staff',
                           'Alumni' => 'Alumni',
                           'ProspectiveStudent' => 'ProspectiveStudent',
                           'Guest' => 'Guest',
                           'Other' => 'Other',
                           'Administrator' => 'Administrator',
                           'Observer' => 'Observer',
                           'None' => 'None'
                       )));

    $settings->add(new admin_setting_configmultiselect('local_ltiprovider/rolesallowedcreateresources', get_string('rolesallowedcreateresources', 'local_ltiprovider'),
                   '', array('Administrator'),
                       array(
                           'Student' => 'Student',
                           'Faculty' => 'Faculty',
                           'Member' => 'Member',
                           'Learner' => 'Learner',
                           'Instructor' => 'Instructor',
                           'Mentor' => 'Mentor',
                           'Staff' => 'Staff',
                           'Alumni' => 'Alumni',
                           'ProspectiveStudent' => 'ProspectiveStudent',
                           'Guest' => 'Guest',
                           'Other' => 'Other',
                           'Administrator' => 'Administrator',
                           'Observer' => 'Observer',
                           'None' => 'None'
                       )));
}