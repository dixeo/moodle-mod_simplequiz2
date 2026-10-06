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

namespace mod_simplequiz2\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Build Mustache context for mod_simplequiz2/question_summary.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_summary_context {
    /**
     * Whether a question slot has any stored content (text, answers, or feedback).
     *
     * @param object|null $questiondata Question object from simplequiz JSON.
     * @return bool
     */
    public static function question_slot_has_content(?object $questiondata): bool {
        if ($questiondata === null) {
            return false;
        }

        if (!editor_content::is_empty($questiondata->text ?? '')) {
            return true;
        }

        if (!empty($questiondata->answers) && is_array($questiondata->answers)) {
            foreach ($questiondata->answers as $answer) {
                if (is_array($answer)) {
                    $answer = (object) $answer;
                }
                if (!editor_content::is_empty($answer->text ?? '')) {
                    return true;
                }
            }
        }

        foreach (['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'] as $field) {
            if (!editor_content::is_empty($questiondata->$field ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Context for a stored question row from JSON.
     *
     * When a module context and question index are provided, plugin-file URLs are
     * rewritten before cleaning. Otherwise the HTML is cleaned without file rewriting.
     *
     * @param object|null $questiondata Question object from simplequiz JSON.
     * @param \context|null $modulecontext Module context for filters and plugin files.
     * @param int|null $questionindex Zero-based question index, used for file item ids.
     * @return array Mustache context.
     */
    public static function from_stored_question(
        ?object $questiondata,
        ?\context $modulecontext = null,
        ?int $questionindex = null
    ): array {
        $context = self::empty_context();

        if ($questiondata === null) {
            return $context;
        }

        $questionitemid = $questionindex === null ? null : $questionindex + 1;

        if (!editor_content::is_empty($questiondata->text ?? '')) {
            $context['hasquestion'] = true;
            $context['questiontext'] = self::format_author_html(
                (string) $questiondata->text,
                $modulecontext,
                $questionitemid
            );
        }

        if (!empty($questiondata->answers) && is_array($questiondata->answers)) {
            foreach ($questiondata->answers as $answerorder => $answer) {
                if (is_array($answer)) {
                    $answer = (object) $answer;
                }
                if (editor_content::is_empty($answer->text ?? '')) {
                    continue;
                }
                $answeritemid = null;
                if ($questionitemid !== null) {
                    $answeritemid = (int) ($questionitemid . ((int) $answerorder + 1));
                }
                $context['answers'][] = [
                    'text' => self::format_author_html((string) $answer->text, $modulecontext, $answeritemid),
                    'iscorrect' => !empty($answer->iscorrect),
                ];
            }
        }
        $context['hasanswers'] = !empty($context['answers']);

        $feedbackmap = [
            'correctfeedback' => get_string('previewcorrect', 'simplequiz2'),
            'partiallycorrectfeedback' => get_string('previewpartial', 'simplequiz2'),
            'incorrectfeedback' => get_string('previewincorrect', 'simplequiz2'),
        ];
        foreach ($feedbackmap as $field => $label) {
            $value = $questiondata->$field ?? '';
            if (editor_content::is_empty($value)) {
                continue;
            }
            $itemid = null;
            if ($questionitemid !== null) {
                if ($field === 'correctfeedback') {
                    $itemid = simplequiz2_correct_feedback_itemid($questionitemid);
                } else if ($field === 'partiallycorrectfeedback') {
                    $itemid = simplequiz2_partiallycorrect_feedback_itemid($questionitemid);
                } else {
                    $itemid = simplequiz2_incorrect_feedback_itemid($questionitemid);
                }
            }
            $context['feedbackitems'][] = [
                'label' => $label,
                'text' => self::format_author_html((string) $value, $modulecontext, $itemid),
            ];
        }
        $context['hasfeedback'] = !empty($context['feedbackitems']);

        return $context;
    }

    /**
     * Clean one author HTML field, rewriting plugin files when an item id is known.
     *
     * @param string $html Stored HTML.
     * @param \context|null $modulecontext Module context.
     * @param int|null $itemid File area item id.
     * @return string
     */
    private static function format_author_html(string $html, ?\context $modulecontext, ?int $itemid): string {
        if ($modulecontext !== null && $itemid !== null) {
            return simplequiz2_format_stored_html($html, $modulecontext, $itemid);
        }

        return simplequiz2_clean_author_html($html, $modulecontext);
    }

    /**
     * Default empty Mustache context with headings.
     *
     * @return array
     */
    public static function empty_context(): array {
        return [
            'hasquestion' => false,
            'questiontext' => '',
            'hasanswers' => false,
            'answerheading' => get_string('previewanswers', 'simplequiz2'),
            'answers' => [],
            'hasfeedback' => false,
            'feedbackheading' => get_string('previewfeedback', 'simplequiz2'),
            'feedbackitems' => [],
        ];
    }
}
