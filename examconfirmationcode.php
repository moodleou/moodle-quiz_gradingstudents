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
class quiz_gradingstudents_report_exam_confirmation_code {
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
     * Check for the correct idnumber and generate a confirmation code
     *
     * @param string $quizidnumber quiz idnumber.
     * @param string $pi student user's idnumber.
     * @param int $version (optional) defaults to 1.
     * @return null|string the computed code if relevant, else null.
     */
    public static function get_confirmation_code($quizidnumber, $pi, $version = 1) {
        if (!preg_match('~\w+-\w+\.((?i:eca|exm)\d+)~', $quizidnumber, $matches)) {
            return null;
        }
        list($courseshortname, $notused) = explode('.', $quizidnumber, 2);
        list($module, $pres) = explode('-', $courseshortname, 2);
        $task = core_text::strtoupper($matches[1]);
        $module = core_text::strtoupper($module);
        $pres = core_text::strtoupper($pres);
        return self::calculate_confirmation_code($pi, $module, $pres, $task, $version);
    }

    /**
     * Compute the confirmation code for a student for on a task.
     * @param string $pi the student's PI.
     * @param string $module the module code, e.g. B747.
     * @param string $pres the short presentation code, e.g. 13B.
     * @param string $task the task name. E.g. EXM01 or EMA01.
     * @param int $version defaults to 1.
     * @return string The confirmation code.
     */
    public static function calculate_confirmation_code($pi, $module, $pres, $task, $version = 1) {
        return self::calculate_hash($pi . $module . $version . $pres . $task);
    }

    /**
     * The raw hash algorithm.
     * @param string $string the input string.
     * @return string The confirmation code.
     */
    public static function calculate_hash($string) {
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
}
