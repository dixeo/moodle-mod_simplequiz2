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

/**
 * Unit tests for editor_content helper (TinyMCE empty markup).
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_simplequiz2\util\editor_content
 */
final class editor_content_test extends \advanced_testcase {

    /**
     * @dataProvider empty_html_provider
     * @param string|null $html
     * @param bool $expected
     */
    public function test_is_empty(?string $html, bool $expected): void {
        $this->assertSame($expected, editor_content::is_empty($html));
    }

    /**
     * @return array
     */
    public static function empty_html_provider(): array {
        return [
            'null' => [null, true],
            'empty string' => ['', true],
            'whitespace' => ['   ', true],
            'tiny empty paragraph' => ['<p><br></p>', true],
            'tiny empty with break' => ['<p></p><br>', true],
            'plain text' => ['Hello', false],
            'paragraph with text' => ['<p>Answer A</p>', false],
            'br only stripped' => ['<br><br>', true],
        ];
    }

    /**
     * strip_for_empty_check removes p/br wrappers but keeps inner text.
     */
    public function test_strip_for_empty_check(): void {
        $this->assertSame('Hello', editor_content::strip_for_empty_check('<p>Hello</p>'));
        $this->assertSame('A &amp; B', editor_content::strip_for_empty_check('<p>A &amp; B</p><br>'));
    }
}
