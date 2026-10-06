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
     *
     * @covers ::simplequiz2_supports
     */
    public function test_simplequiz2_supports(): void {
        $this->assertTrue(simplequiz2_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(simplequiz2_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertSame(MOD_PURPOSE_ASSESSMENT, simplequiz2_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(simplequiz2_supports('unknown_feature'));
    }

    /**
     * prepare_question_from_mod_form skips empty TinyMCE questions and answers.
     *
     * @covers ::simplequiz2_prepare_question_from_mod_form
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
     *
     * @covers ::simplequiz2_prepare_question_from_mod_form
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
     *
     * @covers ::simplequiz2_normalize_question
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
     *
     * @covers ::simplequiz2_prepare_question_from_mod_form
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
     *
     * @covers ::simplequiz2_get_feedback_for_outcome
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
     *
     * @covers ::simplequiz2_reset_course_form_defaults
     */
    public function test_reset_course_form_defaults(): void {
        $course = $this->getDataGenerator()->create_course();
        $defaults = simplequiz2_reset_course_form_defaults($course);
        $this->assertEquals(1, $defaults['reset_simplequiz2_attempts']);
    }

    /**
     * Author HTML keeps formatting and drops script and event handlers.
     *
     * @covers ::simplequiz2_clean_author_html
     */
    public function test_clean_author_html_strips_script_and_handlers(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $payload = '<p>Safe <strong>text</strong></p>'
            . '<script>document.body.setAttribute("data-simplequiz2-xss","executed")</script>'
            . '<img src="https://example.com/a.png" onerror="document.body.setAttribute(\'data-simplequiz2-xss\',\'executed\')"'
            . ' alt="pic">';

        $cleaned = simplequiz2_clean_author_html($payload, $context);

        $this->assertStringContainsString('Safe', $cleaned);
        $this->assertStringContainsString('<strong>text</strong>', $cleaned);
        $this->assertStringContainsString('https://example.com/a.png', $cleaned);
        $this->assertStringNotContainsString('<script', strtolower($cleaned));
        $this->assertStringNotContainsString('onerror', strtolower($cleaned));
        $this->assertStringNotContainsString('data-simplequiz2-xss', $cleaned);
    }

    /**
     * Stored fields are rewritten to pluginfile URLs and then cleaned.
     *
     * @covers ::simplequiz2_format_stored_html
     * @covers ::simplequiz2_rewrite_pluginfile_urls
     * @covers ::simplequiz2_get_feedback_for_outcome
     */
    public function test_stored_html_rewrites_pluginfile_and_strips_script(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');
        $quiz = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('simplequiz2', $quiz->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $formatted = simplequiz2_format_stored_html(
            '<p>Look</p><img src="@@PLUGINFILE@@/pic.png" alt="pic"><script>bad()</script>',
            $context,
            1
        );
        $this->assertStringContainsString('pluginfile.php', $formatted);
        $this->assertStringContainsString('pic.png', $formatted);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $formatted);
        $this->assertStringNotContainsString('<script', strtolower($formatted));
        $this->assertStringContainsString('<p>Look</p>', $formatted);

        $questions = simplequiz2_rewrite_pluginfile_urls([
            (object) [
                'text' => '<p>Question</p><script>bad()</script>',
                'answers' => [
                    (object) [
                        'text' => '<p onclick="bad()">Answer</p><img src="@@PLUGINFILE@@/ans.png" alt="ans">',
                        'iscorrect' => 1,
                    ],
                ],
                'correctfeedback' => '<p>Yes</p><script>bad()</script>',
                'partiallycorrectfeedback' => '<p onmouseover="bad()">Partly</p>',
                'incorrectfeedback' => '<img src="x" onerror="bad()">No',
            ],
        ], $cm->id);

        $question = $questions[0];
        $this->assertStringContainsString('Question', $question->text);
        $this->assertStringNotContainsString('<script', strtolower($question->text));
        $this->assertStringContainsString('Answer', $question->answers[0]->text);
        $this->assertStringContainsString('pluginfile.php', $question->answers[0]->text);
        $this->assertStringNotContainsString('onclick', strtolower($question->answers[0]->text));
        $this->assertStringContainsString('Yes', $question->correctfeedback);
        $this->assertStringNotContainsString('<script', strtolower($question->correctfeedback));
        $this->assertStringContainsString('Partly', $question->partiallycorrectfeedback);
        $this->assertStringNotContainsString('onmouseover', strtolower($question->partiallycorrectfeedback));
        $this->assertStringContainsString('No', $question->incorrectfeedback);
        $this->assertStringNotContainsString('onerror', strtolower($question->incorrectfeedback));

        $feedback = simplequiz2_get_feedback_for_outcome($question, 0, $cm->id, SIMPLE_QUIZ2_FEEDBACK_CORRECT);
        $this->assertStringContainsString('Yes', $feedback);
        $this->assertStringNotContainsString('<script', strtolower($feedback));
    }

    /**
     * Embed question trees are cleaned without treating them as activity files.
     *
     * @covers ::simplequiz2_clean_questions_html
     */
    public function test_clean_questions_html_strips_script(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $cleaned = simplequiz2_clean_questions_html([
            (object) [
                'text' => '<p>Embed</p><script>bad()</script>',
                'answers' => [
                    (object) ['text' => '<a href="javascript:bad()">Choice</a>', 'iscorrect' => 1],
                ],
                'correctfeedback' => '<p>Ok</p><script>bad()</script>',
                'partiallycorrectfeedback' => '<p>Mid</p>',
                'incorrectfeedback' => '<p onclick="bad()">No</p>',
            ],
        ], $context);

        $question = $cleaned[0];
        $this->assertStringContainsString('Embed', $question->text);
        $this->assertStringNotContainsString('<script', strtolower($question->text));
        $this->assertStringContainsString('Choice', $question->answers[0]->text);
        $this->assertStringNotContainsString('javascript:', strtolower($question->answers[0]->text));
        $this->assertStringContainsString('Ok', $question->correctfeedback);
        $this->assertStringNotContainsString('<script', strtolower($question->correctfeedback));
        $this->assertStringContainsString('Mid', $question->partiallycorrectfeedback);
        $this->assertStringContainsString('No', $question->incorrectfeedback);
        $this->assertStringNotContainsString('onclick', strtolower($question->incorrectfeedback));
    }

    /**
     * Edit-form summary HTML is cleaned, including when no module context is available.
     *
     * @covers \mod_simplequiz2\util\question_summary_context::from_stored_question
     */
    public function test_question_summary_strips_script(): void {
        $summary = \mod_simplequiz2\util\question_summary_context::from_stored_question((object) [
            'text' => '<p>Q</p><script>bad()</script>',
            'answers' => [
                (object) ['text' => '<p onclick="bad()">A</p>', 'iscorrect' => 1],
            ],
            'correctfeedback' => '<p>Yes</p><script>bad()</script>',
            'partiallycorrectfeedback' => '<p onmouseover="bad()">Partly</p>',
            'incorrectfeedback' => '<img src="x" onerror="bad()">No',
        ]);

        $this->assertStringContainsString('Q', $summary['questiontext']);
        $this->assertStringNotContainsString('<script', strtolower($summary['questiontext']));
        $this->assertStringContainsString('A', $summary['answers'][0]['text']);
        $this->assertStringNotContainsString('onclick', strtolower($summary['answers'][0]['text']));
        $feedback = array_column($summary['feedbackitems'], 'text');
        $joined = strtolower(implode(' ', $feedback));
        $this->assertStringNotContainsString('<script', $joined);
        $this->assertStringNotContainsString('onmouseover', $joined);
        $this->assertStringNotContainsString('onerror', $joined);
        $this->assertStringContainsString('Yes', $feedback[0]);
    }

    /**
     * Deferred editors keep the site element list and drop script.
     *
     * @covers ::simplequiz2_editor_extended_valid_elements
     * @covers ::simplequiz2_strip_script_extended_elements
     */
    public function test_editor_extended_valid_elements_drops_script(): void {
        $this->assertSame('p[*],i[*]', simplequiz2_editor_extended_valid_elements(null));
        $this->assertSame('p[*],i[*]', simplequiz2_editor_extended_valid_elements('script[*],p[*],i[*]'));
        $this->assertSame(
            'span[style],i[*]',
            simplequiz2_editor_extended_valid_elements('span[style],script[src|type],i[*]')
        );
        $this->assertSame('noscript[*],p[*]', simplequiz2_editor_extended_valid_elements('noscript[*],p[*]'));
        $this->assertSame('', simplequiz2_editor_extended_valid_elements('script[*]'));
        $this->assertSame('', simplequiz2_editor_extended_valid_elements(''));
    }

    /**
     * A learner who can view the activity cannot author it.
     *
     * @coversNothing
     */
    public function test_student_with_view_cannot_add_instance(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_simplequiz2');
        $quiz = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('simplequiz2', $quiz->id, $course->id, false, MUST_EXIST);

        $this->setUser($student);
        $this->assertTrue(has_capability('mod/simplequiz2:view', \context_module::instance($cm->id)));
        $this->assertFalse(has_capability('mod/simplequiz2:addinstance', \context_course::instance($course->id)));
    }
}
