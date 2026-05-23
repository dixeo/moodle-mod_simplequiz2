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
 * Library of simplequiz module functions needed by Moodle core and other subsystems
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('SIMPLE_QUIZ2_MAX_QUESTION_NB', 25);
define('SIMPLE_QUIZ2_MAX_ANSWER_NB', 5);
define('SIMPLE_QUIZ2_GRADE_MAX', 100);

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/completionlib.php');

/**
 * Indicates API features that the simplequiz supports.
 *
 * @param string $feature
 * @return null|bool
 */
function simplequiz2_supports(string $feature) {
    switch ($feature) {
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the simplequiz.
 *
 * @param MoodleQuickForm $mform Moodle form instance (passed by reference).
 */
function simplequiz2_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'simplequiz2_header', get_string('modulenameplural', 'simplequiz2'));
    $mform->addElement('advcheckbox', 'reset_simplequiz2_attempts', get_string('deletealluserdata', 'simplequiz2'));
}

/**
 * Course reset form defaults.
 *
 * @param object $course
 * @return array
 */
function simplequiz2_reset_course_form_defaults($course) {
    return ['reset_simplequiz2_attempts' => 1];
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * simplequiz attempts for course $data->courseid, if $data->reset_simplequiz_attempts is
 * set and true.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function simplequiz2_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'simplequiz2');
    $status       = [];

    // Delete attempts.
    if (!empty($data->reset_simplequiz2_attempts)) {
        $module = $DB->get_record('modules', ['name' => 'simplequiz2']);

        $DB->delete_records_select(
            'simplequiz2_attempts',
            'cmid IN (SELECT id FROM {course_modules} WHERE course = ? and module = ?)',
            [
                $data->courseid,
                $module->id,
            ]
        );

        $DB->delete_records_select(
            'simplequiz2_attempt_data',
            'cmid IN (SELECT id FROM {course_modules} WHERE course = ? and module = ?)',
            [
                $data->courseid,
                $module->id,
            ]
        );

        $status[] = [
            'component' => $componentstr,
            'item'      => get_string('attemptsdeleted', 'simplequiz2'),
            'error'     => false,
        ];
    }

    return $status;
}

/**
 * Add simplequiz instance.
 *
 * @param object $data
 * @param object $mform
 * @return int new simplequiz instance id
 */
function simplequiz2_add_instance($data, $mform) {
    global $DB;

    // Set data to match with DB fields.
    $data->timecreated = time();

    // Force grade max at 100.
    $data->grade = SIMPLE_QUIZ2_GRADE_MAX;

    // Prepare questions data and store draft files.
    $cmid            = $data->coursemodule;
    $data->questions = json_encode(simplequiz2_prepare_question_from_mod_form($cmid, $data));

    // Insert an instance to have its id.
    $data->id = $DB->insert_record('simplequiz2', $data);

    // Link instance id to course_modules table.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);

    // Update instance with association JSON content.
    $DB->update_record('simplequiz2', $data);

    // Set grade item.
    simplequiz2_after_add_or_update($data);

    // Force grade_item to be in display percent and grade pass at 100.
    $gradeitem = $DB->get_record('grade_items', [
        'itemmodule'   => 'simplequiz2',
        'iteminstance' => $data->id,
    ]);
    if ($gradeitem) {
        $DB->update_record('grade_items', [
            'id'        => $gradeitem->id,
            'gradepass' => 0,
            'display'   => GRADE_DISPLAY_TYPE_PERCENTAGE,
        ]);
    }

    return $data->id;
}

/**
 * Update simplequiz instance.
 *
 * @param object $data
 * @param object $mform
 * @return int new resource instance id
 */
function simplequiz2_update_instance($data, $mform) {
    global $DB;

    // Set data to match with DB fields.
    $data->id           = $data->instance;
    $data->timemodified = time();
    $data->grade        = SIMPLE_QUIZ2_GRADE_MAX;

    // Prepare questions data and store draft files.
    $cmid            = $data->coursemodule;
    $data->questions = json_encode(simplequiz2_prepare_question_from_mod_form($cmid, $data));

    // Update instance with questions JSON content.
    $DB->update_record('simplequiz2', $data);

    // Set grade item.
    simplequiz2_after_add_or_update($data);

    return true;
}

/**
 * Delete simplequiz instance.
 *
 * @param int $id
 * @return bool true
 */
