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
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

global $PAGE, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('simplequiz2', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
$coursecontext = context_course::instance($course->id);
require_all_capabilities(simplequiz2_convert_required_capabilities($course), $coursecontext);

$pageurl = new moodle_url('/mod/simplequiz2/convert.php', ['cmid' => $cmid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(get_string('converttoquiz', 'simplequiz2'));

if (!$confirm) {
    echo $OUTPUT->header();
    echo \mod_simplequiz2\local\quiz_conversion::confirmation_html($cm, $course);
    echo $OUTPUT->footer();
    die;
}

[$url, $message, $type] = \mod_simplequiz2\local\quiz_conversion::convert_confirmed($cm, $course, $context);
redirect($url, $message, null, $type);
