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

namespace mod_simplequiz2\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Tests for the embed player question payload limits.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_simplequiz2\util\embed_payload
 */
final class embed_payload_test extends \advanced_testcase {
    /**
     * Build a question list.
     *
     * @param int $questions Number of questions.
     * @param int $answers Number of answers per question.
     * @param string $text Question text.
     * @return string JSON
     */
    private static function payload(int $questions, int $answers, string $text = '<p>Q</p>'): string {
        $list = [];
        for ($i = 0; $i < $questions; $i++) {
            $question = ['text' => $text, 'answers' => []];
            for ($j = 0; $j < $answers; $j++) {
                $question['answers'][] = ['text' => '<p>A</p>', 'iscorrect' => $j === 0];
            }
            $list[] = $question;
        }
        return json_encode($list);
    }

    /**
     * A payload within the limits is decoded.
     */
    public function test_valid_payload(): void {
        $decoded = embed_payload::decode(self::payload(MOD_SIMPLEQUIZ2_MAX_QUESTION_NB, MOD_SIMPLEQUIZ2_MAX_ANSWER_NB));
        $this->assertCount(MOD_SIMPLEQUIZ2_MAX_QUESTION_NB, $decoded);
    }

    /**
     * Payloads that exceed a limit or are malformed are rejected.
     *
     * @dataProvider invalid_payload_provider
     * @param string $json Payload.
     */
    public function test_invalid_payload(string $json): void {
        $this->expectException(\invalid_parameter_exception::class);
        embed_payload::decode($json);
    }

    /**
     * Invalid payloads.
     *
     * @return array
     */
    public static function invalid_payload_provider(): array {
        return [
            'too many questions' => [self::payload(MOD_SIMPLEQUIZ2_MAX_QUESTION_NB + 1, 2)],
            'too many answers' => [self::payload(1, MOD_SIMPLEQUIZ2_MAX_ANSWER_NB + 1)],
            'field too long' => [self::payload(1, 2, str_repeat('a', embed_payload::MAX_FIELD_BYTES + 1))],
            'payload too large' => [str_repeat(' ', embed_payload::MAX_PAYLOAD_BYTES + 1) . '[]'],
            'too deep' => ['[{"text":"q","answers":[{"text":{"a":{"b":{"c":1}}}}]}]'],
            'empty' => ['[]'],
            'object' => ['{"a":{"text":"q"}}'],
            'not json' => ['nope'],
        ];
    }
}
