<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Web service definitions for mod_simplequiz2.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_simplequiz2_render_embed' => [
        'classname'   => 'mod_simplequiz2\external\render_embed',
        'description' => 'Render ephemeral practice quiz player HTML for the tutor',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'mod_simplequiz2_check_embed_question' => [
        'classname'   => 'mod_simplequiz2\external\check_embed_question',
        'description' => 'Grade one practice quiz question server-side for embed player',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'mod_simplequiz2_check_question' => [
        'classname'   => 'mod_simplequiz2\external\check_question',
        'methodname'  => 'execute',
        'description' => 'Grade one activity question and store the result on the attempt',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/simplequiz2:view',
    ],
    'mod_simplequiz2_get_attempt_results' => [
        'classname'   => 'mod_simplequiz2\external\get_attempt_results',
        'methodname'  => 'execute',
        'description' => 'Read the current attempt score and the user best grade',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/simplequiz2:view',
    ],
    'mod_simplequiz2_get_unused_draft_itemids' => [
        'classname'   => 'mod_simplequiz2\external\get_unused_draft_itemids',
        'description' => 'Allocate fresh user draft item ids for deferred question editors',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
