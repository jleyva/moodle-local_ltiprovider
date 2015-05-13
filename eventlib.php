<?php
namespace LTIProvider;
defined('MOODLE_INTERNAL') || die();
/** Event handler for LTIProvider.
 * This helps fix the idea of using Events2 in Moodle to send grades.
 */
class Grade {
	public function __construct() {
		//
	}
	public function user_send ($evt) {
		global $DB;
		$data = $evt->get_data();
		// get the tool info.
		$tools = $DB->get_records('local_ltiprovider', array("courseid" => $data['courseid'], 'contextid' => $data['contextid']));
		$sending = array();
		if ($tools) {
			foreach ($tools as $tool) {
				$users = $DB->get_records('local_ltiprovider_user', array("toolid" => $tool->id, "userid" => $data['userid']));
				if ($users) {
					foreach ($users as $user) {
						debugging("Preparing to send grade for tool {$tool->id} and user {$data['userid']}");
						$sending[] = array($tool, $user);
					}
				} else {
					debugging("No users found for tool id {$tool->id}.");
				}
			}
			if (empty($sending) !== true) {
				$result = self::send_grade($sending);
				//TODO:  Something with the result.  Research logging.
				debugging(print_r($result, true));
			} else {
				debugging("No grades to send.");
			}
		} else {
			debugging("No tool using course {$data['courseid']} with context {$data['contextid']}.");
		}
	}

	private static function send_grade($sending) {
		global $CFG;
		$result = array();
		debugging("Attempting to send grades");
		require_once($CFG->dirroot.'/local/ltiprovider/lib.php');
		foreach ($sending as $send) {
			$result[] = local_ltiprovider_send_grade($send[0], $send[1]);
		}
		return $result;
	}
}
