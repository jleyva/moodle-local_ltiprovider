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
 * Edit a tool provided in a course
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/course/lib.php');

/// get url variables
class edit_form extends moodleform {

    // Define the form
    public function definition () {
        global $USER, $CFG, $COURSE;

        $mform =& $this->_form;
        $templateuser = $USER;
        $context = $this->_customdata['context'];

        $mform->addElement('header', 'settingsheader', get_string('toolsettings', 'local_ltiprovider'));

        $tools = array();
        $tools[$context->id] = get_string('course');

        $modinfo = get_fast_modinfo($this->_customdata['courseid']);
        $mods = $modinfo->get_cms();

        foreach ($mods as $mod) {
            $tools[$mod->context->id] = format_string($mod->name);
        }

        $mform->addElement('select', 'contextid', get_string('tooltobeprovide', 'local_ltiprovider'), $tools);
        $mform->setDefault('contextid', $context->id);

        $mform->addElement('checkbox', 'sendgrades', null, get_string('sendgrades', 'local_ltiprovider'));
        $mform->setDefault('sendgrades', 1);

        $mform->addElement('checkbox', 'requirecompletion', null, get_string('requirecompletion', 'local_ltiprovider'));
        $mform->setDefault('requirecompletion', 0);
        $mform->disabledIf('requirecompletion', 'sendgrades');

        $mform->addElement('checkbox', 'forcenavigation', null, get_string('forcenavigation', 'local_ltiprovider'));
        $mform->setDefault('forcenavigation', 1);

        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'local_ltiprovider'), array('optional' => true, 'defaultunit' => 86400));
        $mform->setDefault('enrolperiod', 0);
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'local_ltiprovider');

        $mform->addElement('date_selector', 'enrolstartdate', get_string('enrolstartdate', 'local_ltiprovider'), array('optional' => true));
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'local_ltiprovider');

        $mform->addElement('date_selector', 'enrolenddate', get_string('enrolenddate', 'local_ltiprovider'), array('optional' => true));
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'local_ltiprovider');

        $mform->addElement('text', 'maxenrolled', get_string('maxenrolled', 'local_ltiprovider'));
        $mform->setDefault('maxenrolled', 0);
        $mform->addHelpButton('maxenrolled', 'maxenrolled', 'local_ltiprovider');
        $mform->setType('maxenrolled', PARAM_INT);

        $assignableroles = get_assignable_roles($context);

        $mform->addElement('checkbox', 'enrolinst', null, get_string('enrolinst', 'local_ltiprovider'));
        $mform->setDefault('enrolinst', 1);
        $mform->addHelpButton('enrolinst', 'enrolinst', 'local_ltiprovider');
        $mform->setAdvanced('enrolinst');
        $mform->addElement('checkbox', 'enrollearn', null, get_string('enrollearn', 'local_ltiprovider'));
        $mform->setDefault('enrollearn', 1);
        $mform->addHelpButton('enrollearn', 'enrollearn', 'local_ltiprovider');
        $mform->setAdvanced('enrollearn');

        $mform->addElement('select', 'croleinst', get_string('courseroleinstructor', 'local_ltiprovider'), $assignableroles);
        $mform->setDefault('croleinst', '3');
        $mform->setAdvanced('croleinst');
        $mform->addElement('select', 'crolelearn', get_string('courserolelearner', 'local_ltiprovider'), $assignableroles);
        $mform->setDefault('crolelearn', '5');
        $mform->setAdvanced('crolelearn');

        $mform->addElement('select', 'aroleinst', get_string('activityroleinstructor', 'local_ltiprovider'), $assignableroles);
        $mform->disabledIf('aroleinst', 'contextid', 'eq', $context->id);
        $mform->setDefault('aroleinst', '3');
        $mform->setAdvanced('aroleinst');
        $mform->addElement('select', 'arolelearn', get_string('activityrolelearner', 'local_ltiprovider'), $assignableroles);
        $mform->disabledIf('arolelearn', 'contextid', 'eq', $context->id);
        $mform->setDefault('arolelearn', '5');
        $mform->setAdvanced('arolelearn');

        $mform->addElement('header', 'remotesystem', get_string('remotesystem', 'local_ltiprovider'));

        $mform->addElement('text', 'secret', get_string('secret', 'local_ltiprovider'), 'maxlength="64" size="25"');
        $mform->setType('secret', PARAM_MULTILANG);
        $mform->setDefault('secret', md5(uniqid(rand(), 1)));
        $mform->addRule('secret', get_string('required'), 'required');


        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('remoteencoding', 'local_ltiprovider'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $mform->addElement('header', 'defaultheader', get_string('userdefaultvalues', 'local_ltiprovider'));

        $choices = array(0 => get_string('never'), 1 => get_string('always'));
        $mform->addElement('select', 'userprofileupdate', get_string('userprofileupdate', 'local_ltiprovider'), $choices);

        $userprofileupdate = get_config('local_ltiprovider', 'userprofileupdate');
        if ($userprofileupdate != -1) {
            $mform->setDefault('userprofileupdate', $userprofileupdate);
            $mform->freeze('userprofileupdate');
        } else {
            $mform->setDefault('userprofileupdate', 1);
        }

        $choices = array(0 => get_string('emaildisplayno'), 1 => get_string('emaildisplayyes'), 2 => get_string('emaildisplaycourse'));
        $mform->addElement('select', 'maildisplay', get_string('emaildisplay'), $choices);
        $mform->setDefault('maildisplay', 2);

        $mform->addElement('text', 'city', get_string('city'), 'maxlength="100" size="25"');
        $mform->setType('city', PARAM_MULTILANG);
        if (empty($CFG->defaultcity)) {
            $mform->setDefault('city', $templateuser->city);
        } else {
            $mform->setDefault('city', $CFG->defaultcity);
        }

        $mform->addElement('select', 'country', get_string('selectacountry'), get_string_manager()->get_list_of_countries());
        if (empty($CFG->country)) {
            $mform->setDefault('country', $templateuser->country);
        } else {
            $mform->setDefault('country', $CFG->country);
        }
        $mform->setAdvanced('country');

        $choices = core_date::get_list_of_timezones();
        $choices['99'] = get_string('serverlocaltime');
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', $templateuser->timezone);
        $mform->setAdvanced('timezone');

        $mform->addElement('select', 'lang', get_string('preferredlanguage'), get_string_manager()->get_list_of_translations());
        $mform->setDefault('lang', $templateuser->lang);
        $mform->setAdvanced('lang');

        $mform->addElement('text', 'institution', get_string('institution'), 'maxlength="40" size="25"');
        $mform->setType('institution', PARAM_MULTILANG);
        $mform->setDefault('institution', $templateuser->institution);
        $mform->setAdvanced('institution');

        $mform->addElement('header', 'memberships', get_string('membershipsettings', 'local_ltiprovider'));
        $mform->addElement('checkbox', 'syncmembers', null, get_string('enablememberssync', 'local_ltiprovider'));
        $mform->disabledIf('syncmembers', 'contextid', 'neq', $context->id);

        $options = array();
        $options[30*60]     = '30 ' . get_string('minutes');
        $options[60*60]     = '1 ' . get_string('hour');
        $options[2*60*60]   = '2 ' . get_string('hours');
        $options[6*60*60]   = '6 ' . get_string('hours');
        $options[12*60*60]  = '12 ' . get_string('hours');
        $options[24*60*60]  = '24 ' . get_string('hours');
        $mform->addElement('select', 'syncperiod', get_string('syncperiod', 'local_ltiprovider'), $options);
        $mform->setDefault('syncperiod', 30*60);
        $mform->disabledIf('syncperiod', 'contextid', 'neq', $context->id);

        $options = array();
        $options[1] = get_string('enrolandunenrol' , 'local_ltiprovider');
        $options[2] = get_string('enrolnew' , 'local_ltiprovider');
        $options[3] = get_string('unenrolmissing' , 'local_ltiprovider');
        $mform->addElement('select', 'syncmode', get_string('syncmode', 'local_ltiprovider'), $options);
        $mform->setDefault('syncmode', 1);
        $mform->disabledIf('syncmode', 'contextid', 'neq', $context->id);


        $mform->addElement('header', 'layoutandcss', get_string('layoutandcss', 'local_ltiprovider'));

        $mform->addElement('checkbox', 'hidepageheader', null, get_string('hidepageheader', 'local_ltiprovider'));
        $mform->addElement('checkbox', 'hidepagefooter', null, get_string('hidepagefooter', 'local_ltiprovider'));
        $mform->addElement('checkbox', 'hideleftblocks', null, get_string('hideleftblocks', 'local_ltiprovider'));
        $mform->addElement('checkbox', 'hiderightblocks', null, get_string('hiderightblocks', 'local_ltiprovider'));
        $mform->setAdvanced('hideleftblocks');
        $mform->setAdvanced('hiderightblocks');

        $editoroptions = array();
        $displayoptions = array('rows'=>'4', 'cols'=>'');
        $mform->addElement('textarea', 'customcss', get_string('customcss', 'local_ltiprovider'), $displayoptions, $editoroptions);
        $mform->setAdvanced('customcss');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $COURSE, $DB, $CFG;

        $errors = parent::validation($data, $files);

        if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'local_ltiprovider');
        }

        if (!empty($data['requirecompletion'])) {
            $completion = new completion_info($COURSE);
            $moodlecontext = $DB->get_record('context', array('id' => $data['contextid']));
            if ($moodlecontext->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id(false, $moodlecontext->instanceid, 0, false, MUST_EXIST);
            } else {
                $cm = null;
            }

            if (! $completion->is_enabled($cm)) {
                $errors['requirecompletion'] = get_string('errorcompletionenabled', 'local_ltiprovider');
            }
        }

        return $errors;
    }

}
