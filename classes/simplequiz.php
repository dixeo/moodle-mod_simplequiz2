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
 *
 *
 * @package    mod_simplequiz2
 * @copyright  2023 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

use coding_exception;
use completion_info;
use dml_exception;
use stdClass;
use function simplequiz2_grade_item_update;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * mod_simplequizz entity class.
 */
class simplequiz {
    /**
     * Course module id ?
     *
     * @var false|stdClass|null
     */
    private $cm = null;
    /**
     * Instance id ?
     *
     * @var false|mixed|stdClass|null
     */
    private $instance = null;
    /**
     * Database interface object.
     *
     * @var database_interface
     */
    private $db;

    /**
     * Load simplequizz instance row.
     *
     * @param int $cmid
     * @throws coding_exception
     */
    public function __construct(int $cmid = 0) {
        // First, connect to DB, used in 99% cases.
        $this->db = database_interface::get_instance();

        // Get activity info if activity already exist.
        if ($cmid) {
            $this->cm = get_coursemodule_from_id('simplequiz2', $cmid, 0, false, MUST_EXIST);
            $this->instance = $this->db->get_simplequiz_by_id($this->cm->instance);
        }
    }

    /**
     * Permit to access private fields outside the class via $this->field syntax.
     *
     * @param string $field Property name.
     * @return mixed
     */
    public function __get($field) {
        return $this->$field;
    }

    /**
     * Get the relative attempts row for a given user and module.
     *
     * @param int $userid User id.
     * @return false|mixed|stdClass|null
     */
    public function get_user_attempt(int $userid) {
        return $this->db->get_user_attempts($this->cm->id, $userid);
    }

    /**
     * If no attempt is completed yet, add one attempt to the attempt counter,
     * update attempts times and mark has completed if needed
     *
     * @param object $attempt
     * @param int $completed (0 = wrong, 1 = correct)
     * @throws dml_exception
     */
    public function finish_attempt(object $attempt, int $completed = 0) {
        // If no previous attempt was fully completed, update number of user attempts.
        $attempts = $this->db->get_user_attempts($this->cm->id, $attempt->userid);

        // This is the first attempt of the user, we need to create the record.
        if (!$attempts) {
            $attempts = (object) [
                'cmid' => $this->cm->id,
                'userid' => $attempt->userid,
                'cntattempt' => 1,
                'timefirstattempt' => time(),
                'timelastattempt' => time(),
                'completed' => $completed,
            ];

            $this->db->add_user_attempts($attempts);
        } else if ($attempts->completed != 1) {
            // Add one to attempts counter and update last attempt time.
            $attempts->cntattempt++;
            $attempts->timelastattempt = time();
            $attempts->completed = $completed;
            $this->db->update_user_attempts($attempts);
        }
    }

    /**
     * Add attempt in user attempts counter if he haven't completed the simplequiz yet
     * and create attempt detail record
     *
     * @param int $userid
     * @return bool|int
     */
    public function create_attempt(int $userid) {
        // Create attempt data to store result details.
        $attemptdata = (object) [
            'cmid' => $this->cm->id,
            'userid' => $userid,
            'answers' => json_encode([]),
            'timecreated' => time(),
            'timefinished' => 0,
        ];

        $attemptid = $this->db->add_attempt_data($attemptdata);

        return $attemptid;
    }

    /**
     * Return the user's open attempt that has no answer yet, creating one if needed.
     *
     * Viewing the activity therefore never adds more than one empty attempt per user;
     * a new row is only created once the previous one holds at least one answer.
     *
     * @param int $userid User id.
     * @return int Attempt data row id.
     */
    public function get_or_create_open_attempt(int $userid): int {
        global $DB;

        $select = 'cmid = :cmid AND userid = :userid AND timefinished = 0 AND '
            . $DB->sql_compare_text('answers', 10) . ' = :empty';
        $records = $DB->get_records_select('simplequiz2_attempt_data', $select, [
            'cmid' => $this->cm->id,
            'userid' => $userid,
            'empty' => json_encode([]),
        ], 'id DESC', 'id', 0, 1);

        if ($records) {
            return (int) reset($records)->id;
        }

        return (int) $this->create_attempt($userid);
    }

    /**
     * Record the result of one question on an attempt and finish the attempt after the last question.
     *
     * Questions must be answered once each, in order, on an attempt that is not finished.
     * When every question is answered the attempt is marked finished, counted once in the
     * user's attempt summary and graded.
     *
     * @param int $attemptid Attempt data row id.
     * @param int $questionid Question index in activity data.
     * @param bool $answerstatus Whether the submitted answers for the question are fully correct.
     * @return bool True when this answer finished the attempt.
     * @throws \moodle_exception When the question cannot be answered on this attempt.
     */
    public function add_attempt_answer(int $attemptid, int $questionid, bool $answerstatus): bool {
        $questions = (array) json_decode($this->instance->questions);
        if (!array_key_exists($questionid, $questions)) {
            throw new \invalid_parameter_exception('Invalid question index');
        }

        // Serialise concurrent submissions on the same attempt.
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_simplequiz2_attempt');
        $lock = $lockfactory->get_lock('attempt_' . $attemptid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'moodle');
        }

