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

namespace mod_simplequiz2\external;

use core_external\external_api;

/**
 * Tests for the edit-form question summary service.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_simplequiz2\external\render_question_summary
 */
final class render_question_summary_test extends \advanced_testcase {
    /**
     * Question fields containing script and event handlers.
     *
     * @return array
     */
    private function malicious_question(): array {
        return [
            'text' => '<p>Question</p><img src="x" onerror="alert(1)">',
            'answers' => [
                ['text' => '<p onclick="alert(2)">Answer</p>', 'iscorrect' => true],
            ],
            'correctfeedback' => '<p>Yes</p><script>alert(3)</script>',
            'partiallycorrectfeedback' => '',
            'incorrectfeedback' => '<svg onload="alert(4)"></svg>No',
        ];
    }

    /**
     * Teachers get a summary with author HTML cleaned.
     */
    public function test_summary_is_cleaned(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        foreach ([\context_module::instance($quiz->cmid), \context_course::instance($course->id)] as $context) {
            $result = render_question_summary::execute($context->id, $this->malicious_question());
            $result = external_api::clean_returnvalue(render_question_summary::execute_returns(), $result);
            $html = strtolower($result['html']);

            $this->assertStringContainsString('question', $html);
            $this->assertStringContainsString('answer', $html);
            $this->assertStringNotContainsString('onerror', $html);
            $this->assertStringNotContainsString('onclick', $html);
            $this->assertStringNotContainsString('onload', $html);
            $this->assertStringNotContainsString('<script', $html);
        }
    }

    /**
     * Students cannot use the service.
     */
    public function test_student_cannot_render_summary(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $this->expectException(\required_capability_exception::class);
        render_question_summary::execute(\context_module::instance($quiz->cmid)->id, $this->malicious_question());
    }

    /**
     * Contexts other than course or module are rejected.
     */
    public function test_rejects_other_contexts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        render_question_summary::execute(\context_system::instance()->id, $this->malicious_question());
    }
}
