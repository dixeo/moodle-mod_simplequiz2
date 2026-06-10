<?php
/**
 * Unit tests for question_grading_service.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see question_grading_service}.
 */
class question_grading_service_test extends \advanced_testcase {

    /**
     * Build a sample question object.
     *
     * @return \stdClass
     */
    protected function sample_question(): \stdClass {
        return (object) [
            'text' => 'Pick one',
            'correctfeedback' => 'Yes',
            'partiallycorrectfeedback' => 'Partly',
            'incorrectfeedback' => 'No',
            'answers' => [
                (object) ['text' => 'A', 'iscorrect' => 1],
                (object) ['text' => 'B', 'iscorrect' => 0],
            ],
        ];
    }

    /**
     * Fully correct single selection.
     */
    public function test_grade_question_correct(): void {
        $grading = question_grading_service::grade_question($this->sample_question(), '0');
        $this->assertTrue($grading['iscorrect']);
        $this->assertFalse($grading['haspartialcorrect']);
        $this->assertEquals([0 => true], $grading['results']);
    }

    /**
     * Wrong selection only.
     */
    public function test_grade_question_incorrect(): void {
        $grading = question_grading_service::grade_question($this->sample_question(), '1');
        $this->assertFalse($grading['iscorrect']);
        $this->assertFalse($grading['haspartialcorrect']);
        $this->assertEquals([1 => false], $grading['results']);
    }

    /**
     * Empty selection is incorrect.
     */
    public function test_grade_question_empty(): void {
        $grading = question_grading_service::grade_question($this->sample_question(), '');
        $this->assertFalse($grading['iscorrect']);
        $this->assertFalse($grading['haspartialcorrect']);
        $this->assertSame([], $grading['results']);
    }

    /**
     * first_correct_answer_plaintext returns stripped answer text.
     */
    public function test_first_correct_answer_plaintext(): void {
        $text = question_grading_service::first_correct_answer_plaintext($this->sample_question());
        $this->assertSame('A', $text);
    }
}
