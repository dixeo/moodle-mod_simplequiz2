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
 * Lists all simplequiz2 instances in a course.
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

$event = \mod_simplequiz2\event\course_module_instance_list_viewed::create(
    ['context' => context_course::instance($course->id)]
);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strnames = get_string('modulenameplural', 'simplequiz2');
$strlastmodified = get_string('lastmodified');
$strnamecol = get_string('name');
$strintro = get_string('moduleintro');

$PAGE->set_url('/mod/simplequiz2/index.php', ['id' => $course->id]);
$PAGE->set_title($course->shortname . ': ' . $strnames);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strnames);
echo $OUTPUT->header();
echo $OUTPUT->heading($strnames);

if (!$instances = get_all_instances_in_course('simplequiz2', $course)) {
    notice(get_string('thereareno', 'moodle', $strnames), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$usesections = course_format_uses_sections($course->format);
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    $table->head = [$strsectionname, $strnamecol, $strintro];
    $table->align = ['center', 'left', 'left'];
} else {
    $table->head = [$strlastmodified, $strnamecol, $strintro];
    $table->align = ['left', 'left', 'left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($instances as $instance) {
    $cm = $modinfo->cms[$instance->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($instance->section !== $currentsection) {
            if ($instance->section) {
                $printsection = get_section_name($course, $instance->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $instance->section;
        }
    } else {
        $printsection = '<span class="smallinfo">' . userdate($instance->timemodified) . '</span>';
    }

    $linkattributes = $instance->visible ? [] : ['class' => 'dimmed'];
    $table->data[] = [
        $printsection,
        html_writer::link(
            new moodle_url('/mod/simplequiz2/view.php', ['id' => $cm->id]),
            format_string($instance->name, true, ['context' => context_module::instance($cm->id)]),
            $linkattributes
        ),
        format_module_intro('simplequiz2', $instance, $cm->id),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
