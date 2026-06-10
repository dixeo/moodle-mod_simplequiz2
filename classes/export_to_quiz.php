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

/**
 * Class to export simplequiz to quiz
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

use coding_exception;
use context_module;
use core_completion\manager;
use core_question\local\bank\question_edit_contexts;
use dml_exception;
use file_exception;
use moodle_exception;
use qtype_multichoice;
use stdClass;
use stored_file_creation_exception;
use function add_moduleinfo;
use function get_course;
use function question_make_default_categories;
use function quiz_add_quiz_question;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Builds a standard quiz activity from a simplequiz2 instance.
 */
class export_to_quiz {
    /**
     * @var \stdClass Course module row of the source activity.
     */
    private $oldcm;

    /**
     * @var \stdClass Source simplequiz2 instance row.
     */
    private $oldmod;

    /**
     * @var \stdClass|false Grade item for the source activity if any.
     */
    private $oldgradeitem;

    /**
     * @var array File lists keyed by area (intro, data).
     */
    private $oldfiles;

    /**
     * @var \context_module Context of the new quiz.
     */
    private $quizcontext;

    /**
     * @var int Default question category id for the new quiz.
     */
    private $categoryid;

    /**
     * @var \stdClass New quiz instance row.
     */
    private $quiz;

    /**
     * @var \stdClass Course module row of the new quiz.
     */
    private $quizcm;

    /**
     * Export constructor
     *
     * @param int $cmid
     * @throws coding_exception
     * @throws dml_exception
     */
    public function __construct($cmid) {
        global $DB;

        // Get simplequiz activity data to prepare export.
        $this->oldcm = $DB->get_record('course_modules', ['id' => $cmid]);
        $modtype = $DB->get_record('modules', ['id' => $this->oldcm->module]);
        $this->oldmod = $DB->get_record($modtype->name, ['id' => $this->oldcm->instance]);
        $this->oldgradeitem = $DB->get_record('grade_items', [
            'itemmodule' => $modtype->name,
            'iteminstance' => $this->oldmod->id,
        ]);

        // Load old activity files.
        $fs = get_file_storage();
        $oldcontext = context_module::instance($this->oldcm->id);
        $this->oldfiles['intro'] = $fs->get_area_files($oldcontext->id, 'mod_simplequiz2', 'intro');
        $this->oldfiles['data'] = $fs->get_area_files($oldcontext->id, 'mod_simplequiz2', 'data');
    }

