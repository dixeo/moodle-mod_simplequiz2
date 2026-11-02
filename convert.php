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
 * Convert a SimpleQuiz activity into a standard Quiz activity.
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

global $PAGE, $OUTPUT, $DB;

$cmid = required_param('cmid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('simplequiz2', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
$coursecontext = context_course::instance($course->id);
require_all_capabilities(simplequiz2_convert_required_capabilities($course), $coursecontext);

$returnurl = new moodle_url('/course/view.php', ['id' => $cm->course]);
$pageurl = new moodle_url('/mod/simplequiz2/convert.php', ['cmid' => $cmid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(get_string('converttoquiz', 'simplequiz2'));

if (!$confirm) {
    echo $OUTPUT->header();
    $confirmurl = new moodle_url('/mod/simplequiz2/convert.php', ['cmid' => $cmid, 'confirm' => 1]);
    echo $OUTPUT->confirm(get_string('convertconfirm', 'simplequiz2'), $confirmurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

require_sesskey();

$simplequiz = $DB->get_record('simplequiz2', ['id' => $cm->instance], '*', MUST_EXIST);

// All database changes are made in one transaction so a failure leaves the course untouched.
$export = null;
try {
    $transaction = $DB->start_delegated_transaction();
    try {
        if ($course->format == 'singleactivity') {
            // A quiz cannot be added next to the single activity: switch to a sectioned format.
            update_course((object) ['id' => $course->id, 'format' => 'topics']);

            // Keep only section 0 when the single activity format left an empty section 1.
            $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
            if ($section && trim((string) $section->sequence) === '') {
                course_delete_section(get_course($course->id), $section, false);
            }
        }

        $export = new \mod_simplequiz2\export_to_quiz($cmid);
        $export->export_to_quiz();

        $transaction->allow_commit();
    } catch (\Throwable $e) {
        $transaction->rollback($e);
    }
} catch (\Throwable $e) {
    if ($DB->is_transaction_started()) {
        // A nested transaction opened by core code was left unfinished, so the rollback above
        // only marked it; roll the whole unit of work back before anything else is written.
        $DB->force_transaction_rollback();
    }
    // Course and module caches may have been rebuilt from rolled back data.
    rebuild_course_cache($course->id, true);
    debugging(
        'Conversion of simplequiz2 course module ' . $cm->id . ' to a quiz failed: ' . $e->getMessage(),
        DEBUG_NORMAL,
        $e->getTrace()
    );
    redirect(
        $returnurl,
        get_string('cantconvertcodeerror', 'simplequiz2'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$event = \mod_simplequiz2\event\quiz_conversion_completed::create_from_conversion(
    $simplequiz,
    $context,
    $cm,
    $export->get_quiz(),
    $export->get_quiz_course_module()
);
$event->trigger();

redirect(
    $returnurl,
    get_string('convert_success', 'simplequiz2'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
