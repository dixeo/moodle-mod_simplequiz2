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
 * Mustache context builders for the mod_simplequiz2 edit form templates.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_form_context {

    /**
     * Context for mod_simplequiz2/question_block_start.
     *
     * @param int $questionid Question index.
     * @param int $displaynumber Visible question number (1-based).
     * @param bool $hascontent Whether the slot has any stored content.
     * @param object|null $questiondata Stored question when editing.
     * @param \renderer_base $output Page renderer.
     * @return array
     */
    public static function question_block_start(
        int $questionid,
        int $displaynumber,
        bool $hascontent,
        ?object $questiondata,
        \renderer_base $output
    ): array {
        return [
            'questionid' => $questionid,
            'title' => get_string('formquestiontitle', 'simplequiz2', $displaynumber),
            'hascontent' => $hascontent,
            'summaryhtml' => $output->render_from_template(
                'mod_simplequiz2/question_summary',
                question_summary_context::from_stored_question($questiondata)
            ),
            'buttons' => self::toolbar_buttons($questionid),
            'errornotenough' => get_string('notenoughanswerserror', 'simplequiz2'),
            'errornoright' => get_string('norightanswererror', 'simplequiz2'),
        ];
    }

    /**
     * Context for mod_simplequiz2/question_block_end.
     *
     * @param int $questionid Question index.
     * @return array
     */
    public static function question_block_end(int $questionid): array {
        return [
            'questionid' => $questionid,
        ];
    }

    /**
     * Toolbar button contexts for one question.
     *
     * @param int $questionid Question index.
     * @return array
     */
    public static function toolbar_buttons(int $questionid): array {
        $buttons = [
            self::toolbar_button(
                'edit-question',
                'fa-pencil',
                get_string('editquestion', 'simplequiz2'),
                $questionid
            ),
            self::toolbar_button(
                'save-question simplequiz2-btn-save',
                'fa-check',
                get_string('savequestion', 'simplequiz2'),
                $questionid,
                ['hidden' => true, 'showlabel' => true]
            ),
            self::toolbar_button(
                'discard-question simplequiz2-btn-discard',
                'fa-times',
                get_string('discardquestion', 'simplequiz2'),
                $questionid,
                ['hidden' => true, 'showlabel' => true]
            ),
        ];

        if ($questionid > 0) {
            $buttons[] = self::toolbar_button(
                'delete-simplequestion',
                'fa-trash',
                get_string('deletequestion', 'simplequiz2'),
                $questionid
            );
        }

        $buttons[] = self::toolbar_button(
            'add-simplequestion',
            'fa-plus',
            get_string('addquestion', 'simplequiz2'),
            $questionid,
            ['attrs' => [['name' => 'data-questionorder', 'value' => (string) $questionid]]]
        );

        return $buttons;
    }

    /**
     * Build one toolbar button context.
     *
     * @param string $class CSS class suffix.
     * @param string $icon Font Awesome icon class (without fa prefix).
     * @param string $label Accessible label and tooltip.
     * @param int $questionid Question index.
     * @param array $options Optional hidden, showlabel, attrs.
     * @return array
     */
    private static function toolbar_button(
        string $class,
        string $icon,
        string $label,
        int $questionid,
        array $options = []
    ): array {
        $showlabel = !empty($options['showlabel']);
        $attrs = [];
        foreach ($options['attrs'] ?? [] as $attr) {
            $attrs[] = [
                'name' => $attr['name'],
                'value' => $attr['value'],
            ];
        }

        return [
            'class' => $class,
            'icon' => $icon,
            'label' => $label,
            'questionid' => $questionid,
            'hidden' => !empty($options['hidden']),
            'showlabel' => $showlabel,
            'icononly' => !$showlabel,
            'attrs' => $attrs,
        ];
    }
}
