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

namespace mod_simplequiz2;

/**
 * Keeps the plugin's copy of the network module identical to the one in local_dixeo.
 *
 * The plugin works without local_dixeo, so it ships its own amd/src/network.js. The test is
 * skipped when local_dixeo is not installed.
 *
 * @package    mod_simplequiz2
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class network_parity_test extends \advanced_testcase {
    /**
     * The copy matches local/dixeo/amd/src/network.js apart from the module docblock.
     */
    public function test_network_module_matches_local_dixeo(): void {
        $localdixeo = \core_component::get_component_directory('local_dixeo');
        $source = $localdixeo === null ? null : $localdixeo . '/amd/src/network.js';
        if ($source === null || !is_readable($source)) {
            $this->markTestSkipped('local_dixeo is not installed');
        }

        $copy = \core_component::get_component_directory('mod_simplequiz2') . '/amd/src/network.js';

        $expected = $this->normalise(file_get_contents($source));
        $actual = $this->normalise(file_get_contents($copy));

        $this->assertSame(
            $expected,
            $actual,
            'amd/src/network.js must match local/dixeo/amd/src/network.js apart from the module docblock'
        );
    }

    /**
     * Remove the module docblock and line ending differences from a module source.
     *
     * @param string $source Module source.
     * @return string
     */
    private function normalise(string $source): string {
        $source = str_replace("\r\n", "\n", $source);
        $source = preg_replace('~/\*\*(?:(?!\*/).)*?@module(?:(?!\*/).)*?\*/~s', '', $source, 1);
        return trim($source);
    }
}
