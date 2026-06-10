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
     * @param object|null $questiondata Question object from simplequiz JSON.
     * @return array Mustache context.
     */
    public static function from_stored_question(?object $questiondata): array {
        $context = self::empty_context();

        if ($questiondata === null) {
            return $context;
        }

        if (!editor_content::is_empty($questiondata->text ?? '')) {
            $context['hasquestion'] = true;
            $context['questiontext'] = format_text(
                $questiondata->text,
                FORMAT_HTML,
                ['noclean' => true, 'para' => false]
            );
        }

        if (!empty($questiondata->answers) && is_array($questiondata->answers)) {
            foreach ($questiondata->answers as $answer) {
                if (is_array($answer)) {
                    $answer = (object) $answer;
                }
                if (editor_content::is_empty($answer->text ?? '')) {
                    continue;
                }
                $context['answers'][] = [
                    'text' => format_text($answer->text, FORMAT_HTML, ['noclean' => true, 'para' => false]),
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
            $context['feedbackitems'][] = [
                'label' => $label,
                'text' => format_text($value, FORMAT_HTML, ['noclean' => true, 'para' => false]),
            ];
        }
        $context['hasfeedback'] = !empty($context['feedbackitems']);

        return $context;
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
