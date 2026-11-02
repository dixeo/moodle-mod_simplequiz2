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
 * Tests for pluginfile access control (SQ2-SEC-002).
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pluginfile_access_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Enrolled users without mod/simplequiz2:view must not load attachments via pluginfile.
     *
     * @covers ::simplequiz2_pluginfile
     */
    public function test_pluginfile_denies_when_view_prohibited(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid);
        $course = get_course($cm->course);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        assign_capability('mod/simplequiz2:view', CAP_PROHIBIT, $studentroleid, $context->id);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_simplequiz2',
            'filearea' => 'data',
            'itemid' => \mod_simplequiz2\util\file_itemid::question_text(0),
            'filepath' => '/',
            'filename' => 'attachment.png',
        ], 'png');

        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        simplequiz2_pluginfile(
            $course,
            $cm,
            $context,
            'data',
            [\mod_simplequiz2\util\file_itemid::question_text(0), 'attachment.png'],
            false
        );
    }

    /**
     * Users with mod/simplequiz2:view pass the capability gate before file resolution.
     *
     * @covers ::simplequiz2_pluginfile
     */
    public function test_pluginfile_allows_view_capability_before_file_lookup(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid);
        $course = get_course($cm->course);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);

        $result = simplequiz2_pluginfile(
            $course,
            $cm,
            $context,
            'data',
            [\mod_simplequiz2\util\file_itemid::question_text(0), 'missing.png'],
            false
        );
        $this->assertFalse($result);
    }

    /**
     * Unknown file areas and item ids outside the question scheme are not served.
     *
     * @covers ::simplequiz2_pluginfile
     */
    public function test_pluginfile_rejects_unknown_area_and_item(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid);
        $fs = get_file_storage();
        foreach (['secret' => 1000, 'data' => 5] as $area => $itemid) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_simplequiz2',
                'filearea' => $area,
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => 'file.png',
            ], 'png');
        }
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $this->assertFalse(simplequiz2_pluginfile($course, $cm, $context, 'secret', [1000, 'file.png'], false));
        $this->assertFalse(simplequiz2_pluginfile($course, $cm, $context, 'data', [5, 'file.png'], false));
    }

    /**
     * Feedback files are only available after the question was answered, or to editors.
     *
     * @covers ::simplequiz2_can_access_data_file
     */
    public function test_feedback_files_require_answered_question(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $cm = get_coursemodule_from_id('simplequiz2', $quiz->cmid);
        $feedbackitemid = \mod_simplequiz2\util\file_itemid::feedback(0, MOD_SIMPLEQUIZ2_FEEDBACK_CORRECT);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $this->assertTrue(simplequiz2_can_access_data_file($cm, $context, $feedbackitemid));

        $this->setUser($student);
        $this->assertTrue(
            simplequiz2_can_access_data_file(
                $cm,
                $context,
                \mod_simplequiz2\util\file_itemid::question_text(0)
            )
        );
        $this->assertFalse(simplequiz2_can_access_data_file($cm, $context, $feedbackitemid));

        $simplequiz = new \mod_simplequiz2\simplequiz((int) $quiz->cmid);
        $attemptid = $simplequiz->get_or_create_open_attempt((int) $student->id);
        $simplequiz->add_attempt_answer($attemptid, 0, true);

        $this->assertTrue(simplequiz2_can_access_data_file($cm, $context, $feedbackitemid));
    }
}
