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
 * Activity custom completion subclass for the simplequiz2 activity.
 *
 * Class for defining mod_simplequiz2's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given simplequiz2 instance and a user.
 *
 * @package    mod_simplequiz2
 * @copyright  2023 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\completion;

use core_completion\activity_custom_completion;
use Exception;
use mod_simplequiz2\simplequiz;

/**
 * Custom completion rules for simplequiz2 (e.g. minimum attempts).
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionminattempts',
        ];
    }

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;
        // Make sure to validate the custom completion rule first.
        $this->validate_rule($rule);

        $status = COMPLETION_INCOMPLETE;

        // Get simplequiz details.
        if (!$simplequizrecord = $DB->get_record('simplequiz2', ['id' => $this->cm->instance])) {
            throw new Exception("Can't find simplequiz {$this->cm->instance}");
        }

        // Check if user has enough attempts.
        if ($rule == 'completionminattempts') {
            if ($simplequizrecord->completionminattempts >= 0) {
                $simplequiz = new simplequiz($this->cm->id);

                $attemptsdata = $simplequiz->get_user_attempt($this->userid);
                if ($attemptsdata) {
                    $status = $attemptsdata->completed == 1 ||
                              ($attemptsdata->cntattempt >= $simplequizrecord->completionminattempts);
                }
            }
        }

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $minattempts = $this->cm->customdata['customcompletionrules']['completionminattempts'] ?? 0;

        return [
            'completionminattempts' => get_string('completionminattempts:attempts', 'simplequiz2', $minattempts),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionusegrade',
            'completionminattempts',
        ];
    }
}
