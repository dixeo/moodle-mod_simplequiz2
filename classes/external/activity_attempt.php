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
 * Shared access checks for the activity player services.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\external;

/**
 * Loads a course module and requires the current user's own attempt.
 */
trait activity_attempt {
    /**
     * Require login, mod/simplequiz2:view, and an attempt owned by this user.
     *
     * @param int $coursemoduleid Course module id
     * @param int $attemptid Attempt data row id
     * @return array{0: \stdClass, 1: \context_module} Course module and module context
     */
    protected static function require_own_attempt(int $coursemoduleid, int $attemptid): array {
        global $DB, $USER;

        $cm = get_coursemodule_from_id('simplequiz2', $coursemoduleid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/simplequiz2:view', $context);

        $attempt = $DB->get_record('simplequiz2_attempt_data', ['id' => $attemptid], '*', MUST_EXIST);
        if ((int) $attempt->userid !== (int) $USER->id || (int) $attempt->cmid !== (int) $cm->id) {
            throw new \moodle_exception('error_invalidattempt', 'mod_simplequiz2');
        }

        return [$cm, $context];
    }
}
