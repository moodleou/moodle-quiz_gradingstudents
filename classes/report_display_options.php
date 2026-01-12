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

use mod_quiz\local\reports\attempts_report_options;


/**
 * This file defines the options for the quiz gradingstudents report.
 *
 * @package   quiz_gradingstudents
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_display_options extends attempts_report_options {

    /** @var bool display auto grade attempt. */
    public $includeauto = false;
    /**
     * @var int
     */
    public $usageid = 0;

    /**
     * @var string
     */
    public $slots = '';

    /**
     * @var null
     */
    public $grade = null;

    /**
     * @var bool whether the report want display user name.
     */
    public $shownames;

    /**
     * @var bool whether the report want display user identify fields.
     */
    public $showidentityfields;

    /**
     * @var bool whether the report want display ou confirmation code.
     */
    public $showconfirmationcode;

    public function __construct($mode, $quiz, $cm, $course) {
        parent::__construct($mode, $quiz, $cm, $course);
        $this->setup_from_params();
    }

    public function setup_from_params() {
        parent::setup_from_params();
        $context = \context_module::instance($this->cm->id);
        $this->grade = optional_param('grade', null, PARAM_ALPHA);
        $this->usageid = optional_param('usageid', 0, PARAM_INT);
        $this->slots = optional_param('slots', '', PARAM_SEQUENCE);
        $this->includeauto = optional_param('includeauto', false, PARAM_BOOL);
        $this->shownames = has_capability('quiz/grading:viewstudentnames', $context);
        $this->showidentityfields = has_capability('quiz/grading:viewidnumber', $context);
        $this->showconfirmationcode = \quiz_gradingstudents_ou_confirmation_code::quiz_can_have_confirmation_code(
            $this->cm->idnumber);

    }

    public function get_url_params() {
        $params = parent::get_url_params();
        $params['includeauto'] = $this->includeauto;
        return $params;
    }
}
