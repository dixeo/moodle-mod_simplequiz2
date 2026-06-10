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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Unit tests for mod_simplequiz2 lib.php.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {

    /**
     * Set up each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Plugin feature support flags.
     */
    public function test_simplequiz2_supports(): void {
        $this->assertTrue(simplequiz2_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(simplequiz2_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertSame(MOD_PURPOSE_ASSESSMENT, simplequiz2_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(simplequiz2_supports('unknown_feature'));
    }

    /**
     * prepare_question_from_mod_form skips empty TinyMCE questions and answers.
     */
    public function test_prepare_question_from_mod_form_skips_empty_tinymce_markup(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');

        $quiz = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('simplequiz2', $quiz->id, $course->id, false, MUST_EXIST);

        $data = new \stdClass();
        $data->questions0 = [
            'questionorder' => 0,
            'text' => [
                'text' => '<p>Question one</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'answers' => [
                ['text' => '<p>Answer A</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '<p><br></p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '<p>Answer C</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ],
            'correctanswers' => [1, 0, 0, 0, 0],
        ];

        $questions = simplequiz2_prepare_question_from_mod_form($cm->id, $data);

        $this->assertCount(1, $questions);
        $this->assertCount(2, $questions[0]->answers);
        $this->assertStringContainsString('Question one', $questions[0]->text);
    }

    /**
     * prepare_question_from_mod_form keeps multiple non-empty questions in order.
     */
    public function test_prepare_question_from_mod_form_multiple_questions(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');

        $quiz = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('simplequiz2', $quiz->id, $course->id, false, MUST_EXIST);

        $data = new \stdClass();
        $data->questions0 = [
            'questionorder' => 0,
            'text' => [
                'text' => '<p>First question</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'answers' => [
                ['text' => '<p>Yes</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '<p>No</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ],
            'correctanswers' => [1, 0, 0, 0, 0],
        ];
        $data->questions1 = [
            'questionorder' => 1,
            'text' => [
                'text' => '<p>Second question</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'answers' => [
                ['text' => '<p>A</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '<p>B</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ],
            'correctanswers' => [0, 1, 0, 0, 0],
        ];

        $questions = simplequiz2_prepare_question_from_mod_form($cm->id, $data);

        $this->assertCount(2, $questions);
        $this->assertStringContainsString('First question', $questions[0]->text);
        $this->assertStringContainsString('Second question', $questions[1]->text);
        $this->assertCount(2, $questions[0]->answers);
        $this->assertCount(2, $questions[1]->answers);
    }

    /**
     * normalize_question adds empty feedback fields for legacy JSON.
     */
    public function test_normalize_question_adds_empty_feedback(): void {
        $legacy = (object) [
            'text' => '<p>Q</p>',
            'answers' => [],
        ];
        $normalized = simplequiz2_normalize_question($legacy);
        $this->assertSame('', $normalized->correctfeedback);
        $this->assertSame('', $normalized->partiallycorrectfeedback);
        $this->assertSame('', $normalized->incorrectfeedback);
    }

    /**
     * prepare_question_from_mod_form persists feedback fields.
     */
    public function test_prepare_question_from_mod_form_persists_feedback(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');

        $quiz = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('simplequiz2', $quiz->id, $course->id, false, MUST_EXIST);

        $data = new \stdClass();
        $data->questions0 = [
            'questionorder' => 0,
            'text' => [
                'text' => '<p>Question one</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'correctfeedback' => [
                'text' => '<p>Great job!</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'partiallycorrectfeedback' => [
                'text' => '<p>Almost!</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'incorrectfeedback' => [
                'text' => '<p>Not quite.</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'answers' => [
                ['text' => '<p>Answer A</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '<p>Answer B</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
                ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ],
            'correctanswers' => [1, 0, 0, 0, 0],
        ];

        $questions = simplequiz2_prepare_question_from_mod_form($cm->id, $data);

        $this->assertStringContainsString('Great job!', $questions[0]->correctfeedback);
        $this->assertStringContainsString('Almost!', $questions[0]->partiallycorrectfeedback);
        $this->assertStringContainsString('Not quite.', $questions[0]->incorrectfeedback);
    }

    /**
     * get_feedback_for_outcome returns correct branch and empty for legacy questions.
     */
    public function test_get_feedback_for_outcome(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');

        $questionsjson = json_encode([
            (object) [
                'text' => '<p>Q</p>',
                'correctfeedback' => '<p>Yes!</p>',
                'partiallycorrectfeedback' => '<p>Partly.</p>',
                'incorrectfeedback' => '<p>No.</p>',
                'answers' => [
                    (object) ['text' => '<p>A</p>', 'iscorrect' => 1],
                    (object) ['text' => '<p>B</p>', 'iscorrect' => 0],
                ],
            ],
        ]);

        $quiz = $generator->create_instance([
            'course' => $course->id,
            'questions' => $questionsjson,
        ]);
        $cm = get_coursemodule_from_instance('simplequiz2', $quiz->id, $course->id, false, MUST_EXIST);

        $question = simplequiz2_normalize_question(json_decode($questionsjson)[0]);

        $this->assertStringContainsString(
            'Yes!',
            simplequiz2_get_feedback_for_outcome($question, 0, $cm->id, SIMPLE_QUIZ2_FEEDBACK_CORRECT)
        );
        $this->assertStringContainsString(
            'Partly.',
            simplequiz2_get_feedback_for_outcome($question, 0, $cm->id, SIMPLE_QUIZ2_FEEDBACK_PARTIAL)
        );
        $this->assertStringContainsString(
            'No.',
            simplequiz2_get_feedback_for_outcome($question, 0, $cm->id, SIMPLE_QUIZ2_FEEDBACK_INCORRECT)
        );

        $this->assertSame(
            SIMPLE_QUIZ2_FEEDBACK_PARTIAL,
            simplequiz2_feedback_outcome_from_grading(false, true)
        );

        $legacy = simplequiz2_normalize_question((object) [
            'text' => '<p>Legacy</p>',
            'answers' => [],
        ]);
        $this->assertSame(
            '',
            simplequiz2_get_feedback_for_outcome($legacy, 0, $cm->id, SIMPLE_QUIZ2_FEEDBACK_CORRECT)
        );
    }

    /**
     * Course reset form defaults enable attempt deletion.
     */
    public function test_reset_course_form_defaults(): void {
        $course = $this->getDataGenerator()->create_course();
        $defaults = simplequiz2_reset_course_form_defaults($course);
        $this->assertEquals(1, $defaults['reset_simplequiz2_attempts']);
    }
}
