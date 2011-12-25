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

/// get url variables
class edit_form extends moodleform {

    // Define the form
    function definition () {
        global $USER, $CFG, $COURSE;

        $mform =& $this->_form;
        $templateuser = $USER;
        $context = $this->_customdata['context'];

        $mform->addElement('header', 'settingsheader', get_string('toolsettings', 'local_ltiprovider'));
        
        $tools = array();
        $tools[$context->id] = get_string('course');
        get_all_mods($this->_customdata['courseid'], $mods, $modnames, $modnamesplural, $modnamesused);
        
        foreach($mods as $mod){
            print_r($mod);
        }
        $mform->addElement('select', 'contextid', get_string('tooltobeprovide','local_ltiprovider'), $tools);
        $mform->setDefault('contextid', $context->id);
        
        $assignableroles = get_assignable_roles($context);
        
        $mform->addElement('select', 'croleinst', get_string('courseroleinstructor','local_ltiprovider'), $assignableroles);
        $mform->addElement('select', 'crolelearn', get_string('courserolelearner','local_ltiprovider'), $assignableroles);
        
        $mform->addElement('select', 'aroleinst', get_string('activityroleinstructor','local_ltiprovider'), $assignableroles);
        $mform->disabledIf('aroleinst', 'contextid', 'eq', 0);
        $mform->addElement('select', 'arolelearn', get_string('activityrolelearner','local_ltiprovider'), $assignableroles);
        $mform->disabledIf('arolelearn', 'contextid', 'eq', 0);
        
        
        $mform->addElement('header', 'settingsheader', get_string('remotesystem', 'local_ltiprovider'));
        
        $mform->addElement('text', 'secret', get_string('secret', 'local_ltiprovider'), 'maxlength="64" size="25"');
        $mform->setType('secret', PARAM_MULTILANG);
        $mform->setDefault('secret', md5(uniqid(rand(), 1)));
        $mform->addRule('secret', get_string('required'), 'required');
        
        $textlib = textlib_get_instance();
        $choices = $textlib->get_encodings();
        $mform->addElement('select', 'encoding', get_string('remoteencoding', 'local_ltiprovider'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        
        
        $mform->addElement('header', 'defaultheader', get_string('userdefaultvalues', 'local_ltiprovider'));
        
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
        $mform->addRule('city', get_string('required'), 'required');
        
        $mform->addElement('select', 'country', get_string('selectacountry'), get_string_manager()->get_list_of_countries());
        if (empty($CFG->country)) {
            $mform->setDefault('country', $templateuser->country);
        } else {
            $mform->setDefault('country', $CFG->country);
        }
        $mform->setAdvanced('country');

        $choices = get_list_of_timezones();
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
        
        $mform->addElement('hidden','id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden','courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        global $COURSE, $DB, $CFG;

        $errors = parent::validation($data, $files);

        return $errors;
    }

}