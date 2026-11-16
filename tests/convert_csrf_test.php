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

namespace mod_simplequiz2;

use mod_simplequiz2\local\quiz_conversion;

/**
 * Tests for the confirmation and session key protection of the quiz conversion (SQ2-SEC-001).
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_simplequiz2\local\quiz_conversion
 */
final class convert_csrf_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The unconfirmed step shows a confirmation and does not convert the activity.
     */
    public function test_convert_confirmation_page_without_database_changes(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $quizcountbefore = $this->count_quiz_modules($course->id);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid, 0, false, MUST_EXIST);

        $output = quiz_conversion::confirmation_html($cm, get_course($course->id));

        $this->assertStringContainsString(get_string('convertconfirm', 'simplequiz2'), $output);
        $this->assertSame($quizcountbefore, $this->count_quiz_modules($course->id));
    }

    /**
     * Confirmed conversion must require a valid session key before any database changes.
     */
    public function test_convert_with_confirm_requires_sesskey(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $quizcountbefore = $this->count_quiz_modules($course->id);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid, 0, false, MUST_EXIST);

        $_GET = [];
        $_POST = [];

        try {
            quiz_conversion::convert_confirmed($cm, get_course($course->id), \context_module::instance($cm->id));
            $this->fail('Expected moodle_exception when sesskey is missing');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingparam', $e->errorcode);
        }

        $this->assertSame($quizcountbefore, $this->count_quiz_modules($course->id));
    }

    /**
     * A failing export returns the error result at once, without logging a conversion or adding a quiz.
     */
    public function test_failed_export_returns_error_without_conversion_event(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        // A multiple choice question needs two answers, so saving it in the question bank fails.
        $questions = json_encode([(object) [
            'text' => '<p>Only one answer</p>',
            'answers' => [(object) ['text' => '<p>A</p>', 'iscorrect' => 1]],
        ]]);
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', [
            'course' => $course->id,
            'questions' => $questions,
        ]);
        $quizcountbefore = $this->count_quiz_modules($course->id);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid, 0, false, MUST_EXIST);

        $_POST = ['sesskey' => sesskey()];
        $sink = $this->redirectEvents();

        [$url, $message, $type] = quiz_conversion::convert_confirmed(
            $cm,
            get_course($course->id),
            \context_module::instance($cm->id)
        );

        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \mod_simplequiz2\event\quiz_conversion_completed;
        });
        $sink->close();
        $this->assertDebuggingCalled();

        $this->assertEquals(new \moodle_url('/course/view.php', ['id' => $course->id]), $url);
        $this->assertSame(get_string('cantconvertcodeerror', 'simplequiz2'), $message);
        $this->assertSame(\core\output\notification::NOTIFY_ERROR, $type);
        $this->assertCount(0, $events);
        $this->assertSame($quizcountbefore, $this->count_quiz_modules($course->id));
    }

    /**
     * Count quiz course modules in a course.
     *
     * @param int $courseid Course id.
     * @return int
     */
    private function count_quiz_modules(int $courseid): int {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        return $DB->count_records('course_modules', [
            'course' => $courseid,
            'module' => $moduleid,
        ]);
    }
}
