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

namespace mod_simplequiz2\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Decoding and size limits for the client-supplied question list of the embed player.
 *
 * The payload is posted by the browser, so it is bounded before any filtering or
 * rendering: total size, JSON depth, number of questions and answers, and the length
 * of every text field. Limits match what an activity can hold.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed_payload {
    /** @var int Maximum size of the JSON payload, in bytes. */
    public const MAX_PAYLOAD_BYTES = 262144;

    /** @var int Maximum JSON nesting (list > question > answers > answer, plus one level of extra data). */
    public const MAX_DEPTH = 5;

    /** @var int Maximum length of one text field, in bytes. */
    public const MAX_FIELD_BYTES = 32000;

    /** @var string[] Text fields of a question. */
    private const QUESTION_TEXT_FIELDS = ['text', 'correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];

    /** @var string Capability that grants access to the tutor practice quiz. */
    public const TUTOR_CAPABILITY = 'block/dixeo_tutor:talktotutor';

    /**
     * Require access to the tutor practice quiz in a course.
     *
     * The embed player is only used by block_dixeo_tutor, which is optional. Without
     * the block its capability is undefined and the services are unavailable.
     *
     * @param \context $context Course context.
     * @return void
     */
    public static function require_tutor_capability(\context $context): void {
        if (!get_capability_info(self::TUTOR_CAPABILITY)) {
            throw new \moodle_exception('error_embedunavailable', 'mod_simplequiz2');
        }
        require_capability(self::TUTOR_CAPABILITY, $context);
    }

    /**
     * Decode and validate a JSON question list.
     *
     * @param string $json JSON array of questions.
     * @return array List of question arrays.
     * @throws \invalid_parameter_exception When the payload exceeds a limit or is malformed.
     */
    public static function decode(string $json): array {
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new \invalid_parameter_exception('Questions payload too large');
        }

        $decoded = json_decode($json, true, self::MAX_DEPTH);
        if (!is_array($decoded) || !$decoded || array_keys($decoded) !== range(0, count($decoded) - 1)) {
            throw new \invalid_parameter_exception('Invalid questions JSON');
        }
        if (count($decoded) > MOD_SIMPLEQUIZ2_MAX_QUESTION_NB) {
            throw new \invalid_parameter_exception('Too many questions');
        }

        foreach ($decoded as $question) {
            if (!is_array($question)) {
                throw new \invalid_parameter_exception('Invalid question');
            }
            foreach (self::QUESTION_TEXT_FIELDS as $field) {
                self::check_text($question[$field] ?? '');
            }

            $answers = $question['answers'] ?? [];
            if (!is_array($answers) || count($answers) > MOD_SIMPLEQUIZ2_MAX_ANSWER_NB) {
                throw new \invalid_parameter_exception('Invalid answers');
            }
            foreach ($answers as $answer) {
                if (!is_array($answer)) {
                    throw new \invalid_parameter_exception('Invalid answer');
                }
                self::check_text($answer['text'] ?? '');
            }
        }

        return $decoded;
    }

    /**
     * Require a scalar text field within the length limit.
     *
     * @param mixed $value Field value.
     * @return void
     */
    private static function check_text($value): void {
        if ($value !== null && !is_scalar($value)) {
            throw new \invalid_parameter_exception('Invalid text field');
        }
        if (strlen((string) $value) > self::MAX_FIELD_BYTES) {
            throw new \invalid_parameter_exception('Text field too long');
        }
    }
}