    /**
     * Function to call to execute export
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function export_to_quiz() {
        global $DB, $CFG;

        // Prepare quiz.
        $quizid = $this->create_quiz($this->oldmod, $this->oldcm);

        $this->quizcm = $DB->get_record_sql('
            SELECT cm.*
            FROM {course_modules} cm
            JOIN {modules} m ON cm.module = m.id
            WHERE cm.instance = :quizinstance AND m.name = "quiz"
        ', ['quizinstance' => $quizid]);

        $this->quiz = $DB->get_record('quiz', ['id' => $quizid]);
        $this->quizcontext = context_module::instance($this->quizcm->id);

        // Duplicate intro files in new activity.
        $fs = get_file_storage();
        foreach ($this->oldfiles['intro'] as $file) {
            if ($file->get_filesize() == 0) {
                continue;
            }

            $fileinfo = [
                'contextid' => $this->quizcontext->id,
                'component' => 'mod_quiz',
                'filearea' => 'intro',
                'itemid' => 0,
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ];
            $fs->create_file_from_storedfile($fileinfo, $file->get_id());
        }

        // Create question and add it to the quiz.
        // Get cm questions context and category.
        $contexts = new question_edit_contexts($this->quizcontext);
        $this->categoryid = question_make_default_categories([$contexts->lowest()])->id;

        // Loop on simplequiz question to create real moodle question.
        $questions = (array) json_decode($this->oldmod->questions);
        foreach ($questions as $questionorder => $questiondata) {
            if (is_array($questiondata)) {
                $questiondata = (object) $questiondata;
            }
            $questiondata = simplequiz2_normalize_question($questiondata);

            $newquestion = $this->create_question($questiondata, $questionorder);

            // Add question to new quiz with grade equal to grade of activity divided by number of questions.
            $questiongrade = isset($this->oldgradeitem) ? $this->oldgradeitem->grademax / count($questions) : null;
            quiz_add_quiz_question($newquestion->id, $this->quiz, 0, $questiongrade);
        }

        // Update sumgrade of quiz to avoid grade error.
        if ((int)$CFG->branch < 403) {
            quiz_update_sumgrades($this->quiz);
        } else {
            \mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->recompute_quiz_sumgrades();
        }

        rebuild_course_cache($this->oldcm->course);
    }

    /**
     * Create a quiz
     *
     * @param stdClass $oldmod
     * @param stdClass $oldcm
     * @return int instance id
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function create_quiz($oldmod, $oldcm) {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $oldcm->section]);
        $quizmodule = $DB->get_record('modules', ['name' => 'quiz']);
        $course = get_course($oldcm->course);

        // Data to mimic moodle form in creation function.
        $quiz = [
            'name' => $oldmod->name,
            'coursemodule' => 0,
            'showdescription' => 0,
            'timeopen' => 0,
            'timeclose' => 0,
            'timelimit' => 0,
            'overduehandling' => 'autosubmit',
            'graceperiod' => 0,
            'grademax' => isset($this->oldgradeitem) ? $this->oldgradeitem->grademax : 0,
            'gradecat' => isset($this->oldgradeitem) ? $this->oldgradeitem->categoryid : 0,
            'gradepass' => isset($this->oldgradeitem) ? $this->oldgradeitem->gradepass : 0,
            'grade' => isset($this->oldgradeitem) ? $this->oldgradeitem->grademax : 0,
            'sumgrade' => 1.0,
            'attempts' => 0,
            'grademethod' => 1,
            'navmethod' => 'sequential',
            'shuffleanswers' => 1,
            'preferredbehaviour' => 'deferredfeedback',
            'canredoquestions' => 0,
            'attemptonlast' => 0,
            'showuserpicture' => 0,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'showblocks' => 0,
            'seb_requiresafeexambrowser' => 0,
            'filemanager_sebconfigfile' => 763472371,
            'seb_showsebdownloadlink' => 1,
            'seb_linkquitseb' => '',
            'seb_userconfirmquit' => 1,
            'seb_allowuserquitseb' => 1,
            'seb_quitpassword' => '',
            'seb_allowreloadinexam' => 1,
            'seb_showsebtaskbar' => 1,
            'seb_showreloadbutton' => 1,
            'seb_showtime' => 1,
            'seb_showkeyboardlayout' => 1,
            'seb_showwificontrol' => 0,
            'seb_enableaudiocontrol' => 0,
            'seb_muteonstartup' => 0,
            'seb_allowspellchecking' => 0,
            'seb_activateurlfiltering' => 0,
            'seb_filterembeddedcontent' => 0,
            'seb_expressionsallowed' => '',
            'seb_regexallowed' => '',
            'seb_expressionsblocked' => '',
            'seb_regexblocked' => '',
            'seb_allowedbrowserexamkeys' => '',
            'subnet' => '',
            'delay1' => 0,
            'delay2' => 0,
            'browsersecurity' => '-',
            'boundary_repeats' => 1,
            'feedbacktext' => [
                [
                    'text' => '',
                    'format' => 1,
                    'itemid' => 0,
                ],
                [
                    'text' => '',
                    'format' => 1,
                    'itemid' => 0,
                ],
            ],
            'feedbackboundaries' => [

            ],
            'visible' => $oldcm->visible,
            'visibleoncoursepage' => $oldcm->visibleoncoursepage,
            'cmidnumber' => '',
            'groupmode' => $oldcm->groupmode,
            'groupingid' => $oldcm->groupingid,
            'availabilityconditionsjson' => '',
            'tags' => [],
            'course' => $oldmod->course,
            'section' => $section->section,
            'module' => $quizmodule->id,
            'modulename' => 'quiz',
            'add' => 'quiz',
            'update' => 0,
            'return' => 0,
            'sr' => 0,
            'competencies' => [],
            'competency_rule' => 0,
            'conditiongradegroup' => [],
            'conditionfieldgroup' => [],
            'intro' => $oldmod->intro,
            'introformat' => $oldmod->introformat,
            'timecreated' => time(),
            'timemodified' => time(),
            'quizpassword' => '',
            'feedbackboundarycount' => 0,
            'reviewattempt' => 69632,
            'reviewcorrectness' => 4096,
            'reviewmarks' => 4096,
            'reviewspecificfeedback' => 0,
            'reviewgeneralfeedback' => 0,
            'reviewrightanswer' => 0,
            'reviewoverallfeedback' => 0,
            'questionsperpage' => 1,
        ];

        // ELEA_RQM-635: set default completion from course defaults.
        $defaultcompletion = manager::get_default_completion($course, $quizmodule);
        foreach ($defaultcompletion as $field => $value) {
            if ($field == 'modids') {
                continue;
            }

            $quiz[$field] = $value;
        }

        $quizinfo = add_moduleinfo((object) $quiz, $course);

        // Move new activity after old activity.
        $sectionactivities = explode(',', $section->sequence);
        $newsectionactivities = [];
        foreach ($sectionactivities as $cmid) {
            $newsectionactivities[] = $cmid;
            if ($cmid == $this->oldcm->id) {
                $newsectionactivities[] = $quizinfo->coursemodule;
            }
        }
        $newsection = (object) [
            'id' => $section->id,
            'sequence' => implode(',', $newsectionactivities),
        ];
        $DB->update_record('course_sections', $newsection);

        // These params aren't updated with add_moduleinfo.
        $record = (object) [
            'id' => $quizinfo->instance,
            'reviewattempt' => 69632,
            'reviewcorrectness' => 4096,
            'reviewmarks' => 4096,
            'reviewspecificfeedback' => 0,
            'reviewgeneralfeedback' => 0,
            'reviewrightanswer' => 0,
            'reviewoverallfeedback' => 0,
        ];
        $DB->update_record('quiz', $record);

        // Update grade item to display grade in percent by default.
        $gradeitem = $DB->get_record('grade_items', [
            'itemmodule' => 'quiz',
            'iteminstance' => $quizinfo->instance,
        ]);
        if ($gradeitem) {
            $DB->update_record('grade_items', [
                'id' => $gradeitem->id,
                'display' => GRADE_DISPLAY_TYPE_PERCENTAGE,
            ]);
        }

        return $quizinfo->instance;
    }

    /**
     * Create question with question text, one correct answer text and multiple other wrong answers
     * in new quiz
     *
     * @param stdClass $questiondata Question fields for export (text, answers, combined feedback).
     * @param int $questionorder Zero-based question index in the source activity.
     * @return object
     * @throws coding_exception
     * @throws dml_exception
     */
    private function create_question($questiondata, int $questionorder) {
        global $USER, $DB;

        // Prepare question params.
        $qtype = new qtype_multichoice();

        $question = (object) [
            'category' => $this->categoryid,
            'qtype' => 'multichoice',
            'createdby' => $USER->id,
            'contextid' => $this->quizcontext,
        ];

        $answers = [];
        $fractions = [];

        $answersdata = $questiondata->answers;

        // Count correct answers for fraction grade.
        $nbcorrectanswers = 0;
        foreach ($answersdata as $answer) {
            if ($answer->iscorrect) {
                $nbcorrectanswers++;
            }
        }

        // Prepare answers.
        foreach ($answersdata as $answer) {
            $answers[] = [
                'text' => $answer->text,
                'format' => 1,
                'itemid' => 0,
            ];

            if ($answer->iscorrect) {
                $fractions[] = 1.0 / $nbcorrectanswers;
            } else {
                $fractions[] = -0.5;
            }
        }

        // One feedback for each answer.
        $feedbacks = [];
        foreach ($answers as $answer) {
            $feedbacks[] = [
                'text' => '',
                'format' => 1,
                'itemid' => 0,
            ];
        }

        // Mimic question form to add it through native functions.
        $form = (object) [
            'category' => $this->categoryid . ',' . $this->quizcontext->id,
            'name' => 'simple question',
            'questiontext' => [
                'text' => '<p dir="ltr" style="text-align: left;">' . $questiondata->text . '</p>',
                'format' => 1,
                'itemid' => 0,
            ],
            'defaultmark' => 1,
            'generalfeedback' => [
                'text' => '',
                'format' => 1,
                'itemid' => 0,
            ],
            'idnumber' => '',
            'single' => 0,
            'shuffleanswers' => 1,
            'answernumbering' => 'abc',
            'showstandardinstruction' => 1,
            'noanswers' => 3,
            'answer' => $answers,
            'fraction' => $fractions,
            'feedback' => $feedbacks,
            'correctfeedback' => $this->map_combined_feedback_field(
                $questiondata,
                'correctfeedback',
                'correctfeedbackdefault'
            ),
            'partiallycorrectfeedback' => $this->map_combined_feedback_field(
                $questiondata,
                'partiallycorrectfeedback',
                'partiallycorrectfeedbackdefault'
            ),
            'incorrectfeedback' => $this->map_combined_feedback_field(
                $questiondata,
                'incorrectfeedback',
                'incorrectfeedbackdefault'
            ),
            'shownumcorrect' => 1,
            'penalty' => 0,
            'numhints' => 0,
            'hint' => [],

            'id' => 0,
            'cmid' => $this->quizcm->id,
            'courseid' => $this->quizcm->course,
            'qtype' => 'multichoice',
        ];

        $newquestion = $qtype->save_question($question, $form);

        // Duplicate simplequiz files into question and answers file area.
        $this->duplicate_question_files_from_text($newquestion->id, $newquestion->questiontext, 'questiontext');
        $answerrecords = $DB->get_records('question_answers', ['question' => $newquestion->id]);
        foreach ($answerrecords as $answer) {
            $this->duplicate_question_files_from_text($answer->id, $answer->answer, 'answer');
        }

        $this->duplicate_combined_feedback_files($newquestion->id, $questiondata, $questionorder);

        return $newquestion;
    }