        try {
            $attempt = $this->db->get_attempt_data($attemptid);
            if (!$attempt || (int) $attempt->cmid !== (int) $this->cm->id) {
                throw new \moodle_exception('error_invalidattempt', 'mod_simplequiz2');
            }
            if (!empty($attempt->timefinished)) {
                throw new \moodle_exception('error_attemptfinished', 'mod_simplequiz2');
            }

            $currentdata = json_decode($attempt->answers, true);
            $currentdata = is_array($currentdata) ? $currentdata : [];
            if (array_key_exists($questionid, $currentdata)) {
                throw new \moodle_exception('error_alreadyanswered', 'mod_simplequiz2');
            }

            $questionkeys = array_map('intval', array_keys($questions));
            $expected = $questionkeys[count($currentdata)] ?? null;
            if ($expected !== $questionid) {
                throw new \moodle_exception('error_questionoutoforder', 'mod_simplequiz2');
            }

            $currentdata[$questionid] = $answerstatus;
            $finished = count($currentdata) >= count($questions);

            $newdata = (object) [
                'id' => $attempt->id,
                'answers' => json_encode($currentdata),
            ];
            if ($finished) {
                $newdata->timefinished = time();
            }
            $this->db->update_attempt_data($newdata);

            if ($finished) {
                $this->grade_finished_attempt($attempt);
            }
        } finally {
            $lock->release();
        }

        // Update completion state.
        $course = get_course($this->instance->course);
        $completion = new completion_info($course);
        if ($completion->is_enabled($this->cm) && $this->instance->completionminattempts) {
            $completion->update_state($this->cm, COMPLETION_COMPLETE);
        }

        return $finished;
    }

    /**
     * Count a finished attempt in the user's summary and push a better grade to the gradebook.
     *
     * @param stdClass $attempt Attempt data row.
     * @return void
     */
    private function grade_finished_attempt(stdClass $attempt): void {
        $attemptgrade = $this->get_attempt_grade($attempt->id);
        $currentgrade = $this->get_current_grade($attempt->userid);

        // Add one to the attempts counter and mark has completed if needed.
        $completed = $attemptgrade == $this->instance->grade ? 1 : 0;
        $this->finish_attempt($attempt, $completed);

        // Update user grade if attempt grade is higher than current grade.
        if ($attemptgrade >= $currentgrade) {
            // Prepare grade item other info.
            $gradeitem = $this->db->get_simplequiz_gradeitem($this->instance->id, $this->cm->course);
            $gradeitem->name = $this->cm->name;
            $gradeitem->cmidnumber = $this->cm->id;
            $gradeitem->grade = $this->instance->grade;
            $gradeitem->id = $this->instance->id;
            $gradeitem->course = $this->instance->course;
            $gradeobj = $this->set_grade($attempt->userid, $attemptgrade);

            simplequiz2_grade_item_update($gradeitem, $gradeobj);
        }
    }

    /**
     * Return decoded answer data from given attempt
     *
     * @param int $attemptid
     * @return bool|mixed
     */
    public function get_user_attempt_data(int $attemptid) {
        $attempt = $this->db->get_attempt_data($attemptid);

        if (!$attempt) {
            return false;
        }

        return (array) json_decode($attempt->answers);
    }

    /**
     * Get grade of selected attempt
     *
     * @param int $attemptid
     * @return bool|float|int
     * @throws dml_exception
     */
    public function get_attempt_grade($attemptid) {
        // Get attempt other result.
        $attempt = $this->db->get_attempt_data($attemptid);

        if (!$attempt) {
            return false;
        }
        $currentdata = (array) json_decode($attempt->answers);

        // Calculate grade from attempt answers.
        $grademax = MOD_SIMPLEQUIZ2_GRADE_MAX;
        $questions = (array) json_decode($this->instance->questions);
        $grade = 0;

        foreach ($questions as $id => $question) {
            // Check if the answer is correct.
            if (array_key_exists($id, $currentdata) && $currentdata[$id] == 1) {
                $grade += $grademax / count($questions);
            }
        }

        return $grade;
    }

    /**
     * Return current user grade for this simplequiz
     *
     * @param int $userid
     * @return int
     * @throws dml_exception
     */
    public function get_current_grade(int $userid) {
        $gradeitem = $this->db->get_simplequiz_gradeitem($this->instance->id, $this->cm->course);
        $currentgrade = 0;
        if ($gradeitem) {
            $usergrade = $this->db->get_user_grade($gradeitem->id, $userid);
            $currentgrade = $usergrade ? $usergrade : 0;
        }

        return $currentgrade;
    }

    /**
     * Set the grade object for the _grade_item_update callback
     *
     * @param int $userid User id.
     * @param float $grade Raw grade value.
     * @return stdClass
     */
    private function set_grade(int $userid, float $grade): stdClass {
        // PHP 8.1: cast float grade to int to avoid deprecation notices.
        $grade = (int) $grade;

        $gradeobj = new stdClass();
        $gradeobj->userid = $userid;
        $gradeobj->rawgrade = $grade;
        $gradeobj->finalgrade = $grade;
        return $gradeobj;
    }
}
