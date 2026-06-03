<?php
/**
 * Shared player preparation and rendering for activity and embed modes.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2;

defined('MOODLE_INTERNAL') || die();

/**
 * Prepares question data and renders the student player template.
 */
class player_service {

    /**
     * Prepare question objects for Mustache (shuffle answers, set order flags).
     * Strips iscorrect from answer objects so it is not exposed in HTML.
     *
     * @param array $questionsdata Decoded question structures.
     * @return array Template-ready question list.
     */
    public static function prepare_questions_for_display(array $questionsdata): array {
        $data = [];
        $count = count($questionsdata);

        foreach ($questionsdata as $order => $question) {
            if (is_array($question)) {
                $question = (object) $question;
            }

            $displayquestion = clone $question;
            $displayquestion->order = $order;
            $displayquestion->rank = $order + 1;
            $displayquestion->islast = ($order + 1 === $count);

            $answers = [];
            foreach ($displayquestion->answers as $rank => $answer) {
                if (is_array($answer)) {
                    $answer = (object) $answer;
                }
                $displayanswer = clone $answer;
                $displayanswer->order = $rank;
                unset($displayanswer->iscorrect);
                $answers[] = $displayanswer;
            }

            shuffle($answers);
            $displayquestion->answers = $answers;
            $data[] = $displayquestion;
        }

        return $data;
    }

    /**
     * Render the quiz player HTML.
     *
     * @param array $questionsdata Raw decoded questions (may include iscorrect).
     * @param array $options embed, show_cancel, title
     * @return string Rendered HTML.
     */
    public static function render_player(array $questionsdata, array $options = []): string {
        global $PAGE;

        $embed = !empty($options['embed']);
        $showcancel = !empty($options['show_cancel']);
        $title = $options['title'] ?? '';

        $renderer = $PAGE->get_renderer('mod_simplequiz2');
        $prepared = self::prepare_questions_for_display($questionsdata);

        $playerhtml = $renderer->render_from_template('mod_simplequiz2/simplequiz_container', [
            'questions' => $prepared,
            'embed' => $embed,
        ]);

        if ($embed) {
            return $renderer->render_from_template('mod_simplequiz2/embed_wrapper', [
                'playerhtml' => $playerhtml,
            ]);
        }

        return \html_writer::div($playerhtml, 'simplequiz2-player');
    }
}
