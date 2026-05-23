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
 * Mod edit form definition
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/simplequiz2/classes/database_interface.php');

/**
 * Module instance settings form
 */
class mod_simplequiz2_mod_form extends moodleform_mod {
    /**
     * @var \mod_simplequiz2\database_interface
     */
    private $db;

    /**
     * @var \stdClass|false Current instance row when editing.
     */
    private $simplequiz;

    /**
     * Defines forms elements
     */
    public function definition(): void {
        global $CFG, $PAGE;

        $mform = $this->_form;

        // Prepare DB interface.
        $this->db = \mod_simplequiz2\database_interface::get_instance();

        // Fetch current simplequiz.
        if ($this->_instance) {
            $this->simplequiz = $this->db->get_simplequiz_by_id($this->_instance);
        }

        // Add the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('name'), [
            'size' => '64',
        ]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Add the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        // Add simplequiz questions fields and buttons.
        $this->define_questions_fields();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        // Js to add/remove questions (TinyMCE base options must run after define_questions_fields()).
        $this->export_tiny_base_options_for_js();
        $PAGE->requires->js_call_amd('mod_simplequiz2/edit', 'init');
    }

    /**
     * Pass TinyMCE base options to AMD so deferred editors can be initialised when a question is shown.
     */
    private function export_tiny_base_options_for_js(): void {
        global $PAGE;

        $pref = editors_get_preferred_editor();
        if (!$pref instanceof \editor_tiny\editor) {
            return;
        }

        $siteconfig = get_config('editor_tiny');
        $manager = new \editor_tiny\manager();
        $context = $PAGE->context;

        // Registers default TinyMCE config for the page (also runs during editor element render).
        \editor_tiny\editor::set_default_configuration($manager);

        $config = (object) [
            'css' => $PAGE->theme->editor_css_url()->out(false),
            'context' => $context->id,
            'plugins' => $manager->get_plugin_configuration($context, [], [], $pref),
            'branding' => property_exists($siteconfig, 'branding') ? !empty($siteconfig->branding) : true,
            'extended_valid_elements' => $siteconfig->extended_valid_elements ?? 'script[*],p[*],i[*]',
        ];

        $configoptions = json_encode(convert_to_array($config));

        $inlinejs = <<<EOF
            require(['mod_simplequiz2/editor_helpers'], (EditorHelpers) => {
                EditorHelpers.setTinyBaseOptions({$configoptions});
            });
        EOF;

        $PAGE->requires->js_amd_inline($inlinejs);
    }

    /**
     * Add repeatable question and answer fields to the form.
     *
     * @throws coding_exception
     */
    private function define_questions_fields() {

        // Fetch the existing questions.
        if ($this->_instance) {
            $questionsdata = (array) json_decode($this->simplequiz->questions);
        }

        $mform = $this->_form;

        $lastquestionwithcontent = 'question_header_0';

        for ($i = 0; $i < SIMPLE_QUIZ2_MAX_QUESTION_NB; $i++) {
            // Check if the question already has content.
            $hascontent   = false;
            $questiontext = '';
            if (isset($questionsdata[$i])) {
                $hascontent              = true;
                $questiontext            = $questionsdata[$i]->text;
                $lastquestionwithcontent = 'question_header_' . $i;
            }

            // Header with questiontext.
            $mform->addElement('header', 'question_header_' . $i, get_string('formquestiontitle', 'simplequiz2', $i + 1));

            $mform->addElement('html', "<div class='header-questiontext' data-questionid='" . $i . "'> " .
                                       strip_tags($questiontext) . "</div>", '');

            // Header warnings.
            $mform->addElement('html', "
                <div class='error_not_enough_answers alert alert-danger'>" . get_string('notenoughanswerserror', 'simplequiz2') . "
                </div>
                <div class='error_no_right_answer alert alert-danger' >" . get_string('norightanswererror', 'simplequiz2') . "
                </div>
            ");

            // Header add and delete buttons.
            if ($i > 0) {
                $mform->addElement('html', '<input type="button" value="' . get_string('deletequestion', 'simplequiz2') . '"
                class="btn btn-primary delete-simplequestion header-btn"
                data-questionid="' . $i . '">');
            }

            $mform->addElement('html', '<input type="button" value="' . get_string('addquestion', 'simplequiz2') . '"
                class="btn btn-primary add-simplequestion header-btn"
                data-questionorder="' . $i . '"
                data-questionid="' . $i . '">');

            // Add question order container because question can be reorder during edition.
            $mform->addElement('hidden', "questions$i" . "[questionorder]", $i);
            $mform->setType("questions$i" . "[questionorder]", PARAM_INT);

            // Add question text and answer fields (deferred plain textareas for empty hidden slots).
            $usedeferred = ($i > 0 && !$hascontent);
            $classes = 'question-text-editor ';
            $classes .= $hascontent ? ' has-content ' : '';

            if ($usedeferred) {
                $this->add_deferred_editor(
                    $mform,
                    "questions{$i}[text]",
                    get_string('questiontext', 'simplequiz2'),
                    ['rows' => 3, 'class' => $classes],
                    "id_questions{$i}_text"
                );
            } else {
                $mform->addElement(
                    'editor',
                    "questions$i" . "[text]",
                    get_string('questiontext', 'simplequiz2'),
                    [
                        'rows'  => 3,
                        'class' => $classes,
                    ],
                    ['maxfiles' => EDITOR_UNLIMITED_FILES]
                );
                $mform->setType("questions$i" . "[text]", PARAM_RAW);

                if ($hascontent) {
                    $itemid        = $i + 1;
                    $converteddata = $this->create_draft_area("questions$i" . "[text]", $questionsdata[$i]->text, $itemid);
                    $mform->setDefault("questions$i" . "[text]", $converteddata);
                }
            }

            // The text of the first question is required.
            if ($i == 0) {
                $mform->addRule("questions$i" . "[text]", get_string('required'), 'required');
            }

            // Add answers containers.
            for ($j = 0; $j < SIMPLE_QUIZ2_MAX_ANSWER_NB; $j++) {
                $answerfieldname = "questions{$i}[answers][{$j}]";
                if ($usedeferred) {
                    $this->add_deferred_editor(
                        $mform,
                        $answerfieldname,
                        get_string('formanswertitle', 'simplequiz2', $j + 1),
                        ['rows' => 1],
                        "id_questions{$i}_answers_{$j}"
                    );
                } else {
                    $mform->addElement(
                        'editor',
                        $answerfieldname,
                        get_string('formanswertitle', 'simplequiz2', $j + 1),
                        ['rows' => 1],
                        ['maxfiles' => EDITOR_UNLIMITED_FILES]
                    );
                    $mform->setType($answerfieldname, PARAM_RAW);

                    if (isset($questionsdata[$i]->answers[$j])) {
                        $itemid        = ($i + 1) . ($j + 1);
                        $converteddata = $this->create_draft_area(
                            $answerfieldname,
                            $questionsdata[$i]->answers[$j]->text,
                            $itemid
                        );
                        $mform->setDefault($answerfieldname, $converteddata);
                    }
                }

                // Define if the answer is correct or not.
                $answerelements[] = $mform->addElement(
                    'advcheckbox',
                    "questions$i" . "[correctanswers][$j]",
                    get_string('iscorrectanswer', 'simplequiz2'),
                    null,
                    ['data-answerid' => $j],
                    [
                        0,
                        1,
                    ]
                );
                $mform->setType("questions$i" . "[correctanswers][$j]", PARAM_BOOL);
                if (isset($questionsdata[$i]->answers[$j])) {
                    $mform->setDefault("questions$i" . "[correctanswers][$j]", $questionsdata[$i]->answers[$j]->iscorrect);
                }
            }

            // Add delete/add question button.
            $buttons   = [];
            $buttons[] = $mform->createElement('html', '<input type="button" value="' .
                                                       get_string('addquestion', 'simplequiz2') . '"
                class="btn btn-primary add-simplequestion"
                data-questionorder="' . $i . '"
                data-questionid="' . $i . '">');

            // Not add "add question" button if this is the first question.
            if ($i > 0) {
                $buttons[] = $mform->createElement('html', '<input type="button" value="' .
                                                           get_string('deletequestion', 'simplequiz2') . '"
               class="btn btn-primary delete-simplequestion" data-questionid="' . $i . '">');
            }

            $mform->addGroup($buttons, "question{$i}_buttons", '', [' '], false);
        }

        // Open last header with content.
        $mform->setExpanded($lastquestionwithcontent, true);
    }

    /**
     * Preprocess form default values before display.
     *
     * @param array $defaultvalues Default values (passed by reference).
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Default for completion.
        $defaultvalues['completionminattemptsenabled'] = !empty($defaultvalues['completionminattempts']) ? 1 : 0;
        if (empty($defaultvalues['completionminattempts'])) {
            $defaultvalues['completionminattempts'] = 1;
        }

        if ($this->_instance == '') {
            $defaultvalues['completionminattemptsenabled'] = 1;
        }
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        // Number of failed attempts before the activity is completed.
        $group   = [];
        $group[] =& $mform->createElement(
            'checkbox',
            'completionminattemptsenabled',
            '',
            get_string('completionminattempts', 'simplequiz2')
        );
        $group[] =& $mform->createElement('text', 'completionminattempts', '', ['size' => 3]);
        $mform->setType('completionminattempts', PARAM_INT);
        $mform->addGroup(
            $group,
            'completionminattemptsgroup',
            get_string('completionminattemptsgroup', 'simplequiz2'),
            [' '],
            false
        );

        $mform->disabledIf('completionminattempts', 'completionminattemptsenabled', 'notchecked');

        return ['completionminattemptsgroup'];
    }

    /**
     * Whether the minimum-attempts completion rule is enabled in submitted data.
     *
     * @param \stdClass|array $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return (!empty($data['completionminattemptsenabled']) && $data['completionminattempts'] != 0);
    }

    /**
     * Prepare draft area with current data
     *
     * @param string $fieldname Form element name for the editor.
     * @param string $text Current HTML content.
     * @param int $itemid File item id in the module file area.
     * @return mixed
     */
    public function create_draft_area($fieldname, $text, $itemid) {

        // Set main datas.
        $draftitemid = file_get_submitted_draft_itemid($fieldname);

        // Prepare a specific object for the HTML form with editor fields.
        $draftarea         = new stdClass();
        $draftarea->format = FORMAT_HTML;

        // Move file to draft area.
        $draftarea->text = file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_simplequiz2',
            'data',
            $itemid,
            null,
            $text
        );

        $draftarea->itemid = $draftitemid;

        return $draftarea;
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        // Turn off completion settings if the checkboxes aren't ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionminattemptsenabled) || !$autocompletion) {
                $data->completionminattempts = 0;
            }
        }
    }

    /**
     * Validate form values before save.
     *
     * @param array $data Submitted data.
     * @param array $files Uploaded files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        if (!empty($data['completionpassgrade'])) {
            $data['gradepass'] = SIMPLE_QUIZ2_GRADE_MAX;
        }

        return parent::validation($data, $files);
    }

    /**
     * Add a plain textarea + format/itemid hiddens matching Moodle editor submission shape.
     * TinyMCE is attached via AMD when the question fieldset is shown.
     *
     * @param MoodleQuickForm $mform Form instance.
     * @param string $basename Field base name (e.g. questions0[text]).
     * @param string $label Field label.
     * @param array $attributes Textarea attributes.
     * @param string $elementid DOM id for the textarea (must match editor_helpers conventions).
     */
    private function add_deferred_editor($mform, string $basename, string $label, array $attributes, string $elementid): void {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        $draftitemid = file_get_unused_draft_itemid();
        $attributes['id'] = $elementid;
        $attributes['class'] = trim(($attributes['class'] ?? '') . ' simplequiz2-deferred-editor');

        $mform->addElement('textarea', "{$basename}[text]", $label, $attributes);
        $mform->setType("{$basename}[text]", PARAM_RAW);
        $mform->addElement('hidden', "{$basename}[format]", FORMAT_HTML);
        $mform->setType("{$basename}[format]", PARAM_INT);
        $mform->addElement('hidden', "{$basename}[itemid]", $draftitemid);
        $mform->setType("{$basename}[itemid]", PARAM_INT);
    }
}
