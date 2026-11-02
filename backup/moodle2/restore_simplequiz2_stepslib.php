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
 * Restore step definitions for mod_simplequiz2.
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_simplequiz_activity_task
 */

/**
 * Structure step to restore one simplequiz activity.
 */
class restore_simplequiz2_activity_structure_step extends restore_activity_structure_step {
    /**
     * Declare restore path elements for the activity and optional user data.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];
        $paths[] = new restore_path_element('simplequiz2', '/activity/simplequiz2');

        $userinfo = $this->get_setting_value('userinfo');
        if ($userinfo) {
            $paths[] = new restore_path_element(
                'user_attempt_summary',
                '/activity/simplequiz2/user_attempt_summaries/user_attempt_summary'
            );
            $paths[] = new restore_path_element(
                'user_attempt_session',
                '/activity/simplequiz2/user_attempt_sessions/user_attempt_session'
            );
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Insert restored simplequiz2 activity row and register the new instance.
     *
     * @param array $data Parsed XML fields for one activity.
     * @throws base_step_exception
     * @throws dml_exception
     */
    protected function process_simplequiz2($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->course = $this->get_courseid();

        // Insert the simplequiz record.
        $newitemid = $DB->insert_record('simplequiz2', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one row from simplequiz2_attempts.
     *
     * @param array $data
     * @return void
     */
    protected function process_user_attempt_summary($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $data->cmid = $this->task->get_moduleid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (empty($data->userid)) {
            return;
        }

        $data->timefirstattempt = $this->apply_date_offset($data->timefirstattempt);
        $data->timelastattempt = $this->apply_date_offset($data->timelastattempt);

        $DB->insert_record('simplequiz2_attempts', $data);
    }

    /**
     * Restore one row from simplequiz2_attempt_data.
     *
     * @param array $data
     * @return void
     */
    protected function process_user_attempt_session($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $data->cmid = $this->task->get_moduleid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (empty($data->userid)) {
            return;
        }

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        if (!isset($data->timefinished)) {
            // Backups without this field: attempts holding answers cannot be resumed, so close them.
            $data->timefinished = ((string) $data->answers !== '[]') ? $data->timecreated : 0;
        } else if ($data->timefinished) {
            $data->timefinished = $this->apply_date_offset($data->timefinished);
        }

        $DB->insert_record('simplequiz2_attempt_data', $data);
    }

    /**
     * Link restored files into the new activity context.
     */
    protected function after_execute() {
        // Move files into the restored plugin location.
        $this->add_related_files('mod_simplequiz2', 'intro', null);
        $this->add_related_files('mod_simplequiz2', 'data', null);
    }
}
