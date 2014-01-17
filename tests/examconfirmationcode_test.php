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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/gradingstudents/examconfirmationcode.php');


/**
 * Unit tests for {@link quiz_grading_students_exam_confirmation_code}
 *
 * @package    quiz_gradingstudents
 * @category   phpunit
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_report_exam_confirmation_code_testcase extends basic_testcase {
    public function test_calculate_hash() {
        $this->assertEquals('PYWF', quiz_gradingstudents_report_exam_confirmation_code::calculate_hash(
                'R335671X L120 1 12P TMA30'));

        // Example form #7168.
        $this->assertEquals('DZSD', quiz_gradingstudents_report_exam_confirmation_code::calculate_hash(
                'B7435280 SK121 1 13R ECA30'));
    }

    public function test_calculate_confirmation_code() {
        $this->assertEquals('PYWF', quiz_gradingstudents_report_exam_confirmation_code::calculate_confirmation_code(
                'R335671X', 'L120', '12P', 'TMA30'));

        // Example form #7168.
        $this->assertEquals('DZSD', quiz_gradingstudents_report_exam_confirmation_code::calculate_confirmation_code(
                'B7435280', 'SK121', '13R', 'ECA30'));
    }

    public function test_get_confirmation_code() {

        // Example form #7168.
        $quizidnumber = 'sk121-13r.eca30';
        $pi = 'B7435280';
        $cfc = 'DZSD';
        $this->assertEquals($cfc, quiz_gradingstudents_report_exam_confirmation_code::get_confirmation_code($quizidnumber, $pi));
    }
}
