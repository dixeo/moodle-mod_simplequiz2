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
 * Simplequiz main renderer.
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\output;

defined('MOODLE_INTERNAL') || die();

use mod_simplequiz2\player_service;

/**
 * Renderer for mod_simplequiz2 student and module views.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Print all questions, answers and navigation.
     *
     * @param \mod_simplequiz2\simplequiz $simplequiz Loaded activity instance.
     * @param array $questionsdata Decoded question structures for the view.
     * @return bool|string
     */
    public function student_view($simplequiz, $questionsdata) {
        return player_service::render_player($questionsdata, [
            'embed' => false,
        ]);
    }
}
