<?php
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
    'mod_simplequiz2_get_unused_draft_itemids' => [
        'classname'   => 'mod_simplequiz2\external\get_unused_draft_itemids',
        'description' => 'Allocate fresh user draft item ids for deferred question editors',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
