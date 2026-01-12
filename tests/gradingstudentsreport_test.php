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

use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;
use quiz_gradingstudents\report_display_options;
use quiz_gradingstudents\report_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/gradingstudents/report.php');

/**
 * This class provides testable methods from quiz_gradingstudents_report by making them public
 *
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_testable_report extends quiz_gradingstudents\report_table {
    public static function normalise_state($state) {
        return parent::normalise_state($state);
    }
}

/**
 * Unit tests for {@link quiz_gradingstudents_report}
 *
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingstudentsreport_test extends advanced_testcase {

    public function test_normalise_state() {
        $this->assertEquals('needsgrading', quiz_gradingstudents_testable_report::normalise_state('needsgrading'));
        $this->assertEquals('autograded', quiz_gradingstudents_testable_report::normalise_state('graded'));
        $this->assertEquals('manuallygraded', quiz_gradingstudents_testable_report::normalise_state('mangr'));
    }

    /**
     * Verify the accurate count of manually graded, autograded, and needsgrading items in the user's attempt.
     *
     * @covers ::get_formatted_student_attempts
     */
    public function test_get_formatted_student_attempts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        // Create a course.
        $course = $generator->create_course(['shortname' => 'Course contain quiz']);
        // Create a quiz.
        $quiz = $generator->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'grade' => 10.0,
            'sumgrades' => 10.0,
        ]);
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        // Create question category cat1.
        $cat1 = $questiongenerator->create_question_category();
        // Add two questions to cat1.
        $es = $questiongenerator->create_question('essay', 'plain', ['category' => $cat1->id]);
        $tf = $questiongenerator->create_question('truefalse', null, ['name' => 'TF1', 'category' => $cat1->id]);
        quiz_add_quiz_question($es->id, $quiz, 0 , 5);
        quiz_add_quiz_question($tf->id, $quiz, 0 , 5);

        // Create some students and enrol them in the course.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id);
        $generator->enrol_user($student2->id, $course->id);
        $generator->enrol_user($student3->id, $course->id);

        $attempts = [
            [$quiz, $student1],
            [$quiz, $student2],
            [$quiz, $student3],
        ];
        $alreadymanuallygraded = false;
        foreach ($attempts as $attempt) {
            [$quiz, $student] = $attempt;
            $quizobj = quiz_settings::create($quiz->id, $student->id);
            $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
            $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
            $timestamp = time();
            // Create the new attempt and initialize the question sessions.
            $quizobj = quiz_settings::create($quiz->id, $student->id);
            $attempt = quiz_create_attempt($quizobj, 1, null, $timestamp, false, $student->id);
            $attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timestamp);
            $attempt = quiz_attempt_save_started($quizobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = quiz_attempt::create($attempt->id);
            $attemptobj->process_submitted_actions($timestamp + 300, false, [
                1 => ['answer' => 'My essay by ' . $student->firstname, 'answerformat' => FORMAT_PLAIN],
                2 => ['answer' => true]]);
            $attemptobj->process_finish($timestamp + 600, false);
            if ($alreadymanuallygraded) {
                continue;
            }
            // Manually grade for the first attempt only.
            $quba = $attemptobj->get_question_usage();
            $quba->get_question_attempt(1)->manual_grade(
                'Comment', 3, FORMAT_HTML, $timestamp + 1200);
            question_engine::save_questions_usage_by_activity($quba);
            $update = new \stdClass();
            $update->id = $attemptobj->get_attemptid();
            $update->timemodified = $timestamp + 1200;
            $update->sumgrades = $quba->get_total_mark();
            $DB->update_record('quiz_attempts', $update);
            // We only want the first attempt to be manually graded.
            $alreadymanuallygraded = true;
        }

        $context = \context_module::instance($quiz->cmid);
        $cm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $studentsjoins = get_enrolled_with_capabilities_join($context, '', ['mod/quiz:attempt', 'mod/quiz:reviewmyattempts']);
        // Set the options.
        $reportoptions = new report_display_options('gradingstudents', $quiz, $cm, $course);
        // Setup the table and query the database so we can get the raw data.
        $table = new report_table($quiz, $context, null, $reportoptions, new \core\dml\sql_join(), $studentsjoins,
            [1 => $es, 2 => $tf], $reportoptions->get_url());
        $table->cm = $cm;
        $table->define_table($studentsjoins, $reportoptions);
        $table->setup();
        $table->query_db(20, false);

        // Using Reflection to test the protected function.
        $class = new ReflectionClass('quiz_gradingstudents\report_table');
        $method = $class->getMethod('get_formatted_student_attempts');
        $method->setAccessible(true);
        $results = $method->invoke($table, $table->rawdata);

        // We have three attempts from three users with two questions.
        // The first attempt from student 1 has been manually graded.
        // Second and third attempt have essay question that need manually grading and T/f question is auto graded.
        $expected = [
            0 => [
                'manuallygraded' => 1,
                'autograded' => 1,
                'all' => 2,
                'needsgrading' => 0,
                'userid' => $student1->id,
            ],
            1 => [
                'manuallygraded' => 0,
                'autograded' => 1,
                'all' => 2,
                'needsgrading' => 1,
                'userid' => $student2->id,
            ],
            2 => [
                'manuallygraded' => 0,
                'autograded' => 1,
                'all' => 2,
                'needsgrading' => 1,
                'userid' => $student3->id,
            ],
        ];
        $index = 0;
        foreach ($results as $r) {
            $this->assertEquals($expected[$index]['manuallygraded'], $r->manuallygraded);
            $this->assertEquals($expected[$index]['autograded'], $r->autograded);
            $this->assertEquals($expected[$index]['needsgrading'], $r->needsgrading);
            $this->assertEquals($expected[$index]['all'], $r->all);
            $this->assertEquals($expected[$index]['userid'], $r->userid);
            $index++;
        }
    }
}
