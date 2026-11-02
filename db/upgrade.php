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
 * Simplequiz module upgrade code
 *
 * @package    mod_simplequiz2
 * @copyright  2023 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin upgrade function
 *
 * @param int $oldversion
 * @return void
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 * @throws downgrade_exception
 * @throws upgrade_exception
 */
function xmldb_simplequiz2_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Create table for simplequiz user attempt info.
    if ($oldversion < 2023010500) {
        $targettablename = 'simplequiz2_attempts';
        if ($dbman->table_exists($targettablename)) {
            $table = new xmldb_table($targettablename);
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('simplequiz2_attempts');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cntattempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timefirstattempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timelastattempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('completed', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('foreigncmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $table->add_key('foreignuserid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('simplequiz-userid-attempt', XMLDB_INDEX_NOTUNIQUE, [
            'cmid',
            'userid',
        ]);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023010500, 'mod', 'simplequiz2');
    }

    // Create table to store attempts data.
    if ($oldversion < 2023010501) {
        $table = new xmldb_table('simplequiz2_attempt_data');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answers', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('foreigncmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $table->add_key('foreignuserid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('simplequiz-userid-attempt', XMLDB_INDEX_NOTUNIQUE, [
            'cmid',
            'userid',
        ]);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023010501, 'mod', 'simplequiz2');
    }

    // Add field for attempts number completion.
    if ($oldversion < 2023011000) {
        $table = new xmldb_table('simplequiz2');
        $field = new xmldb_field('completionminattempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2023011000, 'mod', 'simplequiz2');
    }

    if ($oldversion < 2026032811) {
        // Backup/restore: include user attempt tables when user data is selected (no schema change).
        upgrade_plugin_savepoint(true, 2026032811, 'mod', 'simplequiz2');
    }

    if ($oldversion < 2026110200) {
        // Track when an attempt is finished so it is graded and counted only once.
        $table = new xmldb_table('simplequiz2_attempt_data');
        $field = new xmldb_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Existing attempts that hold answers cannot be resumed by the player: close them.
        $DB->execute(
            "UPDATE {simplequiz2_attempt_data}
                SET timefinished = timecreated
              WHERE timefinished = 0 AND " . $DB->sql_compare_text('answers', 10) . " <> :empty",
            ['empty' => '[]']
        );

        upgrade_plugin_savepoint(true, 2026110200, 'mod', 'simplequiz2');
    }

    if ($oldversion < 2026110201) {
        // Move question, answer and feedback files to non-overlapping file item ids.
        $sql = "SELECT sq.id, sq.questions, cm.id AS cmid
                  FROM {simplequiz2} sq
                  JOIN {course_modules} cm ON cm.instance = sq.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname";
        $rs = $DB->get_recordset_sql($sql, ['modname' => 'simplequiz2']);
        foreach ($rs as $record) {
            $context = context_module::instance($record->cmid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $questions = json_decode((string) $record->questions, true);
            \mod_simplequiz2\util\file_itemid::migrate_legacy_files($context, is_array($questions) ? $questions : []);
        }
        $rs->close();

        upgrade_plugin_savepoint(true, 2026110201, 'mod', 'simplequiz2');
    }

    return true;
}
