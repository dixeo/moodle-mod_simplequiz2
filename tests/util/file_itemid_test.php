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
 * Tests for question file item ids.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_simplequiz2\util\file_itemid
 */
final class file_itemid_test extends \advanced_testcase {
    /**
     * Every editor field of every question has its own item id.
     */
    public function test_itemids_are_unique(): void {
        $ids = [];
        for ($q = 0; $q < MOD_SIMPLEQUIZ2_MAX_QUESTION_NB; $q++) {
            $ids[] = file_itemid::question_text($q);
            for ($a = 0; $a < MOD_SIMPLEQUIZ2_MAX_ANSWER_NB; $a++) {
                $ids[] = file_itemid::answer($q, $a);
            }
            $outcomes = [
                MOD_SIMPLEQUIZ2_FEEDBACK_CORRECT,
                MOD_SIMPLEQUIZ2_FEEDBACK_PARTIAL,
                MOD_SIMPLEQUIZ2_FEEDBACK_INCORRECT,
            ];
            foreach ($outcomes as $outcome) {
                $id = file_itemid::feedback($q, $outcome);
                $this->assertTrue(file_itemid::is_feedback($id));
                $this->assertSame($q, file_itemid::question_order($id));
                $ids[] = $id;
            }
        }

        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertGreaterThanOrEqual(file_itemid::BASE, min($ids));
        $this->assertFalse(file_itemid::is_feedback(file_itemid::question_text(3)));
        $this->assertFalse(file_itemid::is_feedback(file_itemid::answer(3, 4)));
    }

    /**
     * Saving twelve questions keeps the file of question 1 answer 1 and of question 11 text apart.
     *
     * @covers ::simplequiz2_prepare_question_from_mod_form
     */
    public function test_saved_files_do_not_collide(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();

        $data = new \stdClass();
        for ($i = 0; $i < 12; $i++) {
            $questiondraft = file_get_unused_draft_itemid();
            $answerdraft = file_get_unused_draft_itemid();
            $questiontext = '<p>Question ' . $i . '</p>';
            $answertext = '<p>Answer</p>';
            if ($i === 10) {
                $this->create_draft_file($usercontext->id, $questiondraft, 'question11.png');
                $questiontext .= '<img src="@@PLUGINFILE@@/question11.png" alt="">';
            }
            if ($i === 0) {
                $this->create_draft_file($usercontext->id, $answerdraft, 'answer1.png');
                $answertext .= '<img src="@@PLUGINFILE@@/answer1.png" alt="">';
            }
            $data->{'questions' . $i} = [
                'questionorder' => $i,
                'text' => ['text' => $questiontext, 'format' => FORMAT_HTML, 'itemid' => $questiondraft],
                'answers' => [
                    ['text' => $answertext, 'format' => FORMAT_HTML, 'itemid' => $answerdraft],
                    ['text' => '<p>Other</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
                ],
                'correctanswers' => [1, 0],
            ];
        }

        simplequiz2_prepare_question_from_mod_form($quiz->cmid, $data);

        $this->assertNotFalse(
            $fs->get_file(
                $context->id,
                'mod_simplequiz2',
                'data',
                file_itemid::answer(0, 0),
                '/',
                'answer1.png'
            )
        );
        $this->assertNotFalse(
            $fs->get_file(
                $context->id,
                'mod_simplequiz2',
                'data',
                file_itemid::question_text(10),
                '/',
                'question11.png'
            )
        );
        $this->assertFalse(
            $fs->get_file(
                $context->id,
                'mod_simplequiz2',
                'data',
                file_itemid::question_text(10),
                '/',
                'answer1.png'
            )
        );
    }

    /**
     * Files stored under the overlapping item ids are copied to every field that references them.
     */
    public function test_migrate_legacy_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('simplequiz2', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $fs = get_file_storage();

        // Item 11 held both question 11 text and question 1 answer 1; item 16 question 1 correct feedback.
        $this->create_area_file($context->id, 11, 'shared.png');
        $this->create_area_file($context->id, 11, 'q11.png');
        $this->create_area_file($context->id, 16, 'feedback.png');

        $questions = [];
        for ($i = 0; $i < 11; $i++) {
            $questions[$i] = ['text' => '<p>Q</p>', 'answers' => [['text' => '<p>A</p>', 'iscorrect' => 1]]];
        }
        $questions[0]['answers'][0]['text'] = '<img src="@@PLUGINFILE@@/shared.png">';
        $questions[0]['correctfeedback'] = '<img src="@@PLUGINFILE@@/feedback.png">';
        $questions[10]['text'] = '<img src="@@PLUGINFILE@@/shared.png"><img src="@@PLUGINFILE@@/q11.png">';

        file_itemid::migrate_legacy_files($context, $questions);
        // Running again is harmless.
        file_itemid::migrate_legacy_files($context, $questions);

        $exists = fn(int $itemid, string $name): bool =>
            (bool) $fs->get_file($context->id, 'mod_simplequiz2', 'data', $itemid, '/', $name);

        $this->assertTrue($exists(file_itemid::answer(0, 0), 'shared.png'));
        $this->assertFalse($exists(file_itemid::answer(0, 0), 'q11.png'));
        $this->assertTrue($exists(file_itemid::question_text(10), 'shared.png'));
        $this->assertTrue($exists(file_itemid::question_text(10), 'q11.png'));
        $this->assertTrue($exists(file_itemid::feedback(0, MOD_SIMPLEQUIZ2_FEEDBACK_CORRECT), 'feedback.png'));
        $this->assertFalse($exists(11, 'shared.png'));
        $this->assertFalse($exists(16, 'feedback.png'));
    }

    /**
     * Create a file in a user draft area.
     *
     * @param int $usercontextid User context id.
     * @param int $draftitemid Draft item id.
     * @param string $filename File name.
     */
    private function create_draft_file(int $usercontextid, int $draftitemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $usercontextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $filename);
    }

    /**
     * Create a file in the activity data area.
     *
     * @param int $contextid Module context id.
     * @param int $itemid Item id.
     * @param string $filename File name.
     */
    private function create_area_file(int $contextid, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'mod_simplequiz2',
            'filearea' => 'data',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $filename);
    }
}
