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
 * Shared question grading for the activity and embed web services.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

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
