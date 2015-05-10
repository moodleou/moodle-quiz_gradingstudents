@ou @ouvle @mod @mod_quiz @quiz @quiz_gradingstudents
Feature: Grading by students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               | idnumber |
      | teacher1 | T1        | Teacher1 | teacher1@moodle.com | T1000    |
      | student1 | S1        | Student1 | student1@moodle.com | S1000    !
      | student2 | S2        | Student2 | student2@moodle.com | S2000    !
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    When I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name        | Quiz 1             |
      | Description | Quiz 1 description |

    And I add a "Short answer" question to the "Quiz 1" quiz with:
      | Question name    | Short answer 001 |
      | Question text    | Where is the capital city of France? |
      | Answer 1         | Paris                                |
      | Grade            | 100%                                 |

    And I log out

  @javascript
  Scenario: report with no attempts
    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Quiz 1"
    And I navigate to "Manual grading by student" node in "Quiz administration > Results"
    Then I should see "Manual grading by student"
    And I should see "Quiz 1"
    And I should see "Nothing to display"

  @javascript
  Scenario: Report with attempts
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Quiz 1"
    And I press "Attempt quiz now"
    Then I should see "Question 1"
    And I should see "Not yet answered"
    And I should see "Where is the capital city of France?"
    When I set the field "Answer:" to "Paris"
    And I press "Next"
    Then I should see "Answer saved"
    When I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I log out

    When I log in as "student2"
    And I follow "Course 1"
    And I follow "Quiz 1"
    And I press "Attempt quiz now"
    Then I should see "Question 1"
    And I should see "Not yet answered"
    And I should see "Where is the capital city of France?"
    When I set the field "Answer:" to "London or Berlin"
    And I press "Next"
    Then I should see "Answer saved"
    When I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I log out

    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Quiz 1"
    And I navigate to "Manual grading by student" node in "Quiz administration > Results"
    Then I should see "Manual grading by student"
    When I follow "Also show questions that have been graded automatically"
    Then I should see "S1000"
    And I should see "S2000"

    # Adjust the mark for Student1
    When I click on "update grades" "link" in the "S1000" "table_row"
    And I set the field "Comment" to "I have adjusted your mark to 0.6"
    And I set the field "Mark" to "0.6"
    And I press "Save and go to the list of attempts"

    # Adjust the mark for Student2
    And I follow "Also show questions that have been graded automatically"
    And I click on "update grades" "link" in the "S2000" "table_row"
    And I set the field "Comment" to "I have adjusted your mark to 0.3"
    And I set the field "Mark" to "0.3"
    And I press "Save and go to the list of attempts"

    Then I should see "Also show questions that have been graded automatically"
    And I should not see "Automatically graded"
    When I follow "Also show questions that have been graded automatically"
    Then I should see "Hide questions that have been graded automatically"
    And I should see "Automatically graded"
