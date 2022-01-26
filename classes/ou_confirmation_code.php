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
 * Implementation of the OU's 'Confirmation code' algorithm.
 *
 * @package    quiz_gradingstudents
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * This class implements the OU's 'Confirmation code' algorithm for end-of-course assessed tasks.
 *
 * Graders need to enter this into the grading system as a
 * checksum to ensure that they are entering marks for the right student / task.
 *
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_ou_confirmation_code {
    /** @var int value used in the calculation. */
    const HASH_START = 5381;
    /** @var int value used in the calculation. */
    const MODULUS    = 882377;
    /** @var int value used in the calculation. */
    const NUM_CHARS  = 21;
    /** @var string characters used to display the code. */
    const CODE_CHARS = 'BCDFGHJKLMNPQRSTVWXYZ';
    /** @var int value used in the calculation. */
    const NUM_CODES  = 194481; // This equals 21*21*21*21.

    /**
     * Can a quiz with this idnumber have a confirmation code?
     *
     * @param string|null $quizidnumber the quiz idnumber.
     * @return string|null string like eca01 or exm01 if it can, null if it can't.
     */
    public static function quiz_can_have_confirmation_code(?string $quizidnumber): ?string {
        if (!preg_match('~\w+-\w+\.((?i:eca|exm|icme|prj)\d+)~', $quizidnumber, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Check for the correct idnumber and generate a confirmation code
     *
     * @param stdClass|cm_info $cm quiz cm.
     * @param stdClass $user the user object for the student. (Only id and idnumber required.)
     * @return null|string the computed code if relevant, else null.
     */
    public static function get_confirmation_code($cm, stdClass $user): ?string {
        $task = self::quiz_can_have_confirmation_code($cm->idnumber);
        if (!$task) {
            return null;
        }

        [$courseshortname] = explode('.', $cm->idnumber, 2);
        [$module, $pres] = explode('-', $courseshortname, 2);
        if (!$module || !$pres) {
            return null;
        }
        $task = core_text::strtoupper($task);
        $module = core_text::strtoupper($module);
        $pres = core_text::strtoupper($pres);

        [$module, $pres] = self::update_for_variant($module, $pres, $cm, $user);
        return self::calculate_confirmation_code($user->idnumber, $module, $pres, $task, 1);
    }

    /**
     * Compute the confirmation code for a student for on a task.
     *
     * @param string $pi the student's PI.
     * @param string $module the module code, e.g. B747.
     * @param string $pres the short presentation code, e.g. 13B.
     * @param string $task the task name. E.g. EXM01 or EMA01.
     * @param int $version defaults to 1.
     * @return string The confirmation code.
     */
    public static function calculate_confirmation_code(string $pi, string $module, string $pres,
            string $task, int $version): string {
        return self::calculate_hash($pi . $module . $version . $pres . $task);
    }

    /**
     * The raw hash algorithm.
     *
     * @param string $string the input string.
     * @return string The confirmation code.
     */
    public static function calculate_hash(string $string): string {
        $cleanstring = str_replace(' ', '', $string);

        $hash = self::HASH_START;
        $inputlength = strlen($cleanstring);
        for ($i = 0; $i < $inputlength; $i += 1) {
            $nextchar = ord(substr($cleanstring, $i, 1));
            $hash += $nextchar;
            if ($nextchar % 2) {
                $hash *= 13;
            } else {
                $hash *= 7;
            }
            $hash %= self::MODULUS;
        }

        $hash %= self::NUM_CODES;
        $code = '';
        for ($i = 0; $i < 4; $i += 1) {
            $nextchar = $hash % self::NUM_CHARS;
            $code .= substr(self::CODE_CHARS, $nextchar, 1);
            $hash = ($hash - $nextchar) / self::NUM_CHARS;
        }

        return $code;
    }

    /**
     * Update the effective $module, $pres, if the user appears to be in a variant.
     *
     * @param string $module from quiz idnumbe.
     * @param string $pres from quiz idnumbe.
     * @param stdClass|cm_info $cm quiz cm
     * @param stdClass $user user.
     * @return string[] array with two elements, [$module, $pres].
     */
    protected static function update_for_variant(string $module, string $pres, $cm, stdClass $user): array {
        global $DB;

        $variantgroups = $DB->get_records_sql("
                SELECT g.id, g.name
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE gm.userid = ? AND g.courseid = ?
                   AND g.name LIKE '% variant group'
                ORDER BY name DESC
            ", [$user->id, $cm->course], 0, 1);

        if (!$variantgroups) {
            return [$module, $pres];
        }

        $groupname = reset($variantgroups)->name;
        [$modulepres] = explode(' ', $groupname, 2);
        return explode('-', $modulepres, 2);
    }
}
