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
 * External service to render the edit-form summary of one question.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_simplequiz2\util\question_summary_context;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Render the question summary shown on the activity edit form.
 *
 * Editor values are author HTML that has not been cleaned yet, so the summary is
 * always built server-side with format_text() before it reaches the page.
 */
class render_question_summary extends external_api {
    /** @var int Maximum length of one editor field, in characters. */
    public const MAX_FIELD_LENGTH = 65536;

    /**
     * Parameters for rendering a question summary.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Module context id when editing, course context id when adding'),
            'question' => new external_single_structure([
                'text' => new external_value(PARAM_RAW, 'Question text HTML', VALUE_DEFAULT, ''),
                'answers' => new external_multiple_structure(
                    new external_single_structure([
                        'text' => new external_value(PARAM_RAW, 'Answer HTML'),
                        'iscorrect' => new external_value(PARAM_BOOL, 'Whether the answer is correct', VALUE_DEFAULT, false),
                    ]),
                    'Answers',
                    VALUE_DEFAULT,
                    []
                ),
                'correctfeedback' => new external_value(PARAM_RAW, 'Correct feedback HTML', VALUE_DEFAULT, ''),
                'partiallycorrectfeedback' => new external_value(PARAM_RAW, 'Partially correct feedback HTML', VALUE_DEFAULT, ''),
                'incorrectfeedback' => new external_value(PARAM_RAW, 'Incorrect feedback HTML', VALUE_DEFAULT, ''),
            ]),
        ]);
    }

    /**
     * Render the cleaned summary HTML for one question.
     *
     * @param int $contextid Module or course context id.
     * @param array $question Question fields as edited in the form.
     * @return array Rendered HTML
     */
    public static function execute(int $contextid, array $question): array {
        global $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'question' => $question,
        ]);

        $context = \context::instance_by_id($params['contextid']);
        if (!in_array((int) $context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            throw new \invalid_parameter_exception('Invalid context');
        }
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $question = $params['question'];
        if (count($question['answers']) > MOD_SIMPLEQUIZ2_MAX_ANSWER_NB) {
            throw new \invalid_parameter_exception('Too many answers');
        }
        $fields = [$question['text'], $question['correctfeedback'], $question['partiallycorrectfeedback'],
            $question['incorrectfeedback']];
        foreach ($question['answers'] as $answer) {
            $fields[] = $answer['text'];
        }
        foreach ($fields as $field) {
            if (\core_text::strlen($field) > self::MAX_FIELD_LENGTH) {
                throw new \invalid_parameter_exception('Field too long');
            }
        }

        $questiondata = (object) [
            'text' => $question['text'],
            'answers' => array_map(fn(array $answer): object => (object) $answer, $question['answers']),
            'correctfeedback' => $question['correctfeedback'],
            'partiallycorrectfeedback' => $question['partiallycorrectfeedback'],
            'incorrectfeedback' => $question['incorrectfeedback'],
        ];

        $html = $OUTPUT->render_from_template(
            'mod_simplequiz2/question_summary',
            question_summary_context::from_stored_question($questiondata, $context)
        );

        return ['html' => $html];
    }

    /**
     * Return values for a rendered summary.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Summary HTML built from cleaned author content'),
        ]);
    }
}
