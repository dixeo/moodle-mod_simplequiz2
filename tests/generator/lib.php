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
 * Data generator for mod_simplequiz2.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * mod_simplequiz2 data generator.
 *
 * @package    mod_simplequiz2
 * @category   test
 */
class mod_simplequiz2_generator extends testing_module_generator {

    /**
     * Build a minimal questions JSON payload for tests.
     *
     * @return string JSON-encoded questions array
     */
    public function build_questions_json(): string {
        $questions = [
            (object) [
                'text' => '<p>Sample question</p>',
                'answers' => [
                    (object) ['text' => '<p>Correct</p>', 'iscorrect' => 1],
                    (object) ['text' => '<p>Wrong</p>', 'iscorrect' => 0],
                ],
            ],
        ];

        return json_encode($questions);
    }

    /**
     * @param stdClass|array|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        global $DB;

        $record = (object) (array) $record;

        $questionsjson = $record->questions ?? $this->build_questions_json();
        unset($record->questions);

        if (!isset($record->grade)) {
            $record->grade = SIMPLE_QUIZ2_GRADE_MAX;
        }

        $instance = parent::create_instance($record, (array) $options);

        $DB->set_field('simplequiz2', 'questions', $questionsjson, ['id' => $instance->id]);

        return $instance;
    }
}
