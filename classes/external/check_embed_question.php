<?php
/**
 * External API: grade one embed/practice quiz question server-side.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_simplequiz2\question_grading_service;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Check answers for an ephemeral embed quiz question.
 */
class check_embed_question extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'questions' => new external_value(PARAM_RAW, 'JSON array of simplequiz2 questions'),
            'questionid' => new external_value(PARAM_INT, 'Zero-based question index'),
            'answers' => new external_value(PARAM_TEXT, 'Comma-separated selected answer indices', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * @param int $courseid
     * @param string $questions
     * @param int $questionid
     * @param string $answers
     * @return array
     */
    public static function execute(int $courseid, string $questions, int $questionid, string $answers = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'questions' => $questions,
            'questionid' => $questionid,
            'answers' => $answers,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $decoded = json_decode($params['questions'], true);
        if (!is_array($decoded) || !array_key_exists($params['questionid'], $decoded)) {
            throw new \invalid_parameter_exception('Invalid questions JSON or question index');
        }

        $rawquestion = $decoded[$params['questionid']];
        $question = simplequiz2_normalize_question(is_object($rawquestion) ? $rawquestion : (object) $rawquestion);

        $grading = question_grading_service::grade_question($question, $params['answers']);
        $outcome = simplequiz2_feedback_outcome_from_grading(
            $grading['iscorrect'],
            $grading['haspartialcorrect']
        );
        $rawfeedback = simplequiz2_get_raw_feedback_for_outcome($question, $outcome);
        $feedback = '';
        if (!\mod_simplequiz2\util\editor_content::is_empty($rawfeedback)) {
            $feedback = trim(format_text($rawfeedback, FORMAT_HTML, [
                'noclean' => true,
                'para' => false,
                'filter' => true,
                'context' => $context,
            ]));
        }

        return [
            'iscorrect' => $grading['iscorrect'],
            'haspartial' => $grading['haspartialcorrect'],
            'results' => json_encode((object) $grading['results']),
            'feedback' => $feedback,
            'correctanswer' => question_grading_service::first_correct_answer_plaintext($question),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'iscorrect' => new external_value(PARAM_BOOL, 'Whether the answer is fully correct'),
            'haspartial' => new external_value(PARAM_BOOL, 'Whether at least one correct choice was selected'),
            'results' => new external_value(PARAM_RAW, 'JSON map of selected answer index to correctness'),
            'feedback' => new external_value(PARAM_RAW, 'Formatted feedback HTML'),
            'correctanswer' => new external_value(PARAM_TEXT, 'Plain text of first correct answer'),
        ]);
    }
}
