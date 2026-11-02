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
 * Privacy subsystem implementation for mod_simplequiz2.
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as privacy_metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as privacy_plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for SimpleQuiz (attempts and per-question answer payloads).
 */
class provider implements core_userlist_provider, privacy_metadata_provider, privacy_plugin_provider {
    /**
     * Describe stored personal data for the privacy API.
     *
     * @param collection $collection The metadata collection to extend.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:core_files'
        );

        $collection->add_database_table(
            'simplequiz2_attempts',
            [
                'cmid' => 'privacy:metadata:simplequiz2_attempts:cmid',
                'userid' => 'privacy:metadata:simplequiz2_attempts:userid',
                'cntattempt' => 'privacy:metadata:simplequiz2_attempts:cntattempt',
                'timefirstattempt' => 'privacy:metadata:simplequiz2_attempts:timefirstattempt',
                'timelastattempt' => 'privacy:metadata:simplequiz2_attempts:timelastattempt',
                'completed' => 'privacy:metadata:simplequiz2_attempts:completed',
            ],
            'privacy:metadata:simplequiz2_attempts'
        );

        $collection->add_database_table(
            'simplequiz2_attempt_data',
            [
                'cmid' => 'privacy:metadata:simplequiz2_attempt_data:cmid',
                'userid' => 'privacy:metadata:simplequiz2_attempt_data:userid',
                'answers' => 'privacy:metadata:simplequiz2_attempt_data:answers',
                'timecreated' => 'privacy:metadata:simplequiz2_attempt_data:timecreated',
                'timefinished' => 'privacy:metadata:simplequiz2_attempt_data:timefinished',
            ],
            'privacy:metadata:simplequiz2_attempt_data'
        );

        return $collection;
    }

    /**
     * Return contexts containing user data for this plugin.
     *
     * @param int $userid The user id.
     * @return contextlist Context list for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {simplequiz2} sq ON sq.id = cm.instance
             LEFT JOIN {simplequiz2_attempts} sa ON sa.cmid = cm.id AND sa.userid = :userid1
             LEFT JOIN {simplequiz2_attempt_data} sd ON sd.cmid = cm.id AND sd.userid = :userid2
                 WHERE sa.id IS NOT NULL OR sd.id IS NOT NULL";

        $params = [
            'modname' => 'simplequiz2',
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Add users who have data in the given context to the user list.
     *
     * @param userlist $userlist The user list to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'cmid' => $context->instanceid,
            'modname' => 'simplequiz2',
        ];

        $sql = "SELECT sa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {simplequiz2} sq ON sq.id = cm.instance
                  JOIN {simplequiz2_attempts} sa ON sa.cmid = cm.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql2 = "SELECT sd.userid
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                   JOIN {simplequiz2} sq ON sq.id = cm.instance
                   JOIN {simplequiz2_attempt_data} sd ON sd.cmid = cm.id
                  WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql2, $params);
    }

    /**
     * Export user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist Contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('simplequiz2', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $contextdata = helper::get_context_data($context, $user);

            $summary = $DB->get_record('simplequiz2_attempts', [
                'cmid' => $cm->id,
                'userid' => $user->id,
            ]);

            $sessions = $DB->get_records('simplequiz2_attempt_data', [
                'cmid' => $cm->id,
                'userid' => $user->id,
            ], 'timecreated ASC, id ASC');

            $attemptexport = new \stdClass();
            if ($summary) {
                $attemptexport->summary = (object) [
                    'cntattempt' => $summary->cntattempt,
                    'timefirstattempt' => \core_privacy\local\request\transform::datetime($summary->timefirstattempt),
                    'timelastattempt' => \core_privacy\local\request\transform::datetime($summary->timelastattempt),
                    'completed' => $summary->completed,
                ];
            } else {
                $attemptexport->summary = null;
            }

            $attemptexport->sessions = [];
            foreach ($sessions as $session) {
                $row = new \stdClass();
                $row->timecreated = \core_privacy\local\request\transform::datetime($session->timecreated);
                $row->timefinished = $session->timefinished
                    ? \core_privacy\local\request\transform::datetime($session->timefinished)
                    : null;
                $row->answers = $session->answers;
                $attemptexport->sessions[] = $row;
            }

            $contextdata = (object) array_merge((array) $contextdata, ['attempts' => $attemptexport]);

            writer::with_context($context)->export_data([], $contextdata);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Delete all user data in the given context for this plugin.
     *
     * @param \context $context The module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('simplequiz2', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('simplequiz2_attempts', ['cmid' => $cm->id]);
        $DB->delete_records('simplequiz2_attempt_data', ['cmid' => $cm->id]);
    }

    /**
     * Delete one user's data for the approved contexts.
     *
     * @param approved_contextlist $contextlist Contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('simplequiz2', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $DB->delete_records('simplequiz2_attempts', ['cmid' => $cm->id, 'userid' => $userid]);
            $DB->delete_records('simplequiz2_attempt_data', ['cmid' => $cm->id, 'userid' => $userid]);
        }
    }

    /**
     * Delete data for multiple users in the user list context.
     *
     * @param approved_userlist $userlist Users approved for deletion.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('simplequiz2', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['cmid' => $cm->id], $inparams);

        $DB->delete_records_select(
            'simplequiz2_attempts',
            "cmid = :cmid AND userid $insql",
            $params
        );
        $DB->delete_records_select(
            'simplequiz2_attempt_data',
            "cmid = :cmid AND userid $insql",
            $params
        );
    }
}
