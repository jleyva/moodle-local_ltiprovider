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
 * Language strings
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'LTI Provider';
$string['providetool'] = 'Provide a tool for an external system';

$string['remotesystem'] = 'Remote system';
$string['userdefaultvalues'] = 'User default values';
$string['remoteencoding'] = 'Remote system encoding';
$string['secret'] = 'Shared secret';
$string['toolsettings'] = 'Tool settings';

$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid (in seconds). If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user enrols themselves from the remote system. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can access from this date onward only.';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can access until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';

$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can access from the remote system. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to access was already reached.';

$string['courseroleinstructor'] = 'Course role for Instructor';
$string['courserolelearner'] = 'Course role for Learner';
$string['activityroleinstructor'] = 'Activity role for Instructor';
$string['activityrolelearner'] = 'Activity role for Learner';

$string['tooldisabled'] = 'Access to the tool is disabled';
$string['tooltobeprovide'] = 'Tool to be provided';
$string['delconfirm'] = 'Are you sure you want to delete this tool?';
$string['deletetool'] = 'Delete a tool';
$string['toolsprovided'] = 'List of tools provided';
$string['name'] = 'Tool name';
$string['url'] = 'Launch URL';
$string['layoutandcss'] = 'Layout and CSS';
$string['hidepageheader'] = 'Hide page header';
$string['hidepagefooter'] = 'Hide page footer';
$string['hideleftblocks'] = 'Hide left blocks';
$string['hiderightblocks'] = 'Hide right blocks';
$string['customcss'] = 'Custom CSS';
$string['sendgrades'] = 'Send grades back';
$string['forcenavigation'] = 'Force course or activity navigation';

$string['invalidcredentials'] = 'Invalid credentials';
$string['allowframembedding'] = 'In order to avoid problems embedding this site, please enable the allowframembedding setting in Admin -> Security -> HTTP security';
$string['newpopupnotice'] = 'The tool will be opened in a new Window. Please, check that popups for this site are enabled in your browser. You can use the link displayed bellow for opening the tool.';
$string['opentool'] = 'Open tool in a new window';

$string['enrolmentnotstarted'] = 'The enrolment period has not started';
$string['enrolmentfinished'] = 'The enrolment period has finished';
$string['ltiprovider:manage'] = 'Manage tools (provide)';
$string['ltiprovider:view'] = 'View tools provided';

$string['globalsharedsecret'] = 'Global Shared Secret';
$string['defaultauthmethod'] = 'Default auth method';
$string['defaultauthmethodhelp'] = 'This is the auth method assigned a new users created by the plugin';
$string['delegate'] = 'Delegate';
$string['userprofileupdate'] = 'User profile update';
$string['userprofileupdatehelp'] = 'Never for not update the user profile on every remote access, Delegate to be configured at tool level';
$string['rolesallowedcreateresources'] = 'Roles allowed to create resources (from the remote site)';
$string['rolesallowedcreatecontexts'] = 'Roles allowed to create contexts (from the remote site)';
$string['cantdeterminecontext'] = 'Can\' determine the context, it seems that there are more than one tool provided for this context_id';

$string['invalidtplcourse'] = 'Invalid course template id';
$string['missingrequiredtool'] = 'For duplicating a resource, you must point the request to an existing resource type course';
$string['invalidtypetool'] = 'For duplicating a resource, you must point the request to a resource type course';
$string['invalidresourcecopyid'] = 'Invalid resource to be copied identifier';

$string['coursebeingrestored'] = 'This course is being restored, it can take some minutes to finish';

$string['membershipsettings'] = 'Memberships service settings';
$string['enablememberssync'] = 'Enable members synchronization';
$string['syncperiod'] = 'Synchronization period';
$string['syncmode'] = 'Synchronization mode';
$string['enrolandunenrol'] = 'Enrol new and unenrol missing members';
$string['enrolnew'] = 'Enrol new members';
$string['unenrolmissing'] = 'Unenrol missing members';

$string['idnumberformat'] = 'Idnumber format for new created courses';
$string['shortnameformat'] = 'Shortname format for new created courses';
$string['fullnameformat'] = 'Fullname format for new created courses';
$string['genericformathelp'] = 'For remotely new create courses you can select the remote parameters for creating the name';

$string['duplicatecourseswithoutusers'] = 'Duplicate courses without users';
$string['duplicatecourseswithoutusershelp'] = 'When creating a new course, do not import the users from the template course';

$string['subplugintype_ltiproviderextension'] = 'LTI extension';
$string['subplugintype_ltiproviderextension_plural'] = 'LTI extensions';

$string['requirecompletion'] = 'Require course or activity completed before sending the grades';
$string['errorcompletionenabled'] = 'Completion should be enabled for the course or the activity';

$string['enrolinst'] = 'Automatically enrol Instructors';
$string['enrolinst_help'] = 'Uncheck this box to redirect instructors to the course page so they can select a self-enrolment option';
$string['enrollearn'] = 'Automatically enrol Learners';
$string['enrollearn_help'] = 'Uncheck this box to redirect learners to the course page so they can select a self-enrolment option';
