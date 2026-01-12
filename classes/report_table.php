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

namespace quiz_gradingstudents;

use html_writer;
use moodle_url;
use mod_quiz\local\reports\attempts_report_table;
use table_sql;
use qubaid_list;

/**
 * This file defines the quiz gradingstudents table for helping teachers manually grade questions by students.
 *
 * @package   quiz_gradingstudents
 * @copyright 2024 The Open university
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends attempts_report_table {

    /**
     * @var object cm current course module object of this table.
     */
    public $cm;

    public function __construct($quiz, $context, $qmsubselect, report_display_options $options,
        \core\dml\sql_join $groupstudentsjoins, \core\dml\sql_join $studentsjoins, $questions, $reporturl) {
        parent::__construct('mod-quiz-report-gradingstudents-report', $quiz, $context,
            $qmsubselect, $options, $groupstudentsjoins, $studentsjoins, $questions, $reporturl);
    }

    public function build_table() {
        if (!$this->rawdata) {
            return;
        }
        $this->rawdata = $this->get_formatted_student_attempts($this->rawdata);
        $this->rawdata = $this->filter_attempts_base_on_grading_status($this->rawdata, $this->options->includeauto);
        // We want to disable paging after get all the data.
        $this->totalrows = count($this->rawdata);
        $this->pageable(false);
        parent::build_table();
    }

    protected function update_sql_after_count($fields, $from, $where, $params) {
        $fields .= ', quiza.id AS attemptid, quiza.attempt AS attemptnumber, quiza.preview';
        return [$fields, $from, $where, $params];
    }

    public function get_sort_columns() {
        $sortcolumns = parent::get_sort_columns();
        if (empty($sortcolumns)) {
            $sortcolumns['u.idnumber'] = SORT_ASC;
            $sortcolumns['attemptid'] = SORT_ASC;
        }
        return $sortcolumns;
    }

    public function col_fullname($attempt) {
        // The quiz report normally adds a review link here, but we don't want that,
        // so call the grandparent method.
        return table_sql::col_fullname($attempt);
    }

    /**
     * Define the table column, headers and sorting.
     *
     * @param $allowedjoins
     * @param $options
     */
    public function define_table($allowedjoins, $options): void {

        $this->setup_sql_queries($allowedjoins);
        // Define table columns and headers.
        [$columns, $headers] = $this->add_columns_and_headers_from_options($options);
        $this->define_columns($columns);
        $this->define_headers($headers);
        // Do not allow sorting on non-sql columns.
        $this->no_sorting('confirmationcode');
        $this->no_sorting('tograde');
        $this->no_sorting('alreadygraded');
        $this->no_sorting('autograded');
        $this->no_sorting('total');
        $this->no_sorting('attempt');
        // Set up the table.
        $this->define_baseurl($options->get_url());
        $this->set_attribute('id', 'gradingstudents');
        if ($options->shownames) {
            $this->initialbars(true);
        }
    }

    /**
     * Add columns and headers to table base on display options.
     *
     * @param report_display_options $options report display options.
     * @return array [$column, $header]
     */
    public function add_columns_and_headers_from_options(report_display_options $options): array {
        $columns = [];
        $headers = [];
        if ($options->shownames) {
            $columns = ['fullname'];
            $headers = [get_string('user')];
        }
        if ($options->showidentityfields) {
            $identityfields = \core_user\fields::get_identity_fields($this->context, true);
            foreach ($identityfields as $fieldname) {
                $displayname = \core_user\fields::get_display_name($fieldname);
                $columns[] = $fieldname;
                $headers[] = $displayname;
            }
        }
        if ($options->showconfirmationcode) {
            $columns[] = 'confirmationcode';
            $headers[] = get_string('confirmationcodeheading', 'quiz_gradingstudents');
        }
        $columns = array_merge($columns, ['attempt', 'tograde', 'alreadygraded']);
        $headers = array_merge($headers, [
            get_string('attempt', 'quiz_gradingstudents'),
            get_string('tograde', 'quiz_gradingstudents'),
            get_string('alreadygraded', 'quiz_gradingstudents'),
        ]);

        if ($options->includeauto) {
            $columns[] = 'autograded';
            $headers[] = get_string('automaticallygraded', 'quiz_gradingstudents');
        }
        $columns[] = 'total';
        $headers[] = get_string('total', 'quiz_gradingstudents');

        return [$columns, $headers];
    }

    /**
     * Return the attempt link or the attempt id.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_attempt(\stdClass $row): string {
        if (has_capability('mod/quiz:viewreports', $this->context)) {
            $reviewlink = html_writer::tag('a', get_string('attemptid', 'quiz_gradingstudents',
                $row->attemptnumber), [
                'href' => new moodle_url('/mod/quiz/review.php', [
                    'attempt' => $row->attemptid,
                ]),
            ]);
        } else {
            $reviewlink = get_string('attemptid', 'quiz_gradingstudents', $row->attemptnumber);
        }
        return $reviewlink;
    }

    /**
     * Display the exam code (OU-specific).
     *
     * @param \stdClass $row the table row being output.
     * @return string HTML content to go inside the td.
     */
    public function col_confirmationcode(\stdClass $row): string {
        if ($row->idnumber) {
            return \quiz_gradingstudents_ou_confirmation_code::get_confirmation_code(
                $this->cm, (object) ['id' => $row->userid, 'idnumber' => $row->idnumber]);
        }
        return '-';
    }

    /**
     * Display the attempt that need grading.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_tograde(\stdClass $row): string {
        return $this->format_count_for_table($row, 'needsgrading', 'grade');
    }

    /**
     * Display the attempt that already been graded.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_alreadygraded(\stdClass $row): string {
        return $this->format_count_for_table($row, 'manuallygraded', 'updategrade');
    }

    /**
     * Display the total
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_total(\stdClass $row): string {
        return $this->format_count_for_table($row, 'all', 'gradeall');
    }

    /**
     * Display the attempt that has auto graded.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_autograded(\stdClass $row): string {
        return $this->format_count_for_table($row, 'autograded', 'updategrade');
    }

    /**
     * Return url for appropriate questions.
     *
     * @param int $usageid the usage id of the attempt to grade.
     * @param string $slots comma-sparated list of the slots to grade.
     * @param string $grade type of things to grade, e.g. 'needsgrading'.
     * @return moodle_url the requested URL.
     */
    protected function grade_question_url($usageid, $slots, $grade) {
        $url = \quiz_gradingstudents_report::base_url($this->cm);
        $url->params(['usageid' => $usageid, 'slots' => $slots, 'grade' => $grade]);
        return $url;
    }

    /**
     * Return formatted output.
     *
     * @param object $attempt augmented quiz_attempts row as from get_formatted_student_attempts.
     * @param string $type type of attempts, e.g. 'needsgrading'.
     * @param string $gradestring corresponding lang string for the action, e.g. 'grade'.
     * @return string formatted string.
     */
    protected function format_count_for_table($attempt, $type, $gradestring) {
        $counts = $attempt->$type;
        $slots = [];
        if ($counts > 0) {
            foreach ($attempt->questions as $question) {
                if ($type === $this->normalise_state($question->state) || $type === 'all') {
                    $slots[] = $question->slot;
                }
            }
        }
        $slots = implode(',', $slots);
        $result = $counts;
        if ($counts > 0) {
            $result .= ' ' . html_writer::link($this->grade_question_url(
                    $attempt->usageid, $slots, $type),
                    get_string($gradestring, 'quiz_gradingstudents'),
                    ['class' => 'gradetheselink']);
        }
        return $result;
    }

    /**
     * Display the UI for grading or regrading questions.
     *
     * @param string $grade the type of slots to grade, e.g. 'needsgrading'.
     */
    public function display_grading_interface(report_display_options $options, $grade, $allowedjoins) {
        global $OUTPUT, $PAGE;
        // We only want to re used the table data for the grading interface, not display the table.
        $this->define_table($allowedjoins, $options);
        $this->setup();
        $this->query_db($this->pagesize, false);
        $this->close_recordset();

        $attempts = $this->get_formatted_student_attempts($this->rawdata);
        $usageid = $options->usageid;
        // The attempts list key is base uniqueid not usageid so we need to find the correct attempt with same usageid.
        $attempt = '';
        foreach ($attempts as $currentattempt) {
            if ($currentattempt->usageid == $usageid) {
                $attempt = $currentattempt;
                break;
            }
        }
        // If not, redirect back to the list.
        if (!$attempt || $attempt->$grade == 0) {
            redirect($this->baseurl, get_string('alldoneredirecting', 'quiz_gradingstudents'));
        }

        $usageid = $attempt->usageid;
        $slots = $options->slots;

        // Print the heading and form.
        echo \question_engine::initialise_js();

        $info = [];
        foreach (\core_user\fields::get_identity_fields($this->context) as $field) {
            if ($attempt->$field) {
                $info[] = html_writer::div(get_string('fieldandvalue', 'quiz_gradingstudents',
                    ['field' => \core_user\fields::get_display_name($field), 'value' => $attempt->$field]));
            }
        }

        $cfmcode = \quiz_gradingstudents_ou_confirmation_code::get_confirmation_code(
            $this->cm, (object) ['id' => $attempt->userid, 'idnumber' => $attempt->idnumber]);
        if ($cfmcode) {
            $info[] = html_writer::div(get_string('fieldandvalue', 'quiz_gradingstudents',
                ['field' => get_string('confirmationcodeheading', 'quiz_gradingstudents'), 'value' => $cfmcode]));
        }

        echo $OUTPUT->heading(get_string('gradingstudentx', 'quiz_gradingstudents', $attempt->attemptnumber));
        echo implode("\n", $info);
        echo html_writer::tag('p', html_writer::link(\quiz_gradingstudents_report::base_url($this->cm),
            get_string('backtothelistofstudentattempts', 'quiz_gradingstudents')),
            ['class' => 'mdl-align']);

        // Display the form with one section for each attempt.
        $sesskey = sesskey();
        echo html_writer::start_tag('form', ['method' => 'post',
                'action' => $this->grade_question_url($usageid, $slots, $grade),
                'class' => 'mform', 'id' => 'manualgradingform']) .
            html_writer::start_tag('div') .
            html_writer::input_hidden_params(new moodle_url('', ['usageid' => $usageid,
                'slots' => $slots, 'sesskey' => $sesskey]));
        $quba = \question_engine::load_questions_usage_by_activity($usageid);
        $displayoptions = quiz_get_review_options($this->quiz, $attempt, $this->context);
        $displayoptions->generalfeedback = \question_display_options::HIDDEN;
        $displayoptions->history = \question_display_options::HIDDEN;
        $displayoptions->manualcomment = \question_display_options::EDITABLE;
        foreach ($attempt->questions as $slot => $question) {
            if (array_key_exists($slot, $this->questions)) {
                if ($this->normalise_state($question->state) === $grade ||
                    $question->state === $grade || $grade === 'all') {
                    echo $quba->render_question($slot, $displayoptions, $this->questions[$slot]->number);
                }
            }
        }

        echo html_writer::tag('div', html_writer::empty_tag('input', [
                'type' => 'submit', 'value' => get_string('saveandgotothelistofattempts', 'quiz_gradingstudents')]),
                ['class' => 'mdl-align']) .
            html_writer::end_tag('div') . html_writer::end_tag('form');

        $PAGE->requires->string_for_js('changesmadereallygoaway', 'moodle');
        $PAGE->requires->js_call_amd('core_form/changechecker', 'watchFormById', ['manualgradingform']);
    }

    /**
     * Return an array of question attempts with the latest state.
     *
     * @param array $qubaidlist List of question usage ids.
     * @return array an array of question attempts.
     */
    private function get_question_attempts_with_latest_state(array $qubaidlist): array {
        global $DB;
        // Sub query to get the latest state of an attempt.
        $dm = new \question_engine_data_mapper();
        $qubaids = new qubaid_list($qubaidlist);
        [$subquery, $subqueryparams] = $dm->question_attempt_latest_state_view('', $qubaids);
        $sql = "SELECT questionattemptid, slot, questionid, questionusageid AS usageid, state
                  FROM $subquery sq
              ORDER BY slot";
        return $DB->get_records_sql($sql, $subqueryparams);
    }

    /**
     * Filter all the existing attempts base on the grading status.
     *
     * @param array $attempts
     * @param bool $includeauto
     * @return array
     */
    public function filter_attempts_base_on_grading_status(array $attempts, bool $includeauto): array {
        $filteredattempts = [];
        foreach ($attempts as $attempt) {
            if ($attempt->all == 0) {
                continue;
            }
            if (!$includeauto && $attempt->needsgrading == 0 && $attempt->manuallygraded == 0) {
                continue;
            }
            $filteredattempts[] = $attempt;
        }
        return $filteredattempts;
    }

    /**
     * Return an array of quiz attempts with all relevant information for each attempt.
     */
    protected function get_formatted_student_attempts($quizattempts) {
        if (!$quizattempts) {
            return [];
        }
        $usageidlist = array_map(function($quizattempt) {
            return $quizattempt->usageid;
        }, $quizattempts);

        $attempts = $this->get_question_attempts_with_latest_state($usageidlist);
        if (!$attempts) {
            return [];
        }
        $output = [];
        foreach ($quizattempts as $quizattempt) {
            $questions = [];
            $needsgrading = 0;
            $autograded = 0;
            $manuallygraded = 0;
            $all = 0;
            foreach ($attempts as $attempt) {
                if ($quizattempt->usageid === $attempt->usageid) {
                    $questions[$attempt->slot] = $attempt;
                    $questions[$attempt->slot]->state = $attempt->state;

                    if (array_key_exists($attempt->slot, $this->questions)) {
                        if ($this->normalise_state($attempt->state) === 'needsgrading') {
                            $needsgrading++;
                        }
                        if ($this->normalise_state($attempt->state) === 'autograded') {
                            $autograded++;
                        }
                        if ($this->normalise_state($attempt->state) === 'manuallygraded') {
                            $manuallygraded++;
                        }
                        $all++;
                    }
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
     * Normalise the string from the database table for easy comparison.
     *
     * @param string $state
     * @return string|null the classified state.
     */
    protected static function normalise_state($state) {
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
