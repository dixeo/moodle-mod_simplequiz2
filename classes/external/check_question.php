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
 * External service to grade one activity question.
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
use mod_simplequiz2\question_grading_service;
use mod_simplequiz2\simplequiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Grade one question in the activity player and store the result on the attempt.
 */
class check_question extends external_api {
    use activity_attempt;

    /**
     * Parameters for checking a question.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module id'),
            'questionid' => new external_value(PARAM_INT, 'Zero-based question index'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt data row id'),
            'answers' => new external_value(PARAM_TEXT, 'Comma-separated selected answer indices', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Grade the submitted answers and record them on the user's attempt.
     *
     * @param int $coursemoduleid Course module id
     * @param int $questionid Zero-based question index
     * @param int $attemptid Attempt data row id
     * @param string $answers Comma-separated selected answer indices
     * @return array Correctness, per-answer results, and feedback HTML
     */
    public static function execute(
        int $coursemoduleid,
        int $questionid,
        int $attemptid,
        string $answers = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'coursemoduleid' => $coursemoduleid,
            'questionid' => $questionid,
            'attemptid' => $attemptid,
            'answers' => $answers,
        ]);
        [$cm] = self::require_own_attempt($params['coursemoduleid'], $params['attemptid']);

        $simplequiz = new simplequiz((int) $cm->id);
        $questiondata = (array) json_decode($simplequiz->__get('instance')->questions);
        $question = isset($questiondata[$params['questionid']])
            ? simplequiz2_normalize_question($questiondata[$params['questionid']])
            : null;

        $grading = ['iscorrect' => false, 'results' => [], 'haspartialcorrect' => false];
        if ($question) {
            $grading = question_grading_service::grade_question($question, $params['answers']);
        }

        $simplequiz->add_attempt_answer($params['attemptid'], $params['questionid'], $grading['iscorrect']);

        $feedback = '';
        if ($question) {
            $outcome = simplequiz2_feedback_outcome_from_grading(
                $grading['iscorrect'],
                $grading['haspartialcorrect']
            );
            $feedback = simplequiz2_get_feedback_for_outcome(
                $question,
                $params['questionid'],
                (int) $cm->id,
                $outcome
            );
        }

        return [
            'iscorrect' => $grading['iscorrect'],
            'results' => json_encode((object) $grading['results']),
            'feedback' => $feedback,
        ];
    }

    /**
     * Return values for a checked question.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'iscorrect' => new external_value(PARAM_BOOL, 'Whether the answer is fully correct'),
            'results' => new external_value(PARAM_RAW, 'JSON map of selected answer index to correctness'),
            'feedback' => new external_value(PARAM_RAW, 'Formatted feedback HTML'),
        ]);
    }
}
