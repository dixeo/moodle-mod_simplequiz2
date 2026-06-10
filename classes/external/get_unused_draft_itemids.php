<?php
/**
 * External API: allocate fresh user draft item ids for deferred editors.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\external;

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

/**
 * Return unused draft item ids for resetting question editor fields client-side.
 */
class get_unused_draft_itemids extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * @return array
     */
    public static function execute(): array {
        global $USER;

        require_login();
        self::validate_context(context_user::instance($USER->id));

        self::validate_parameters(self::execute_parameters(), []);

        $itemid = file_get_unused_draft_itemid();

        return ['itemid' => $itemid];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'itemid' => new external_value(PARAM_INT, 'Draft item id'),
        ]);
    }
}
