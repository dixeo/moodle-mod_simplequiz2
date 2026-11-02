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
 * External services for the activity player.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

use core_external\external_api;
use mod_simplequiz2\external\check_question;
use mod_simplequiz2\external\get_attempt_results;
use mod_simplequiz2\simplequiz;

/**
 * External services for the activity player.
 */
final class activity_player_external_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create an activity, enrol a student, and open an attempt.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: int}
     */
    private function create_attempt_for_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');
        $instance = $generator->create_instance(['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $quiz = new simplequiz((int) $instance->cmid);
        $attemptid = (int) $quiz->create_attempt((int) $student->id);

        return [$instance, $student, $attemptid];
    }

    /**
     * A student can grade their own question through the external service.
     *
     * @covers \mod_simplequiz2\external\check_question::execute
     */
    public function test_student_can_check_question(): void {
        [$instance, , $attemptid] = $this->create_attempt_for_student();

        $result = check_question::execute((int) $instance->cmid, 0, $attemptid, '0');
        $result = external_api::clean_returnvalue(check_question::execute_returns(), $result);

        $this->assertTrue($result['iscorrect']);
        $decoded = json_decode($result['results'], true);
        $this->assertTrue($decoded['0']);
    }

    /**
     * A student cannot grade another user's attempt.
     *
     * @covers \mod_simplequiz2\external\check_question::execute
     */
    public function test_student_cannot_check_another_attempt(): void {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');
        $instance = $generator->create_instance(['course' => $course->id]);
        $owner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($owner);
        $quiz = new simplequiz((int) $instance->cmid);
        $attemptid = (int) $quiz->create_attempt((int) $owner->id);
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        check_question::execute((int) $instance->cmid, 0, $attemptid, '0');
    }

    /**
     * A user who is not enrolled cannot read attempt results.
     *
     * @covers \mod_simplequiz2\external\get_attempt_results::execute
     */
    public function test_unenrolled_user_cannot_read_results(): void {
        [$instance, , $attemptid] = $this->create_attempt_for_student();
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $this->expectException(\require_login_exception::class);
        get_attempt_results::execute((int) $instance->cmid, $attemptid);
    }

    /**
     * The attempt summary returns the score stored for this attempt.
     *
     * @covers \mod_simplequiz2\external\get_attempt_results::execute
     */
    public function test_student_can_read_attempt_results(): void {
        [$instance, , $attemptid] = $this->create_attempt_for_student();
        check_question::execute((int) $instance->cmid, 0, $attemptid, '0');

        $result = get_attempt_results::execute((int) $instance->cmid, $attemptid);
        $result = external_api::clean_returnvalue(get_attempt_results::execute_returns(), $result);

        $this->assertEquals(100, $result['attemptgrade']);
    }

    /**
     * Build a two-question activity, enrol a student, and open an attempt.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: int}
     */
    private function create_two_question_attempt(): array {
        $question = (object) [
            'text' => '<p>Question</p>',
            'correctfeedback' => '',
            'partiallycorrectfeedback' => '',
            'incorrectfeedback' => '',
            'answers' => [
                (object) ['text' => '<p>Correct</p>', 'iscorrect' => 1],
                (object) ['text' => '<p>Wrong</p>', 'iscorrect' => 0],
            ],
        ];
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');
        $instance = $generator->create_instance([
            'course' => $course->id,
            'questions' => json_encode([$question, $question]),
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $quiz = new simplequiz((int) $instance->cmid);
        $attemptid = $quiz->get_or_create_open_attempt((int) $student->id);

        return [$instance, $student, $attemptid];
    }

    /**
     * Read the stored answers of an attempt.
     *
     * @param int $attemptid Attempt data row id.
     * @return array
     */
    private function stored_answers(int $attemptid): array {
        global $DB;
        return json_decode($DB->get_field('simplequiz2_attempt_data', 'answers', ['id' => $attemptid]), true);
    }

    /**
     * A question cannot be answered twice in the same attempt, and the first result is kept.
     *
     * @covers \mod_simplequiz2\external\check_question::execute
     * @covers \mod_simplequiz2\simplequiz::add_attempt_answer
     */
    public function test_question_cannot_be_answered_twice(): void {
        [$instance, , $attemptid] = $this->create_two_question_attempt();

        check_question::execute((int) $instance->cmid, 0, $attemptid, '1');

        try {
            check_question::execute((int) $instance->cmid, 0, $attemptid, '0');
            $this->fail('A second answer to the same question must be rejected');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_alreadyanswered', $e->errorcode);
        }

        $this->assertSame([0 => false], $this->stored_answers($attemptid));
    }

    /**
     * Questions must be answered in order.
     *
     * @covers \mod_simplequiz2\simplequiz::add_attempt_answer
     */
    public function test_question_out_of_order_is_rejected(): void {
        [$instance, , $attemptid] = $this->create_two_question_attempt();

        try {
            check_question::execute((int) $instance->cmid, 1, $attemptid, '0');
            $this->fail('Answering the last question first must be rejected');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_questionoutoforder', $e->errorcode);
        }

        $this->assertSame([], $this->stored_answers($attemptid));
    }

    /**
     * An out-of-range question index is rejected and nothing is stored.
     *
     * @covers \mod_simplequiz2\external\check_question::execute
     */
    public function test_unknown_question_is_rejected(): void {
        [$instance, , $attemptid] = $this->create_two_question_attempt();

        try {
            check_question::execute((int) $instance->cmid, 99, $attemptid, '0');
            $this->fail('An unknown question index must be rejected');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid question index', $e->getMessage());
        }

        $this->assertSame([], $this->stored_answers($attemptid));
    }

    /**
     * A finished attempt accepts no more answers and is counted once.
     *
     * @covers \mod_simplequiz2\simplequiz::add_attempt_answer
     */
    public function test_finished_attempt_is_counted_once(): void {
        global $DB;

        [$instance, $student, $attemptid] = $this->create_attempt_for_student();

        check_question::execute((int) $instance->cmid, 0, $attemptid, '1');
        $this->assertNotEmpty($DB->get_field('simplequiz2_attempt_data', 'timefinished', ['id' => $attemptid]));

        for ($i = 0; $i < 3; $i++) {
            try {
                check_question::execute((int) $instance->cmid, 0, $attemptid, '0');
                $this->fail('A finished attempt must not accept answers');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_attemptfinished', $e->errorcode);
            }
        }

        $summary = $DB->get_record('simplequiz2_attempts', ['cmid' => $instance->cmid, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertEquals(1, $summary->cntattempt);
        $this->assertEquals(0, $summary->completed);

        $result = get_attempt_results::execute((int) $instance->cmid, $attemptid);
        $this->assertEquals(0, $result['attemptgrade']);
    }

    /**
     * Viewing the activity again reuses the empty open attempt instead of adding rows.
     *
     * @covers \mod_simplequiz2\simplequiz::get_or_create_open_attempt
     */
    public function test_open_attempt_is_reused_until_answered(): void {
        global $DB;

        [$instance, $student, $attemptid] = $this->create_two_question_attempt();
        $quiz = new simplequiz((int) $instance->cmid);

        $this->assertSame($attemptid, $quiz->get_or_create_open_attempt((int) $student->id));
        $this->assertSame($attemptid, $quiz->get_or_create_open_attempt((int) $student->id));
        $this->assertSame(1, $DB->count_records('simplequiz2_attempt_data', ['cmid' => $instance->cmid]));

        check_question::execute((int) $instance->cmid, 0, $attemptid, '0');

        $next = $quiz->get_or_create_open_attempt((int) $student->id);
        $this->assertNotSame($attemptid, $next);
        $this->assertSame(2, $DB->count_records('simplequiz2_attempt_data', ['cmid' => $instance->cmid]));
    }
}