function simplequiz2_delete_instance($id) {
    global $DB;

    $simplequiz = $DB->get_record('simplequiz2', ['id' => $id], '*', MUST_EXIST);
    $cm         = $DB->get_record_sql('
        SELECT cm.*
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        WHERE m.name = "simplequiz2" AND cm.instance = ?
    ', [$id]);

    $events = $DB->get_records('event', [
        'modulename' => 'simplequiz2',
        'instance'   => $simplequiz->id,
    ]);
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // Delete grades.
    simplequiz2_grade_item_delete($simplequiz);

    // Delete attempts.
    $DB->delete_records('simplequiz2_attempts', ['cmid' => $cm->id]);
    $DB->delete_records('simplequiz2_attempt_data', ['cmid' => $cm->id]);

    // We must delete the module record after we delete the grade item.
    $DB->delete_records('simplequiz2', ['id' => $id]);

    return true;
}

/**
 * Add a get_coursemodule_info function in case the activity needs to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function simplequiz2_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields   = 'id, name, intro, introformat, completionminattempts';
    if (!$simplequiz = $DB->get_record('simplequiz2', $dbparams, $fields)) {
        return false;
    }

    $result       = new cached_cm_info();
    $result->name = $simplequiz->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('simplequiz2', $simplequiz, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionminattempts'] = $simplequiz->completionminattempts;
    }

    return $result;
}

/**
 * This function is called at the end of quiz_add_instance
 * and quiz_update_instance, to do the common processing.
 *
 * @param object $simplequiz
 */
function simplequiz2_after_add_or_update($simplequiz) {
    simplequiz2_grade_item_update($simplequiz);
}

/**
 * Update grades in central gradebook
 *
 * @param object $simplequiz the simplequiz settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 * @category grade
 */
function simplequiz2_update_grades($simplequiz, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($simplequiz->grade == 0) {
        simplequiz2_grade_item_update($simplequiz);
    } else if ($userid && $nullifnone) {
        $grade           = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        simplequiz2_grade_item_update($simplequiz, $grade);
    } else {
        simplequiz2_grade_item_update($simplequiz);
    }
}

/**
 * Create or update the grade item for given simplequiz
 *
 * @param object $simplequiz object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 * @category grade
 */
function simplequiz2_grade_item_update($simplequiz, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params             = [];
    $params['itemname'] = $simplequiz->name;
    if (isset($gradeitem->cmidnumber)) {
        $params['idnumber'] = $simplequiz->cmidnumber;
    }

    if ($simplequiz->grade >= 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = SIMPLE_QUIZ2_GRADE_MAX;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades          = null;
    }

    return grade_update('mod/simplequiz2', $simplequiz->course, 'mod', 'simplequiz2', $simplequiz->id, 0, $grades, $params);
}

/**
 * Delete grade item for given quiz
 *
 * @param object $simplequiz object
 * @return object simplequiz
 * @category grade
 */
function simplequiz2_grade_item_delete($simplequiz) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/simplequiz2',
        $simplequiz->course,
        'mod',
        'simplequiz2',
        $simplequiz->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_simplequiz2_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (
        empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC
    ) {
        return [];
    }

    $descriptions = [];
    $rules = $cm->customdata['customcompletionrules'];

    if (!empty($rules['completionminattempts'])) {
        $descriptions[] = get_string('completionminattemptsdesc', 'simplequiz2', $rules['completionminattempts']);
    }

    return $descriptions;
}


/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param stdClass $simplequiz simplequiz object
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @since Moodle 3.0
 */
function simplequiz2_view($simplequiz, $course, $cm, $context) {

    global $DB, $USER;

    // Trigger course_module_viewed event.
    $params = [
        'context'  => $context,
        'objectid' => $simplequiz->id,
    ];

    $event = \mod_simplequiz2\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('simplequiz2', $simplequiz);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);

    // Check if completion is automaticaly tracked before set as viewed.
    if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
        $completion->set_module_viewed($cm);

        // Update timemodified for the view.
        $cmcompletion = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cm->id,
            'userid'         => $USER->id,
        ]);

        // In some cases, "mark activity as viewed" is not enabled in mod_form.
        if ($cmcompletion) {
            $cmcompletionupdate               = new \stdClass();
            $cmcompletionupdate->id           = $cmcompletion->id;
            $cmcompletionupdate->timemodified = time();
            $DB->update_record('course_modules_completion', $cmcompletionupdate);
        }
    }
}

/**
 * Pluginfile callback: find and send stored files for this module.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context $context Module context.
 * @param string $filearea File area name.
 * @param array $args Remaining path args (itemid and filepath parts).
 * @param bool $forcedownload Whether to force download.
 * @param array $options Send options for send_stored_file().
 * @return false|void False if file not found; otherwise sends file and exits.
 * @throws coding_exception
 */
