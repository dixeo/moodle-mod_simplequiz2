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
 * Main generic controller
 *
 * Handle HTTP request
 * Prepare generic things (params, action, db,...)
 * Offer generic util methods (data validation, moodle behaviour, etc...)
 * Run the requested method in the mod controller
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

use moodle_exception;
use require_login_exception;

defined('MOODLE_INTERNAL') || die();

// Load DB interface.
require_once($CFG->dirroot . '/mod/simplequiz2/classes/database_interface.php');

/**
 * Base class for simplequiz2 AJAX/API controllers (params, action, DB access).
 */
abstract class mod_api {
    /**
     * Merged GET/POST parameters.
     *
     * @var array
     */
    private $params = [];

    /**
     * Controller method name to run.
     *
     * @var string
     */
    private $action = '';

    /**
     * Database interface singleton.
     *
     * @var database_interface
     */
    protected $db;

    /**
     * Constructor.
     * Must be called by children.
     * Parse action and params.
     * Load DB interface
     *
     * @return void
     */
    protected function __construct() {
        $this->prepare_params();
        $this->prepare_action($this->params->action);
        $this->db = database_interface::get_instance();
    }

    /**
     * Build $this->params with received payload.
     *
     * @return void
     */
    private function prepare_params() {
        // Technical note : Sanitize string reencode JSON.
        $get = filter_input_array(INPUT_GET, FILTER_UNSAFE_RAW);
        $post = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
        $this->params = (object) array_merge((array) $get, (array) $post);
    }

    /**
     * Actions the AJAX dispatcher may invoke.
     *
     * Subclasses may override to extend the list. Names must match protected
     * handler methods on the concrete controller; never include run or other
     * infrastructure methods.
     *
     * @return string[]
     */
    protected function get_allowed_actions(): array {
        return [
            'check_question',
            'get_attempt_results',
        ];
    }

    /**
     * Set $this->action and return error if it's impossible.
     *
     * @param string|null $action The mod controller method name to run.
     * @return void
     */
    private function prepare_action($action) {
        if ($action === null) {
            $this->send(400, get_string('error_actionrequired', 'mod_simplequiz2'));
        } else if ($action === '') {
            $this->send(400, get_string('error_actioninvalid', 'mod_simplequiz2'));
        } else if (!in_array((string) $action, $this->get_allowed_actions(), true)) {
            $this->send(405, get_string('error_actionunknown', 'mod_simplequiz2', $action));
        } else {
            $this->action = $action;
        }
    }

    /**
     * Fetch a param in $this->params
     *
     * @param string $name Param name.
     * @param int $type PHP filter_* constant (e.g. FILTER_VALIDATE_INT).
     * @param bool $required If false, missing params return $default.
     * @param mixed|null $default Default when param is optional and missing.
     * @return mixed
     */
    protected function get_param(string $name, $type = FILTER_UNSAFE_RAW, bool $required = true, $default = null) {
        // Check required.
        if ($required === true && isset($this->params->$name) === false) {
            $this->send(400, get_string('error_paramrequired', 'mod_simplequiz2', $name));
        }

        // Return default if param doesn't exists.
        if ($required === false && isset($this->params->$name) === false) {
            return $default;
        }

        // Apply filter.
        $filtered = filter_var($this->params->$name, $type);

        // Return error if value is not filterable with this filter.
        if ($filtered === null || $filtered === false) {
            $this->send(400, get_string('error_paramtype', 'mod_simplequiz2', $name));
        }

        // Return filtered value.
        return $filtered;
    }

    /**
     * Every thing is set up, call the required method in the mod controller
     *
     * @return void
     */
    public function run() {
        // Defense in depth: never dispatch without an allowlisted action.
        if ($this->action === '' || !in_array($this->action, $this->get_allowed_actions(), true)) {
            $this->send(405, get_string('error_actionunknown', 'mod_simplequiz2', $this->action));
        }

        call_user_func([
            $this,
            $this->action,
        ]);
    }

    /**
     * Check if user is simply logged.
     *
     * @return void
     */
    protected function user_is_logged() {
        try {
            // Disable redirection to login page.
            require_login(null, false, null, false, true);
        } catch (require_login_exception $moodleexception) {
            $this->send(401, get_string('error_mustbelogged', 'mod_simplequiz2', $moodleexception->errorcode));
        } catch (moodle_exception $moodleexception) {
            $this->send(400, get_string('error_moodleexception', 'mod_simplequiz2', $moodleexception->errorcode));
        }
    }

    /**
     * Check if current user is enrolled in a course module
     *
     * @param string $modname the module identifier.
     * @return void
     */
    protected function user_is_enrolled(string $modname): void {
        // Params required to check enrollment.
        $courseid = $this->get_param('courseid', FILTER_VALIDATE_INT);
        $coursemoduleid = $this->get_param('coursemoduleid', FILTER_VALIDATE_INT);

        try {
            // Load course module.
            $cm = get_coursemodule_from_id($modname, $coursemoduleid);

            // Check enrollment.
            require_login($courseid, false, $cm, false, true);
            require_capability('mod/' . $modname . ':view', \context_module::instance($cm->id));
        } catch (require_login_exception $moodleexception) {
            $this->send(403, get_string('error_mustbeenrolled', 'mod_simplequiz2', $moodleexception->errorcode));
        } catch (\required_capability_exception $moodleexception) {
            $this->send(403, get_string('error_capabilityrequired', 'mod_simplequiz2', $moodleexception->errorcode));
        } catch (moodle_exception $moodleexception) {
            $this->send(400, get_string('error_moodleexception', 'mod_simplequiz2', $moodleexception->errorcode));
        }
    }

    /**
     * Build response datas before serialize and return them to the client.
     *
     * @param int $httpstatus HTTP status code
     * @param string $message A global information about the response
     * @param array $payload data
     * @return void
     */
    protected function send(int $httpstatus, string $message, array $payload = []): void {
        // Change the content type and status HTTP header.
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpstatus);

        // Build mandatory datas.
        $generic = [];
        $generic['status'] = $httpstatus;
        $generic['message'] = $message;

        // Add other data if necessary.
        $response = array_merge($generic, $payload);

        // Return the json.
        exit(json_encode($response));
    }

    /**
     * This magic method is called by PHP when the called method doesn't exist
     *
     * @param string $name called method name
     * @param array $arguments called arguments
     * @return void
     */
    public function __call($name, $arguments): void {
        $this->send(405, get_string('error_actionunknown', 'mod_simplequiz2', $name));
    }
}
