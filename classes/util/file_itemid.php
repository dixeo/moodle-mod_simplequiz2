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

namespace mod_simplequiz2\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * File item ids in the mod_simplequiz2 "data" file area.
 *
 * Every editor field of a question owns one item id:
 * BASE + questionorder * SLOTS_PER_QUESTION + slot, where slot 0 is the question
 * text, 1..MOD_SIMPLEQUIZ2_MAX_ANSWER_NB the answers and 6..8 the combined feedback.
 * BASE keeps every id above the item ids written by releases before 2.0.3
 * (question order + 1, concatenated answer ids, order * 10 + 6..8), so those
 * files can be recognised and moved by {@see self::migrate_legacy_files()}.
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_itemid {
    /** @var int Lowest item id used by the current scheme. */
    public const BASE = 1000;

    /** @var int Item ids reserved per question. */
    public const SLOTS_PER_QUESTION = 100;

    /** @var int Slot of the question text. */
    public const SLOT_TEXT = 0;

    /** @var int Slot of the correct feedback. */
    public const SLOT_CORRECT_FEEDBACK = 6;

    /** @var int Slot of the incorrect feedback. */
    public const SLOT_INCORRECT_FEEDBACK = 7;

    /** @var int Slot of the partially correct feedback. */
    public const SLOT_PARTIAL_FEEDBACK = 8;

    /**
     * Item id of the question text.
     *
     * @param int $questionorder Zero-based question index.
     * @return int
     */
    public static function question_text(int $questionorder): int {
        return self::build($questionorder, self::SLOT_TEXT);
    }

    /**
     * Item id of one answer.
     *
     * @param int $questionorder Zero-based question index.
     * @param int $answerorder Zero-based answer index.
     * @return int
     */
    public static function answer(int $questionorder, int $answerorder): int {
        if ($answerorder < 0 || $answerorder >= MOD_SIMPLEQUIZ2_MAX_ANSWER_NB) {
            throw new \coding_exception('Answer index out of range: ' . $answerorder);
        }
        return self::build($questionorder, $answerorder + 1);
    }

    /**
     * Item id of a combined feedback field.
     *
     * @param int $questionorder Zero-based question index.
     * @param string $outcome One of the MOD_SIMPLEQUIZ2_FEEDBACK_* constants.
     * @return int
     */
    public static function feedback(int $questionorder, string $outcome): int {
        switch ($outcome) {
            case MOD_SIMPLEQUIZ2_FEEDBACK_CORRECT:
                return self::build($questionorder, self::SLOT_CORRECT_FEEDBACK);
            case MOD_SIMPLEQUIZ2_FEEDBACK_PARTIAL:
                return self::build($questionorder, self::SLOT_PARTIAL_FEEDBACK);
            default:
                return self::build($questionorder, self::SLOT_INCORRECT_FEEDBACK);
        }
    }

    /**
     * Whether a question (and optionally answer) position has an item id.
     *
     * @param int $questionorder Zero-based question index.
     * @param int|null $answerorder Zero-based answer index, or null for the question itself.
     * @return bool
     */
    public static function has_itemid(int $questionorder, ?int $answerorder = null): bool {
        if ($questionorder < 0 || $questionorder >= MOD_SIMPLEQUIZ2_MAX_QUESTION_NB) {
            return false;
        }
        return $answerorder === null || ($answerorder >= 0 && $answerorder < MOD_SIMPLEQUIZ2_MAX_ANSWER_NB);
    }

    /**
     * Whether an item id belongs to a combined feedback field.
     *
     * @param int $itemid Item id.
     * @return bool
     */
    public static function is_feedback(int $itemid): bool {
        if ($itemid < self::BASE) {
            return false;
        }
        $slot = ($itemid - self::BASE) % self::SLOTS_PER_QUESTION;
        return in_array($slot, [self::SLOT_CORRECT_FEEDBACK, self::SLOT_INCORRECT_FEEDBACK, self::SLOT_PARTIAL_FEEDBACK], true);
    }

    /**
     * Zero-based question index an item id belongs to.
     *
     * @param int $itemid Item id.
     * @return int|null Null when the id is not part of the current scheme.
     */
    public static function question_order(int $itemid): ?int {
        if ($itemid < self::BASE) {
            return null;
        }
        return intdiv($itemid - self::BASE, self::SLOTS_PER_QUESTION);
    }

    /**
     * Compose an item id.
     *
     * @param int $questionorder Zero-based question index.
     * @param int $slot Field slot.
     * @return int
     */
    private static function build(int $questionorder, int $slot): int {
        if ($questionorder < 0 || $questionorder >= MOD_SIMPLEQUIZ2_MAX_QUESTION_NB) {
            throw new \coding_exception('Question index out of range: ' . $questionorder);
        }
        return self::BASE + $questionorder * self::SLOTS_PER_QUESTION + $slot;
    }

    /**
     * Move files stored under the pre-2.0.3 item ids to the current item ids.
     *
     * The old scheme shared item ids between fields (question 11 text and question 1
     * answer 1 were both item 11), so each file is copied to every field whose stored
     * HTML references it by path and name. All files below {@see self::BASE} are then
     * removed. Safe to run more than once.
     *
     * @param \context $context Module context.
     * @param array $questions Decoded questions keyed by zero-based order.
     * @return void
     */
    public static function migrate_legacy_files(\context $context, array $questions): void {
        $fs = get_file_storage();

        $legacyfiles = [];
        $files = $fs->get_area_files($context->id, 'mod_simplequiz2', 'data', false, 'itemid, filepath, filename', false);
        foreach ($files as $file) {
            if ((int) $file->get_itemid() < self::BASE) {
                $legacyfiles[(int) $file->get_itemid()][] = $file;
            }
        }
        if (!$legacyfiles) {
            return;
        }

        foreach ($questions as $order => $question) {
            $order = (int) $order;
            if ($order < 0 || $order >= MOD_SIMPLEQUIZ2_MAX_QUESTION_NB) {
                continue;
            }
            $question = (object) $question;
            $questionitemid = $order + 1;

            $fields = [[(string) ($question->text ?? ''), $questionitemid, self::question_text($order)]];

            $answers = isset($question->answers) ? array_values((array) $question->answers) : [];
            foreach ($answers as $answerorder => $answer) {
                if ($answerorder >= MOD_SIMPLEQUIZ2_MAX_ANSWER_NB) {
                    break;
                }
                $answer = (object) $answer;
                $fields[] = [
                    (string) ($answer->text ?? ''),
                    (int) ($questionitemid . ($answerorder + 1)),
                    self::answer($order, $answerorder),
                ];
            }

            $feedbacks = [
                'correctfeedback' => [6, MOD_SIMPLEQUIZ2_FEEDBACK_CORRECT],
                'incorrectfeedback' => [7, MOD_SIMPLEQUIZ2_FEEDBACK_INCORRECT],
                'partiallycorrectfeedback' => [8, MOD_SIMPLEQUIZ2_FEEDBACK_PARTIAL],
            ];
            foreach ($feedbacks as $field => [$legacyslot, $outcome]) {
                $fields[] = [
                    (string) ($question->$field ?? ''),
                    $questionitemid * 10 + $legacyslot,
                    self::feedback($order, $outcome),
                ];
            }

            foreach ($fields as [$html, $legacyitemid, $newitemid]) {
                if (empty($legacyfiles[$legacyitemid]) || strpos($html, '@@PLUGINFILE@@') === false) {
                    continue;
                }
                $references = self::referenced_paths($html);
                foreach ($legacyfiles[$legacyitemid] as $file) {
                    $path = $file->get_filepath() . $file->get_filename();
                    if (!in_array($path, $references, true)) {
                        continue;
                    }
                    if (
                        $fs->file_exists(
                            $context->id,
                            'mod_simplequiz2',
                            'data',
                            $newitemid,
                            $file->get_filepath(),
                            $file->get_filename()
                        )
                    ) {
                        continue;
                    }
                    $fs->create_file_from_storedfile(['itemid' => $newitemid], $file);
                }
            }
        }

        foreach ($legacyfiles as $itemid => $files) {
            $fs->delete_area_files($context->id, 'mod_simplequiz2', 'data', $itemid);
        }
    }

    /**
     * Decoded file paths referenced through @@PLUGINFILE@@ in some HTML.
     *
     * @param string $html Stored HTML.
     * @return string[] Paths such as "/image.png".
     */
    private static function referenced_paths(string $html): array {
        preg_match_all('/@@PLUGINFILE@@(\/[^"\'\s>?#]*)/', $html, $matches);
        $paths = [];
        foreach ($matches[1] as $match) {
            $paths[] = rawurldecode($match);
        }
        return array_values(array_unique($paths));
    }
}
