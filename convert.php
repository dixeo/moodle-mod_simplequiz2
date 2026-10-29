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

global $PAGE, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('simplequiz2', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/quiz:addinstance', $context);

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

global $DB;

$simplequiz = $DB->get_record('simplequiz2', ['id' => $cm->instance], '*', MUST_EXIST);

try {
    // ELEA_RQM-234: change course format if course is singleactivity.
    if ($course->format == 'singleactivity') {
        $coursedata = (object) [
            'id' => $course->id,
            'format' => 'topics',
        ];
        update_course($coursedata);

        // Remove section 1 to only use section 0.
        $DB->delete_records('course_sections', ['course' => $course->id, 'section' => 1]);
    }

    // Export current simplequiz to quiz.
    $export = new \mod_simplequiz2\export_to_quiz($cmid);
    $export->export_to_quiz();

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
} catch (Exception $e) {
    redirect($returnurl, get_string('cantconvertcodeerror', 'simplequiz2'));
}
