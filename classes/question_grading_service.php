<?php
/**
 * Shared question grading for activity AJAX and embed webservices.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Grade a simplequiz2 question against selected answer indices.
 */
class question_grading_service {

    /**
     * Grade selected answers for one question.
     *
     * @param object $question Normalized question object.
     * @param string $useranswerscsv Comma-separated answer indices, or empty string.
     * @return array{iscorrect: bool, results: array<int, bool>, haspartialcorrect: bool}
     */
    public static function grade_question(object $question, string $useranswerscsv): array {
        $question = simplequiz2_normalize_question($question);
        $results = [];
        $iscorrect = true;
        $selectedids = [];

        if ($useranswerscsv === '') {
            $iscorrect = false;
        } else {
            $selectedids = array_map('intval', explode(',', $useranswerscsv));

            foreach ($question->answers as $answerid => $answer) {
                if (is_array($answer)) {
                    $answer = (object) $answer;
                }

                if ((int) $answer->iscorrect === 1 && !in_array($answerid, $selectedids, true)) {
                    $iscorrect = false;
                    continue;
                }

                if ((int) $answer->iscorrect === 0 && in_array($answerid, $selectedids, true)) {
                    $iscorrect = false;
                    $results[$answerid] = false;
                    continue;
                }

                if (in_array($answerid, $selectedids, true)) {
                    $results[$answerid] = true;
                }
            }
        }

        $haspartialcorrect = false;
        if (!$iscorrect && $useranswerscsv !== '') {
            foreach ($results as $result) {
                if ($result === true) {
                    $haspartialcorrect = true;
                    break;
                }
            }
        }

        return [
            'iscorrect' => $iscorrect,
            'results' => $results,
            'haspartialcorrect' => $haspartialcorrect,
        ];
    }

    /**
     * Plain text of the first correct answer (for tutor context after check).
     *
     * @param object $question Normalized question object.
     * @return string
     */
    public static function first_correct_answer_plaintext(object $question): string {
        $question = simplequiz2_normalize_question($question);

        foreach ($question->answers as $answer) {
            if (is_array($answer)) {
                $answer = (object) $answer;
            }
            if ((int) $answer->iscorrect === 1) {
                return trim(html_to_text($answer->text ?? '', 0, false));
            }
        }

        return '';
    }
}
