<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External service to read an activity attempt score.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_simplequiz2\simplequiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Return the current attempt score and the user's best grade.
 */
class get_attempt_results extends external_api {
    use activity_attempt;

    /**
     * Parameters for reading attempt results.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module id'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt data row id'),
        ]);
    }

    /**
     * Read the attempt score and the best stored grade for the current user.
     *
     * @param int $coursemoduleid Course module id
     * @param int $attemptid Attempt data row id
     * @return array Attempt score and best score
     */
    public static function execute(int $coursemoduleid, int $attemptid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'coursemoduleid' => $coursemoduleid,
            'attemptid' => $attemptid,
        ]);
        [$cm] = self::require_own_attempt($params['coursemoduleid'], $params['attemptid']);

        $simplequiz = new simplequiz((int) $cm->id);

        return [
            'attemptgrade' => (float) $simplequiz->get_attempt_grade($params['attemptid']),
            'bestscore' => (float) $simplequiz->get_current_grade((int) $USER->id),
        ];
    }

    /**
     * Return values for an attempt summary.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptgrade' => new external_value(PARAM_FLOAT, 'Score for this attempt'),
            'bestscore' => new external_value(PARAM_FLOAT, 'Best stored grade for the user'),
        ]);
    }
}
