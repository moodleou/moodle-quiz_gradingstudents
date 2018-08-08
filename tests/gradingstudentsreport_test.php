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
 * Unit tests for {@link quiz_gradingstudents_report}
 *
 * @package    quiz_gradingstudents
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/gradingstudents/report.php');

/**
 * This class provides testable methods from quiz_gradingstudents_report by making them public
 *
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_testable_report extends quiz_gradingstudents_report {
    public function normalise_state($state) {
        return parent::normalise_state($state);
    }
}


/**
 * Unit tests for {@link quiz_gradingstudents_report}
 *
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_report_testcase extends basic_testcase {
    /** @var quiz_gradingstudents_testable_report report instance to test. */
    protected $report;

    public function setUp() {
        $this->report = new quiz_gradingstudents_testable_report();
    }

    public function tearDown() {
        $this->report = null;
    }

    public function test_normalise_state() {
        $this->assertEquals('needsgrading', $this->report->normalise_state('needsgrading'));
        $this->assertEquals('autograded', $this->report->normalise_state('graded'));
        $this->assertEquals('manuallygraded', $this->report->normalise_state('mangr'));
    }

}
