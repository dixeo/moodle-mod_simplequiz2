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

namespace mod_simplequiz2\completion;

/**
 * Unit tests for custom completion rules.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_simplequiz2\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Defined custom rules list.
     */
    public function test_get_defined_custom_rules(): void {
        $this->assertSame(
            ['completionminattempts'],
            custom_completion::get_defined_custom_rules()
        );
    }

    /**
     * Sort order lists minimum attempts with standard completion rules.
     */
    public function test_get_sort_order_includes_min_attempts(): void {
        $mockcminfo = $this->getMockBuilder(\cm_info::class)
            ->disableOriginalConstructor()
            ->getMock();
        $completion = new custom_completion($mockcminfo, 0);
        $order = $completion->get_sort_order();

        $this->assertContains('completionminattempts', $order);
        $this->assertContains('completionview', $order);
    }
}
