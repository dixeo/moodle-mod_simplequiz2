<?php
/**
 * External API: render ephemeral practice quiz player HTML.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_simplequiz2\player_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Render embed player markup for tutor practice quizzes.
 */
class render_embed extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'questions' => new external_value(PARAM_RAW, 'JSON array of simplequiz2 questions'),
            'title' => new external_value(PARAM_TEXT, 'Quiz title', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * @param int $courseid
     * @param string $questions
     * @param string $title
     * @return array
     */
    public static function execute(int $courseid, string $questions, string $title = ''): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'questions' => $questions,
            'title' => $title,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $decoded = json_decode($params['questions'], true);
        if (!is_array($decoded) || empty($decoded)) {
            throw new \invalid_parameter_exception('Invalid questions JSON');
        }

        // Normalise to objects for player_service.
        $questionsdata = [];
        foreach ($decoded as $item) {
            $questionsdata[] = is_object($item) ? $item : (object) $item;
        }

        $html = player_service::render_player($questionsdata, [
            'embed' => true,
            'show_cancel' => true,
            'title' => $params['title'],
        ]);

        return [
            'html' => $html,
            'questioncount' => count($questionsdata),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered player HTML'),
            'questioncount' => new external_value(PARAM_INT, 'Number of questions'),
        ]);
    }
}
