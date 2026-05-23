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
 * Helpers for normalising HTML editor content (TinyMCE and legacy Atto).
 *
 * @package    mod_simplequiz2
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplequiz2\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Editor content utilities shared by PHP save logic and unit tests.
 */
class editor_content {

    /**
     * Strip empty paragraph/break markup then trim (matches client-side edit.js logic).
     *
     * @param string|null $html Raw HTML from an editor field.
     * @return string Normalised string for empty checks.
     */
    public static function strip_for_empty_check(?string $html): string {
        if ($html === null || $html === '') {
            return '';
        }

        $stripped = preg_replace('/<\/?p[^>]*>/i', '', $html);
        $stripped = preg_replace('/<\/?br[^>]*>/i', '', $stripped ?? '');

        return trim($stripped ?? '');
    }

    /**
     * Whether editor HTML should be treated as empty (e.g. {@code <p><br></p>} from TinyMCE).
     *
     * @param string|null $html Raw HTML from an editor field.
     * @return bool
     */
    public static function is_empty(?string $html): bool {
        return self::strip_for_empty_check($html) === '';
    }
}
