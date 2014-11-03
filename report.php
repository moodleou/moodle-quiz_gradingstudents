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
 * This file defines the quiz manual grading by students report class.
 *
 * @package   quiz_gradingstudents
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Quiz report to help teachers manually grade questions by students.
 *
 * This report basically provides two screens:
 * - List student attempts that might need manual grading / regarding.
 * - Provide a UI to grade all questions of a particular quiz attempt.
 *
 * @copyright 2013 The Open university
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_gradingstudents_report extends quiz_default_report {

    protected $viewoptions = array();
    protected $questions;
    protected $course;
    protected $cm;
    protected $quiz;
    protected $context;
    protected $users;

    public function display($quiz, $cm, $course) {
        global $CFG, $DB, $PAGE;

        $this->quiz = $quiz;
        $this->cm = $cm;
        $this->course = $course;

        // Get the URL options.
        $slot = optional_param('slot', null, PARAM_INT);
        $grade = optional_param('grade', null, PARAM_ALPHA);
        $usageid = optional_param('usageid', 0, PARAM_INT);
        $slots = optional_param('slots', '', PARAM_SEQUENCE);
        $includeauto = optional_param('includeauto', false, PARAM_BOOL);
        if (!in_array($grade, array('all', 'needsgrading', 'autograded', 'manuallygraded'))) {
            $grade = null;
        }

        // Assemble the options requried to reload this page.
        if ($includeauto) {
            $this->viewoptions['includeauto'] = 1;
        }

        // Check permissions.
        $this->context = context_module::instance($cm->id);
        require_capability('mod/quiz:grade', $this->context);

        // Get the list of questions in this quiz.
        $this->questions = quiz_report_get_significant_questions($quiz);

        // Process any submitted data.
        if ($data = data_submitted() && confirm_sesskey() && $this->validate_submitted_marks($usageid, $slots)) {
            $this->process_submitted_data($usageid);
            redirect(new moodle_url('/mod/quiz/report.php',
                array('id' => $this->cm->id, 'mode' => 'gradingstudents')));
        }

        // Get the group, and the list of significant users.
        $this->currentgroup = $this->get_current_group($cm, $course, $this->context);
        if ($this->currentgroup == self::NO_GROUPS_ALLOWED) {
            $this->users = array();
        } else {
            $this->users = get_users_by_capability($this->context,
                    array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'), '', '', '', '',
                    $this->currentgroup);
        }

        $hasquestions = quiz_has_questions($quiz->id);;

        // Start output.
        $this->print_header_and_tabs($cm, $course, $quiz, 'gradingstudents');

        // What sort of page to display?
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);

        } else if (!$usageid) {
            $this->display_index($includeauto);

        } else {
            $this->display_grading_interface($usageid, $slots, $grade);
        }
        return true;
    }

    /**
     * Return the base URL of the report.
     * @return string the URL.
     */
    protected function base_url() {
        return new moodle_url('/mod/quiz/report.php',
                array('id' => $this->cm->id, 'mode' => 'gradingstudents'));
    }

    /**
     * Get the URL of the front page of the report that lists all attempts.
     * @param $includeauto if not given, use the current setting, otherwise,
     *      force a paricular value of includeauto in the URL.
     * @return string the URL.
     */
    protected function list_questions_url($includeauto = null) {
        $url = $this->base_url();

        $url->params($this->viewoptions);

        if ($includeauto !== null) {
            if ($includeauto) {
                $url->param('includeauto', 1);
            } else {
                $url->remove_params('includeauto');
            }
        }
        return $url;
    }

    /**
     * Return url for appropriate questions
     * @param int $usageid
     * @param int $slots
     * @param string $grade
     */
    protected function grade_question_url($usageid, $slots, $grade) {
        $url = $this->base_url();
        $url->params(array('usageid' => $usageid, 'slots' => $slots, 'grade' => $grade));
        $url->params($this->viewoptions);
        return $url;
    }

    /**
     * Return formatted output
     * @param object $attempt
     * @param  string $type
     * @param string $gradestring
     * @return string, formatted string.
     */
    protected function format_count_for_table($attempt, $type, $gradestring) {
        $counts = $attempt->$type;
        $slots = array();
        if ($counts > 0) {
            foreach ($attempt->questions as $id => $question) {
                if ($type === $this->normalise_state($question->state) || $type === 'all') {
                    $slots[] = $question->slot;
                }
            }
        }
        $slots = implode(',', $slots);
        $result = $counts;
        if ($counts > 0) {
            $result .= ' ' . html_writer::link($this->grade_question_url(
                    $attempt->uniqueid, $slots, $type),
                    get_string($gradestring, 'quiz_gradingstudents'),
                    array('class' => 'gradetheselink'));
        }
        return $result;
    }

    /**
     * Display data (the students attempts) in a table format
     * @param string $includeauto
     */
    protected function display_index($includeauto) {
        global $OUTPUT;

        $attempts = $this->get_formatted_student_attempts();
        if ($groupmode = groups_get_activity_groupmode($this->cm)) {
            // Groups is being used.
            groups_print_activity_menu($this->cm, $this->list_questions_url());
        }
        echo $OUTPUT->heading(get_string('questionsthatneedgrading', 'quiz_gradingstudents'));
        if ($includeauto) {
            $linktext = get_string('hideautomaticallygraded', 'quiz_gradingstudents');
        } else {
            $linktext = get_string('alsoshowautomaticallygraded', 'quiz_gradingstudents');
        }
        echo html_writer::tag('p', html_writer::link($this->list_questions_url(!$includeauto),
                $linktext), array('class' => 'toggleincludeauto'));

        $data = array();
        foreach ($attempts as $key => $attempt) {
            if ($attempt->all == 0) {
                continue;
            }
            if (!$includeauto && $attempt->needsgrading == 0 && $attempt->manuallygraded == 0) {
                continue;
            }
            if (has_capability ('mod/quiz:attempt', $this->context)) {
                $reviewlink = html_writer::tag('a',
                                        get_string('attemptid', 'quiz_gradingstudents', $attempt->attemptnumber),
                                        array('href'=>new moodle_url('/mod/quiz/review.php',
                                        array('attempt' => $attempt->attemptid))));
            } else {
                $reviewlink = get_string('attemptid', 'quiz_gradingstudents', $attempt->attemptnumber);
            }
            $row = array();
            $row[] = format_string($attempt->idnumber);
            $row[] = $reviewlink;
            $row[] = $this->format_count_for_table($attempt, 'needsgrading', 'grade');
            $row[] = $this->format_count_for_table($attempt, 'manuallygraded', 'updategrade');

            if ($includeauto) {
                $row[] = $this->format_count_for_table($attempt, 'autograded', 'updategrade');
            }

            $row[] = $this->format_count_for_table($attempt, 'all', 'gradeall');
            $data[] = $row;
        }

        if (empty($data)) {
            echo $OUTPUT->heading(get_string('nothingfound', 'quiz_gradingstudents'));
            return;
        }

        $table = new html_table();
        $table->class = 'generaltable';
        $table->id = 'questionstograde';

        $table->head[] = get_string('student', 'quiz_gradingstudents');
        $table->head[] = get_string('attempt', 'quiz_gradingstudents');
        $table->head[] = get_string('tograde', 'quiz_gradingstudents');
        $table->head[] = get_string('alreadygraded', 'quiz_gradingstudents');
        if ($includeauto) {
            $table->head[] = get_string('automaticallygraded', 'quiz_gradingstudents');
        }
        $table->head[] = get_string('total', 'quiz_gradingstudents');

        $table->data = $data;
        echo html_writer::table($table);
    }

    /**
     * Display the UI for grading or regrading questions
     * @param int $usageid
     * @param string $slots
     * @param string $grade
     */
    protected function display_grading_interface($usageid, $slots, $grade) {
        global $CFG, $OUTPUT;

        $attempts = $this->get_formatted_student_attempts();
        $attempt = $attempts[$usageid];

         // If not, redirect back to the list.
        if (!$attempt || $attempt->$grade == 0) {
            redirect($this->list_questions_url(), get_string('alldoneredirecting', 'quiz_gradingstudents'));
        }

         // Prepare the form.
         $hidden = array(
             'id' => $this->cm->id,
             'mode' => 'gradingstudents',
             'usageid' => $usageid,
             'slots' => $slots,
         );

        if (array_key_exists('includeauto', $this->viewoptions)) {
            $hidden['includeauto'] = $this->viewoptions['includeauto'];
        }

        // Print the heading and form.
        echo question_engine::initialise_js();

        require_once($CFG->dirroot . '/mod/quiz/report/gradingstudents/examconfirmationcode.php');
        $pi = $attempt->idnumber;
        $pi = $pi ? get_string('personalidentifier', 'quiz_gradingstudents', $pi) : '';

        $cfmcode = quiz_gradingstudents_report_exam_confirmation_code::get_confirmation_code(
                                            $this->cm->idnumber,  $attempt->idnumber);
        $cfmcode = $cfmcode ? get_string('confirmationcode', 'quiz_gradingstudents', $cfmcode) : '';

        echo $OUTPUT->heading(get_string('gradingstudentx', 'quiz_gradingstudents', $attempt->attemptnumber));
        if ($cfmcode) {
            echo html_writer::tag('div', $cfmcode);
        }
        if ($pi) {
            echo html_writer::tag('div', $pi);
        }
        echo html_writer::tag('p', html_writer::link($this->list_questions_url(),
                get_string('backtothelistofstudentattempts', 'quiz_gradingstudents')),
                array('class' => 'mdl-align'));

        // Display the form with one section for each attempt.
        $sesskey = sesskey();
        echo html_writer::start_tag('form', array('method' => 'post',
                'action' => $this->grade_question_url($usageid, $slots, $grade),
                'class' => 'mform', 'id' => 'manualgradingform')) .
                html_writer::start_tag('div') .
                html_writer::input_hidden_params(new moodle_url('', array(
                                'usageid' => $usageid, 'slots' => $slots, 'sesskey' => $sesskey)));
            $quba = question_engine::load_questions_usage_by_activity($usageid);
            $displayoptions = quiz_get_review_options($this->quiz, $attempt, $this->context);
            $displayoptions->hide_all_feedback();
            $displayoptions->history = question_display_options::HIDDEN;
            $displayoptions->manualcomment = question_display_options::EDITABLE;
        foreach ($attempt->questions as $slot => $question) {
            if ($this->normalise_state($question->state) === $grade ||
                    $question->state === $grade || $grade === 'all') {
                echo $quba->render_question($slot, $displayoptions, $this->questions[$slot]->number);
            }
        }

        echo html_writer::tag('div', html_writer::empty_tag('input', array(
                'type' => 'submit', 'value' => get_string('saveandgotothelistofattempts', 'quiz_gradingstudents'))),
                array('class' => 'mdl-align')) .
                html_writer::end_tag('div') . html_writer::end_tag('form');
    }

    /**
     * Validate submitted marks before updating the database
     * @param int $usageid, uniqueid of the quiz attempt
     * @param obj $slots, array of slots
     */
    protected function validate_submitted_marks($usageid, $slots) {
        if (!$usageid) {
            return false;
        }
        if (!$slots) {
            $slots = array();
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
     * Update the quiz attempt with the new grades
     * @param int $usageid, uniqueid of the quiz attempt
     */
    protected function process_submitted_data($usageid) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $attempt = $DB->get_record('quiz_attempts', array('uniqueid' => $usageid));
        $quba = question_engine::load_questions_usage_by_activity($usageid);
        $attemptobj = new quiz_attempt($attempt, $this->quiz, $this->cm, $this->course);
        $attemptobj->process_submitted_actions(time());
        $transaction->allow_commit();
    }

    /**
     * Return an array of quiz attempts, augmented by user idnumber.
     * @param object $quiz
     * @return an array of userid
     */
    private function get_quiz_attempts($quiz) {
        global $DB;

        if (empty($this->users)) {
            return array();
        }

        list($usql, $params) = $DB->get_in_or_equal(array_keys($this->users), SQL_PARAMS_NAMED);
        $params['quizid'] = $quiz->id;
        $params['state'] = 'finished';
        $sql = "SELECT qa.id AS attemptid, qa.uniqueid, qa.attempt AS attemptnumber,
                       qa.quiz AS quizid, qa.layout, qa.userid, qa.timefinish,
                       qa.preview, qa.state, u.idnumber
                  FROM {user} u
                  JOIN {quiz_attempts} qa ON u.id = qa.userid
                 WHERE u.id $usql AND qa.quiz = :quizid AND qa.state = :state
              ORDER BY u.idnumber ASC, attemptid ASC";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return and array of question attempts.
     * @return object, an array of question attempts.
     */
    private function get_question_attempts() {
        global $DB;
        $sql = "SELECT qa.id AS questionattemptid, qa.slot, qa.questionid, qu.id AS usageid
                  FROM {question_usages} qu
                  JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                 WHERE qu.contextid = :contextid
              ORDER BY qa.slot ASC";
        return $DB->get_records_sql($sql, array('contextid' => $this->context->id));
    }

    /**
     * Reurn the latest state for a given question
     * @param int $attemptid
     */
    private function get_current_state_for_this_attempt($attemptid) {
        global $DB;
        $sql = "SELECT qas.*
                  FROM {question_attempt_steps} qas
                 WHERE questionattemptid = :qaid
              ORDER BY qas.sequencenumber ASC";
        $states = $DB->get_records_sql($sql, array('qaid' => $attemptid));
        return end($states)->state;
    }

    /**
     * Return an array of quiz attempts withh all relevant information for each attempt
     */
    protected function get_formatted_student_attempts() {
        $quizattempts = $this->get_quiz_attempts($this->quiz);
        $attempts = $this->get_question_attempts();
        if (!$quizattempts) {
            return array();
        }
        if (!$attempts) {
            return array();
        }
        $output = array();
        foreach ($quizattempts as $key => $quizattempt) {
            $questions = array();
            $needsgrading = 0;
            $autograded = 0;
            $manuallygraded = 0;
            $all = 0;
            foreach ($attempts as $attempt) {
                if ($quizattempt->uniqueid === $attempt->usageid) {
                    $questions[$attempt->slot] = $attempt;
                    $state = $this->get_current_state_for_this_attempt($attempt->questionattemptid);
                    $questions[$attempt->slot]->state = $state;

                    if ($this->normalise_state($state) === 'needsgrading') {
                        $needsgrading++;
                    }
                    if ($this->normalise_state($state) === 'autograded') {
                        $autograded++;
                    }
                    if ($this->normalise_state($state) === 'manuallygraded') {
                        $manuallygraded++;
                    }
                    $all++;
                }
            }
            $quizattempt->needsgrading = $needsgrading;
            $quizattempt->autograded = $autograded;
            $quizattempt->manuallygraded = $manuallygraded;
            $quizattempt->all = $all;
            $quizattempt->questions = $questions;
            $output[$quizattempt->uniqueid] = $quizattempt;
        }
        return $output;
    }

    /**
     * Normalise the string from the database table for easy comparison
     * @param string $state
     */
    protected function normalise_state($state) {
        if (!$state) {
            return null;
        }
        if ($state === 'needsgrading') {
            return 'needsgrading';
        }
        if (substr($state, 0, strlen('graded')) === 'graded') {
            return 'autograded';
        }
        if (substr($state, 0, strlen('mangr')) === 'mangr') {
            return 'manuallygraded';
        }
        return null;
    }

}
