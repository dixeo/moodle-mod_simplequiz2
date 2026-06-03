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
 * Print particular instance of simple quiz
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/simplequiz2/classes/simplequiz.php');

$cmid    = required_param('id', PARAM_INT);
$cm      = get_coursemodule_from_id('simplequiz2', $cmid);
$context = context_module::instance($cm->id);
require_login($cm->course, true, $cm);
require_capability('mod/simplequiz2:view', $context);

$PAGE->set_url('/mod/simplequiz2/view.php', ['id' => $cm->id]);
$PAGE->set_title($cm->name);
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');

// Prepare data.
$course      = get_course($cm->course);
$renderer    = $PAGE->get_renderer('mod_simplequiz2');
$simplequiz  = new \mod_simplequiz2\simplequiz($cmid);
$decodeddata = (array) json_decode($simplequiz->__get('instance')->questions);

// Mark activity has viewed.
simplequiz2_view($simplequiz->__get('instance'), $course, $cm, $context);

// Init attempt.
$attemptid = $simplequiz->create_attempt($USER->id);

echo $OUTPUT->header();

if (!$decodeddata) {
    // Print message if there is no question.
    echo "<p>" . get_string('no-questions', 'simplequiz2') . "</p>";
} else {
    $converteddata = simplequiz2_rewrite_pluginfile_urls($decodeddata, $cmid);

    echo $renderer->student_view($simplequiz, $converteddata);

    // Send data for authentication and enrollment check.
    $jsparams = [
        'instance_id'      => $cm->instance,
        'course_id'        => $cm->course,
        'course_module_id' => $cm->id,
        'attempt_id'       => $attemptid,
    ];

    $PAGE->requires->js_call_amd('mod_simplequiz2/activity_player', 'init', $jsparams);
}

echo $OUTPUT->footer();
