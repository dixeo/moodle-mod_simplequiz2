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

namespace mod_simplequiz2\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Conversion of a simple quiz activity into a standard quiz activity.
 *
 * The page script owns login, capability checks, output and redirects; this class only builds
 * the confirmation and performs the confirmed conversion.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_conversion {
    /**
     * Confirmation box (without page header and footer) for converting this activity to a quiz.
     *
     * @param \stdClass $cm Simple quiz course module.
     * @param \stdClass $course Course of the activity.
     * @return string
     */
    public static function confirmation_html(\stdClass $cm, \stdClass $course): string {
        global $OUTPUT;

        $confirmurl = new \moodle_url('/mod/simplequiz2/convert.php', ['cmid' => $cm->id, 'confirm' => 1]);
        $returnurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
        return $OUTPUT->confirm(get_string('convertconfirm', 'simplequiz2'), $confirmurl, $returnurl);
    }

    /**
     * Convert after confirmation.
     *
     * Requires the session key before any change. All database changes run in one transaction, so a
     * failure leaves the course untouched and returns the error result without logging a conversion.
     *
     * The caller must have checked simplequiz2_convert_required_capabilities() in the course context.
     *
     * @param \stdClass $cm Simple quiz course module.
     * @param \stdClass $course Course of the activity.
     * @param \context_module $context Context of the simple quiz.
     * @return array{0: \moodle_url, 1: string, 2: string} Return URL, message, notification type.
     */
    public static function convert_confirmed(\stdClass $cm, \stdClass $course, \context_module $context): array {
        global $DB;

        require_sesskey();

        $returnurl = new \moodle_url('/course/view.php', ['id' => $cm->course]);
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

                $export = new \mod_simplequiz2\export_to_quiz($cm->id);
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
            return [
                $returnurl,
                get_string('cantconvertcodeerror', 'simplequiz2'),
                \core\output\notification::NOTIFY_ERROR,
            ];
        }

        $event = \mod_simplequiz2\event\quiz_conversion_completed::create_from_conversion(
            $simplequiz,
            $context,
            $cm,
            $export->get_quiz(),
            $export->get_quiz_course_module()
        );
        $event->trigger();

        return [
            $returnurl,
            get_string('convert_success', 'simplequiz2'),
            \core\output\notification::NOTIFY_SUCCESS,
        ];
    }
}
