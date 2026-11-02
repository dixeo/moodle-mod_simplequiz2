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
 * Interface for database
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

use dml_exception;
use stdClass;

/**
 * Thin wrapper around $DB for simplequiz2 persistence.
 */
class database_interface {
    /**
     * Moodle database manager.
     *
     * @var \moodle_database
     */
    protected $db;

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    protected static $instance;

    /**
     * Create the interface and bind global $DB.
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Create a singleton
     *
     * @return database_interface
     */
    public static function get_instance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Return an simplequiz DB row by its id.
     *
     * @param int $id simplequiz instance id.
     * @return stdClass
     * @throws dml_exception When the instance does not exist.
     */
    public function get_simplequiz_by_id(int $id) {
        return $this->db->get_record('simplequiz2', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Get simplequiz_attempts record
     *
     * @param int $cmid
     * @param int $userid
     * @return mixed
     * @throws dml_exception
     */
    public function get_user_attempts(int $cmid, int $userid) {
        return $this->db->get_record('simplequiz2_attempts', [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);
    }

    /**
     * Insert simplequiz_attempts record
     *
     * @param object $attempts
     * @return bool|int
     * @throws dml_exception
     */
    public function add_user_attempts(object $attempts) {
        return $this->db->insert_record('simplequiz2_attempts', $attempts);
    }

    /**
     * Update simplequiz_attempts record
     *
     * @param stdClass $attempts Row object (must include id).
     * @return bool
     * @throws dml_exception
     */
    public function update_user_attempts($attempts) {
        return $this->db->update_record('simplequiz2_attempts', $attempts);
    }

    /**
     * Get simplequiz_attempt_data record
     *
     * @param int $attemptid
     * @return mixed
     * @throws dml_exception
     */
    public function get_attempt_data(int $attemptid) {
        return $this->db->get_record('simplequiz2_attempt_data', ['id' => $attemptid]);
    }

    /**
     * Add simplequiz_attempt_data record
     *
     * @param object $attempt
     * @return bool|int
     * @throws dml_exception
     */
    public function add_attempt_data(object $attempt) {
        return $this->db->insert_record('simplequiz2_attempt_data', $attempt);
    }

    /**
     * Update simplequiz_attempt_data record
     *
     * @param stdClass $attempt Row object (must include id).
     * @return bool
     * @throws dml_exception
     */
    public function update_attempt_data($attempt) {
        return $this->db->update_record('simplequiz2_attempt_data', $attempt);
    }

    /**
     * Return grade_items record for given simplequiz id
     *
     * @param int $simplequizid
     * @param int $courseid
     * @return object
     * @throws dml_exception
     */
    public function get_simplequiz_gradeitem(int $simplequizid, int $courseid) {
        return $this->db->get_record('grade_items', [
            'iteminstance' => $simplequizid,
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => 'simplequiz2',
        ]);
    }

    /**
     * Get the final grade of a user (logged or not) for a given grade item.
     *
     * @param int $gradeitemid
     * @param int|null $userid
     * @return false|float|null Final grade, or false when the user has no grade row.
     * @throws dml_exception
     */
    public function get_user_grade(int $gradeitemid, int $userid) {
        $record = $this->db->get_record('grade_grades', [
            'itemid' => $gradeitemid,
            'userid' => $userid,
        ], 'finalgrade');
        if (!$record) {
            return false;
        }
        return $record->finalgrade;
    }
}
