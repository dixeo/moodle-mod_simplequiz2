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

/**
 * Tests for converting an activity into a quiz with convert.php.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class convert_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A teacher who may add quizzes but not update the course cannot convert in a single activity course.
     */
    public function test_singleactivity_conversion_requires_course_update(): void {
        global $CFG, $DB;

        $course = $this->getDataGenerator()->create_course(['format' => 'singleactivity', 'activitytype' => 'simplequiz2']);
        $simplequiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:update', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        $this->setUser($teacher);

        $_GET = ['cmid' => $simplequiz->cmid];
        $_POST = [];

        try {
            include($CFG->dirroot . '/mod/simplequiz2/convert.php');
            $this->fail('Expected required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->assertSame('singleactivity', $DB->get_field('course', 'format', ['id' => $course->id]));
        $this->assertSame(0, $this->count_quiz_modules($course->id));
    }

    /**
     * A teacher who cannot add questions cannot convert.
     */
    public function test_conversion_requires_question_add(): void {
        global $CFG, $DB;

        $course = $this->getDataGenerator()->create_course();
        $simplequiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/question:add', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        $this->setUser($teacher);

        $this->assertFalse(simplequiz2_can_convert_to_quiz(get_course($course->id)));

        $_GET = ['cmid' => $simplequiz->cmid];
        $_POST = [];

        $this->expectException(\required_capability_exception::class);
        include($CFG->dirroot . '/mod/simplequiz2/convert.php');
    }

    /**
     * A conversion that fails part way leaves the course format, sections and modules unchanged.
     */
    public function test_failed_conversion_is_rolled_back(): void {
        global $CFG, $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'singleactivity', 'activitytype' => 'simplequiz2']);
        // A multiple choice question needs two answers, so saving it in the question bank fails.
        $questions = json_encode([(object) [
            'text' => '<p>Only one answer</p>',
            'answers' => [(object) ['text' => '<p>A</p>', 'iscorrect' => 1]],
        ]]);
        $simplequiz = $this->getDataGenerator()->create_module('simplequiz2', [
            'course' => $course->id,
            'questions' => $questions,
        ]);
        $sectionsbefore = $DB->count_records('course_sections', ['course' => $course->id]);

        $_GET = ['cmid' => $simplequiz->cmid, 'confirm' => 1];
        $_POST = ['sesskey' => sesskey()];

        try {
            include($CFG->dirroot . '/mod/simplequiz2/convert.php');
            $this->fail('Expected the failure redirect');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
        $this->assertDebuggingCalled();

        $this->assertSame('singleactivity', $DB->get_field('course', 'format', ['id' => $course->id]));
        $this->assertSame($sectionsbefore, $DB->count_records('course_sections', ['course' => $course->id]));
        $this->assertSame(0, $this->count_quiz_modules($course->id));
        $this->assertFalse($DB->is_transaction_started());
    }

    /**
     * A successful conversion in a single activity course switches the format and adds the quiz.
     */
    public function test_singleactivity_conversion(): void {
        global $CFG, $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'singleactivity', 'activitytype' => 'simplequiz2']);
        $simplequiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);

        $_GET = ['cmid' => $simplequiz->cmid, 'confirm' => 1];
        $_POST = ['sesskey' => sesskey()];

        try {
            include($CFG->dirroot . '/mod/simplequiz2/convert.php');
            $this->fail('Expected the success redirect');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }

        $this->assertSame('topics', $DB->get_field('course', 'format', ['id' => $course->id]));
        $this->assertSame(1, $this->count_quiz_modules($course->id));
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
