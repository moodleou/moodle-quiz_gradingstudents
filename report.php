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
use mod_quiz\local\reports\attempts_report;
use quiz_gradingstudents\report_table;
use quiz_gradingstudents\report_display_options;
use mod_quiz\quiz_attempt;

/**
 * Quiz report to help teachers manually grade questions by students.
 *
 * This report basically provides two screens:
 * - List student attempts that might need manual grading / regarding.
 * - Provide a UI to grade all questions of a particular quiz attempt.
 *
 * @package   quiz_gradingstudents
 * @copyright 2013 The Open university
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_report extends attempts_report {

    public function display($quiz, $cm, $course) {
        global $OUTPUT;
        // Check permissions.
        $this->context = context_module::instance($cm->id);
        require_capability('mod/quiz:grade', $this->context);

        // Get the URL options.
        $options = new report_display_options('gradingstudents', $quiz, $cm, $course);
        $grade = $options->grade;
        if (!in_array($options->grade, ['all', 'needsgrading', 'autograded', 'manuallygraded'])) {
            $grade = null;
        }
        $baseurl = $this->base_url($cm);
        // Process any submitted data.
        if (data_submitted() && confirm_sesskey() && $this->validate_submitted_marks($options->usageid, $options->slots)) {
            $this->process_submitted_data($options->usageid, $quiz, $cm, $course);
            redirect($baseurl);
        }

        [$currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins] = $this->get_students_joins($cm, $course);
        // Start output.
        $this->print_header_and_tabs($cm, $course, $quiz, 'gradingstudents');
        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);
        $table = new report_table($quiz, $this->context, $this->qmsubselect,
            $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $table->cm = $cm;

        $hasquestions = quiz_has_questions($quiz->id);
        // What sort of page to display?
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        } else if (!$options->usageid) {
            $table->define_table($allowedjoins, $options);
            if (groups_get_activity_groupmode($cm)) {
                // Groups is being used.
                groups_print_activity_menu($cm, $table->baseurl);
            }
            // Get the current group for the user looking at the report.
            if ($currentgroup == self::NO_GROUPS_ALLOWED) {
                echo $OUTPUT->notification(get_string('notingroup'));
                return;
            }
            if ($options->includeauto) {
                $linktext = get_string('hideautomaticallygraded', 'quiz_gradingstudents');
            } else {
                $linktext = get_string('alsoshowautomaticallygraded', 'quiz_gradingstudents');
            }
            if ($options->includeauto) {
                $baseurl->remove_params('includeauto');
            } else {
                $baseurl->param('includeauto', 1);
            }
            echo $OUTPUT->heading(get_string('questionsthatneedgrading', 'quiz_gradingstudents'));
            echo html_writer::tag('p', html_writer::link($baseurl, $linktext), ['class' => 'toggleincludeauto']);
            $table->out(0, $table->use_initials);
        } else {
            $table->display_grading_interface($options, $grade, $allowedjoins);
        }
        return true;

    }

    /**
     * Validate submitted marks before updating the database
     *
     * @param int $usageid usage id of the quiz attempt.
     * @param string $slots comma-separated list of slots.
     * @return bool whether all the submitted marks are valid.
     */
    protected function validate_submitted_marks($usageid, $slots) {
        if (!$usageid) {
            return false;
        }
        if (!$slots) {
            $slots = [];
        } else {
            $slots = explode(',', $slots);
        }

        foreach ($slots as $slot) {
            if (!question_engine::is_manual_grade_in_range($usageid, $slot)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update the quiz attempt with the new grades.
     *
     * @param int $usageid usage id of the quiz attempt being graded.
     * @param object $quiz the current quiz of the report.
     * @param object $cm the current course module object
     * @param object $course current course.
     */
    protected function process_submitted_data($usageid, $quiz, $cm, $course): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $attempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid]);
        $attemptobj = new quiz_attempt($attempt, $quiz, $cm, $course);
        $attemptobj->process_submitted_actions(time());
        $transaction->allow_commit();
    }

    /**
     * Return the base URL of the report.
     *
     * @return moodle_url the URL.
     */
    public static function base_url($cm) {
        return new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'gradingstudents']);
    }
}