    /**
     * Build a Moodle combined-feedback editor field from SimpleQuiz2 stored HTML.
     *
     * @param stdClass $questiondata Source question.
     * @param string $field Property name on the question object.
     * @param string $defaultlangkey Moodle core lang string key used when the field is empty.
     * @return array
     */
    private function map_combined_feedback_field(stdClass $questiondata, string $field, string $defaultlangkey): array {
        $question = simplequiz2_normalize_question($questiondata);
        $text = $question->$field ?? '';
        if (\mod_simplequiz2\util\editor_content::is_empty($text)) {
            $text = get_string($defaultlangkey, 'question');
        }

        return [
            'text' => $text,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
    }

    /**
     * Copy embedded files from SimpleQuiz2 combined feedback into the question bank.
     *
     * @param int $newquestionid Saved question id.
     * @param stdClass $questiondata Source question.
     * @param int $questionorder Zero-based question index.
     * @return void
     */
    private function duplicate_combined_feedback_files(int $newquestionid, stdClass $questiondata, int $questionorder): void {
        $question = simplequiz2_normalize_question($questiondata);
        $feedbackareas = [
            'correctfeedback' => 'correctfeedback',
            'partiallycorrectfeedback' => 'partiallycorrectfeedback',
            'incorrectfeedback' => 'incorrectfeedback',
        ];

        foreach ($feedbackareas as $field => $filearea) {
            $text = $question->$field ?? '';
            if (!\mod_simplequiz2\util\editor_content::is_empty($text)) {
                $this->duplicate_question_files_from_text($newquestionid, $text, $filearea);
            }
        }
    }

    /**
     * Find all files mentioned in $text, look for them in simplequiz activity and duplicate it in correct question area
     *
     * @param int $itemid Question or answer id in the question bank.
     * @param string $text HTML text possibly containing @@PLUGINFILE@@ references.
     * @param string $filearea Target question file area (e.g. questiontext or answer).
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    private function duplicate_question_files_from_text($itemid, $text, $filearea) {
        preg_match_all('/@@PLUGINFILE@@(.*?)"/', $text, $matches);

        if (!$matches[1]) {
            return;
        }

        $fs = get_file_storage();

        // Looking for files matching text.
        foreach ($matches[1] as $match) {
            // Looking for simplequiz file that match the question file.
            foreach ($this->oldfiles['data'] as $file) {
                // Exclude fake files.
                if ($file->get_filesize() == 0) {
                    continue;
                }

                // Check if old file has same path and filename.
                if ($file->get_filepath() . $file->get_filename() != urldecode($match)) {
                    continue;
                }

                // Prepare file data and create it.
                $fileinfo = [
                    'contextid' => $this->quizcontext->id,
                    'component' => 'question',
                    'filearea' => $filearea,
                    'itemid' => $itemid,
                    // Question or answer id.
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ];
                $fs->create_file_from_storedfile($fileinfo, $file->get_id());

                break;
            }
        }
    }
}
