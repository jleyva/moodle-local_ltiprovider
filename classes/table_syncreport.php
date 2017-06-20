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
 * Table for displaying syncreports.
 *
 *
 * @package    local
 * @subpackage ltiprovider
 * @copyright  2017 Juan Leyva <juanleyvadelgado@gmail.com>, Antoni Bertran <antoni@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/tablelib.php');


class local_ltiprovider_table_syncreport extends table_sql {

    /** @var stdClass lti_toolprovider parameters */
    protected $lti_toolprovider;

    /** @var stdClass filters parameters */
    protected $filterparams;

    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param stdClass $filterparams (optional) filter params.
     *     - int courseid: id of course
     *     - int userid: user id
     *     - int|string modid: Module id or "site_errors" to view site errors
     *     - int groupid: Group id
     *     - \core\log\sql_reader logreader: reader from which data will be fetched.
     *     - int edulevel: educational level.
     *     - string action: view action
     *     - int date: Date from which logs to be viewed.
     */
    public function __construct($uniqueid, $lti_toolprovider, $filterparams=null) {
        parent::__construct($uniqueid);
        global $DB;

        $this->set_attribute('class', 'generaltable generalbox');
        $this->set_attribute('aria-live', 'polite');
        $this->lti_toolprovider = $lti_toolprovider;
        $this->filterparams = $filterparams;

        $this->define_columns(array('checkbox', 'fullnameuser', 'lastsync', 'lastgrade', 'forcesendbutton', 'serviceurl', 'sourceid'));
        $this->define_headers(array(
                get_string('select'),
                get_string('fullnameuser'),
                get_string('time'),
                get_string('grade'),
                '',
                get_string('gradessourceid', 'local_ltiprovider'),
                get_string('gradesserviceurl', 'local_ltiprovider')
                )
        );
        $this->no_sorting('checkbox');
        $this->no_sorting('forcesendbutton');
        $this->no_sorting('serviceurl');
        $this->no_sorting('sourceid');
        $this->collapsible(true);
        $this->sortable(true);
        $this->pageable(true);
        $this->is_downloadable(false);
    }

    /**
     * Generate select checkbox column.
     *
     * @param stdClass $row_report event data.
     * @return string HTML for the username column
     */
    public function col_checkbox($row_report) {

        $checked = '';
        return '<input type="checkbox" class="usercheckbox" name="user_force_grade_checkbox" value="'.$row_report->id.'" ' . $checked .'/>';

    }

    /**
     * Generate the username column.
     *
     * @param stdClass $row_report event data.
     * @return string HTML for the username column
     */
    public function col_fullnameuser($row_report) {

        return fullname($row_report);

    }

    /**
     * Generate the lastsync column.
     *
     * @param stdClass $row_report event data.
     * @return string HTML for the time column
     */
    public function col_time($row_report) {
        $recenttimestr = get_string('strftimerecent', 'core_langconfig');
        return userdate($row_report->lastsync, $recenttimestr);
    }

    /**
     * Generate the forcesendbutton column.
     *
     * @param stdClass $row_report event data.
     * @return string HTML for the course column.
     */
    public function col_forcesendbutton($row_report) {
        global $OUTPUT;
        $forcesendurl = new \moodle_url('test/forcesendgrades.php', array('toolid' => $this->lti_toolprovider->id, 'userid' => $row_report->id, 'printresponse' => 1));
        $forcesendbutton = $OUTPUT->single_button($forcesendurl, get_string('forcesendgrades', 'local_ltiprovider'));

        return $forcesendbutton;
    }


    /**
     * Generate the sourceid column.
     *
     * @param stdClass $row_report event data.
     * @return string HTML for the related username column
     */
    public function col_sourceid($row_report) {
        
        return s($row_report->sourceid);
    }

    /**
     * Builds the SQL query.
     *
     * @param bool $count When true, return the count SQL.
     * @return array containing sql to use and an array of params.
     */
    protected function get_sql_and_params($count = false) {
        global $DB;
        $fields = 'u.*, g.serviceurl, g.sourceid, g.lastgrade, g.lastsync';
        list($extra_sql, $params) = $this->get_sql_filters();

        if ($count) {
            $select = "COUNT(1)";
        } else {
            $select = "$fields";
        }

        $sql = "SELECT $select
                  FROM {local_ltiprovider_user} g JOIN {user} u
                ON u.id = g.userid
                WHERE g.lastsync > 0 AND g.toolid = :toolid ".
                $extra_sql;
        $params = array_merge($params, array('toolid' => $this->lti_toolprovider->id));

        // Add order by if needed.
        if (!$count && $sqlsort = $this->get_sql_sort()) {
            if (strpos($sqlsort, 'fullnameuser')!==false) {
                $sqlsort = str_replace('fullnameuser', $DB->sql_fullname(), $sqlsort);
            }
            $sql .= " ORDER BY " . $sqlsort;
        }

        return array($sql, $params);
    }

    /**
     * Get the SQL filters
     * @return array
     */
    private function get_sql_filters() {
        global $DB;
        $params = array();
        $extra_sql = '';
        if ($this->filterparams && !empty($this->filterparams->fullname) ){
            $field = $DB->sql_fullname();
            $name = 'fullname';
            $value = $this->filterparams->fullname;
            $extra_sql .= ' AND '.$DB->sql_like($field, ":$name", false, false);
            $params[$name] = "%$value%";
        }

        return array($extra_sql, $params);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {

        global $DB;

        list($countsql, $countparams) = $this->get_sql_and_params(true);
        list($sql, $params) = $this->get_sql_and_params();
        $total = $DB->count_records_sql($countsql, $countparams);
        $this->pagesize($pagesize, $total);
        $this->rawdata = $DB->get_records_sql($sql, $params, $this->get_page_start(), $this->get_page_size());

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }

    }

    /**
     * Renders html to display a syncreport search form
     *
     * @param int $tool_id the lti tool id
     * @param string $value default value to populate the search field
     * @return string
     */
    function syncreport_search_form($tool_id, $value = '') {
        $formid = 'ltiprovidersyncreport';
        $inputid = 'coursesearchbox';
        $inputsize = 30;

        $strsearchuser= get_string('fullnameuser');
        $searchurl = new moodle_url('/local/ltiprovider/syncreport.php');

        $output = html_writer::start_tag('form', array('id' => $formid, 'action' => $searchurl, 'method' => 'get'));
        $output .= html_writer::start_tag('fieldset', array('class' => 'ltiprovidersearchbox invisiblefieldset'));
        $output .= html_writer::tag('label', $strsearchuser.': ', array('for' => $inputid));
        $output .= html_writer::empty_tag('input', array('type' => 'text', 'id' => $inputid,
            'size' => $inputsize, 'name' => 'search', 'value' => s($value)));
        $output .= html_writer::empty_tag('input', array('type' => 'submit',
            'value' => get_string('go')));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id',
            'value' => $tool_id));
        $output .= html_writer::end_tag('fieldset');
        $output .= html_writer::end_tag('form');

        return $output;
    }




}