function simplequiz2_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    // Requires user logged in the course where image is displayed.
    require_course_login($course, true, $cm);

    // Build the fullpath of the file before search it in DB.
    $itemid = $args[0];
    array_shift($args);

    $fs           = get_file_storage();
    $relativepath = implode('/', $args);

    $fullpath = rtrim("/$context->id/mod_simplequiz2/$filearea/" . $itemid . "/$relativepath", '/');

    // Try to get the file using hashed fullpath.
    $file = $fs->get_file_by_hash(sha1($fullpath));

    if ($file === false || $file->is_directory() === true) {
        return false;
    }

    // Send the file.
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Prepare simple quiz data from mform and store draft file
 * return [0 => ["questiontext" => $text, "answers" => [ 0 => ["text" => blabla, "iscorrect" => 0/1] ]
 *
 * @param int $cmid Course module id.
 * @param stdClass $data Submitted form data.
 * @return array
 */
function simplequiz2_prepare_question_from_mod_form(int $cmid, $data) {
    $context = context_module::instance($cmid);

    $questions = [];

    // Loop on questions with their answers data.
    for ($i = 0; $i < SIMPLE_QUIZ2_MAX_QUESTION_NB; $i++) {
        $fieldname = 'questions' . $i;
        if (!property_exists($data, $fieldname)) {
            continue;
        }

        $questionrawdata = $data->$fieldname;

        // Exclude empty question (TinyMCE may submit <p><br></p> instead of "").
        if (\mod_simplequiz2\util\editor_content::is_empty($questionrawdata['text']['text'] ?? '')) {
            continue;
        }

        // Start at one to avoid 0 to be removed when concate to answer item id.
        $questionitemid = $questionrawdata['questionorder'] + 1;

        // Prepare question data and store files.
        $question          = new stdClass();
        $question->text    = simplequiz2_save_editor_files($context->id, $questionitemid, $questionrawdata['text']);
        $question->answers = [];

        // Prepare answers data (convert text and exlude empty answer).
        $answeritemid = 1;
        foreach ($questionrawdata['answers'] as $order => $answerdata) {
            if (\mod_simplequiz2\util\editor_content::is_empty($answerdata['text'] ?? '')) {
                continue;
            }

            // Prepare object and store files.
            $answer              = (object) [
                'text'      => simplequiz2_save_editor_files($context->id, $questionitemid . $answeritemid, $answerdata),
                'iscorrect' => $questionrawdata['correctanswers'][$order],
            ];
            $question->answers[] = $answer;

            $answeritemid++;
        }

        // Add question at correct order.
        $questions[$questionrawdata['questionorder']] = $question;
    }

    // Fix questions order in the array and return it.
    ksort($questions);
    return $questions;
}

/**
 * Save draft files to real files and return converted text
 *
 * @param int $contextid
 * @param int $newitemid
 * @param array $editordata from draft item
 * @return string|null
 */
function simplequiz2_save_editor_files(int $contextid, $newitemid, array $editordata) {

    $draftitemid = $editordata['itemid'];
    $text        = $editordata['text'];

    $convertedtext = file_save_draft_area_files($draftitemid, $contextid, 'mod_simplequiz2', 'data', $newitemid, null, $text);

    return $convertedtext;
}

/**
 * Rewrite @@PLUGINFILE@@ to pluginfile.php/xxxx
 *
 * @param array $questions
 * @param int $cmid
 * @return array
 */
function simplequiz2_rewrite_pluginfile_urls($questions, int $cmid) {
    global $CFG;
    require_once("$CFG->libdir/filelib.php");

    $context = \context_module::instance($cmid);

    $options   = [
        'noclean' => true,
        'para'    => false,
        'filter'  => true,
        'context' => $context,
    ];
    $questions = (array) $questions;

    // Rename all @@PLUGINFILE@@ link with pluginfile.php.
    foreach ($questions as $order => $questiondata) {
        $questionitemid = $order + 1;

        // Question text.
        $questiontext = file_rewrite_pluginfile_urls(
            $questiondata->text,
            'pluginfile.php',
            $context->id,
            'mod_simplequiz2',
            'data',
            $questionitemid,
            $options
        );
        $questiontext = trim(format_text($questiontext, FORMAT_HTML, $options, null));

        $questions[$order]->text = $questiontext;

        // Answers.
        foreach ($questiondata->answers as $answerorder => $answerdata) {
            $answeritemid = $questionitemid . ($answerorder + 1);

            $answertext = file_rewrite_pluginfile_urls(
                $answerdata->text,
                'pluginfile.php',
                $context->id,
                'mod_simplequiz2',
                'data',
                $answeritemid,
                $options
            );
            $answertext = trim(format_text($answertext, FORMAT_HTML, $options, null));

            $questions[$order]->answers[$answerorder]->text = $answertext;
        }
    }

    return $questions;
}

/**
 * Extends the settings navigation with the simplequiz settings
 * - button to convert this module into a quiz module
 *
 * This function is called when the context for the page is a simplequiz module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav The settings navigation tree for the current page.
 * @param navigation_node|null $simplequiznode Activity node under settings (may be null).
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function simplequiz2_extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $simplequiznode = null) {
    global $PAGE, $DB, $COURSE;

    // Prepare context to capability check.
    $cmcontext = $PAGE->context;
    if ($cmcontext instanceof context_module) {
        $coursecontext = $cmcontext->get_course_context();
    }

    // Add convert to quiz button in simplequiz menu if simplequiz contain at least one question,
    // and course is not singleactivity.
    if ($coursecontext && has_capability('mod/quiz:addinstance', $coursecontext)) {
        $cm        = get_coursemodule_from_id('simplequiz2', $cmcontext->instanceid);
        $mod       = $DB->get_record('simplequiz2', ['id' => $cm->instance]);
        $questions = (array) json_decode($mod->questions);

        // Multichoice question need more than one response to works.
        if (count($questions) > 0) {
            $convertnode = navigation_node::create(
                get_string('converttoquiz', 'simplequiz2'),
                new moodle_url('/mod/simplequiz2/convert.php', ['cmid' => $cmcontext->instanceid])
            );
            $simplequiznode->add_node($convertnode);
        }
    }
}
