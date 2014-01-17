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
 * Quiz grading by students report upgrade script.
 *
 * @package   quiz_gradingstudents
 * @copyright 2013 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Quiz grading by students report upgrade function.
 * @param number $oldversion
 */
function xmldb_quiz_gradingstudents_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2013071500) {

        // Add quiz_reports record.
        $record = new stdClass();
        $record->name         = 'gradingstudents';
        $record->displayorder = 5500;
        $record->capability   = 'mod/quiz:grade';

        $DB->insert_record('quiz_reports', $record);

        // Quiz grading by students savepoint reached.
        upgrade_plugin_savepoint(true, 2013071500, 'quiz', 'gradingstudents');
    }

    return true;
}
