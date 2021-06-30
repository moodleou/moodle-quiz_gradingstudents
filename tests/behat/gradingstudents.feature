@ou @ou_vle @mod @mod_quiz @quiz @quiz_gradingstudents @javascript
Feature: Grading by students

  Background:
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Text area"
    And I set the following fields to these values:
      | Short name  | customid    |
      | Name        | Custom ID   |
      | Category    | Identifiers |
    And I click on "Save changes" "button"
    And I log out

    Given the following "users" exist:
      | username | firstname | lastname | email               | idnumber | profile_field_customid |
      | teacher  | T1        | Teacher  | teacher@moodle.com  | T1000    | CF123                  |
      | student1 | S1        | Student1 | student1@moodle.com | S1000    | CF234                  |
      | student2 | S2        | Student2 | student2@moodle.com | S2000    | CF345                  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |

    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | course | idnumber |
      | quiz       | Quiz 1 | C1     | q1       |
    And the following "questions" exist:
      | questioncategory | qtype       | name  |
      | Test questions   | shortanswer | SA    |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | SA       | 1    |         |
    And the following config values are set as admin:
      | showuseridentity | username,idnumber,profile_field_customid |

  Scenario: report with no attempts
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Results > Manual grading by student" in current page administration
    Then I should see "Manual grading by student"
    And I should see "Quiz 1"
    And I should see "Nothing to display"

  Scenario: Report with attempts
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Frog     |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Cat      |

    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Results > Manual grading by student" in current page administration
    Then I should see "Manual grading by student"
    When I follow "Also show questions that have been graded automatically"
    Then I should see "S1000"
    And I should see "S2000"
    And "Attempt 1" "link" should exist

    # Adjust the mark for Student1
    When I click on "update grades" "link" in the "S1000" "table_row"
    And I should see "Frog is a very good answer."
    And I should not see "Generalfeedback: frog or toad would have been OK."
    And I should see "The correct answer is: frog"
    And I set the field "Comment" to "I have adjusted your mark to 0.6"
    And I set the field "Mark" to "0.6"
    And I press "Save and go to the list of attempts"

    # Adjust the mark for Student2
    And I follow "Also show questions that have been graded automatically"
    And I click on "update grades" "link" in the "S2000" "table_row"
    And I should see "That is a bad answer."
    And I should not see "Generalfeedback: frog or toad would have been OK."
    And I should see "The correct answer is: frog"
    And I set the field "Comment" to "I have adjusted your mark to 0.3"
    And I set the field "Mark" to "0.3"
    And I press "Save and go to the list of attempts"

    Then I should see "Also show questions that have been graded automatically"
    And I should not see "Automatically graded"
    When I follow "Also show questions that have been graded automatically"
    Then I should see "Hide questions that have been graded automatically"
    And I should see "Automatically graded"

  Scenario: Admin with permission can see custom fields and student name
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Snake    |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Rabbit   |
  When I am on the "Quiz 1" "mod_quiz > View" page logged in as "admin"
  When I navigate to "Results > Manual grading by student" in current page administration
  When I follow "Also show questions that have been graded automatically"
  Then I should see "S1 Student1" in the "student1" "table_row"
  And I should see "Custom ID"
  Then I should see "CF234" in the "student1" "table_row"

  Scenario: Teacher without permission can see custom fields and not student name
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | frog     |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Duck     |
    And the following "permission overrides" exist:
      | capability                    | permission | role                  | contextlevel | reference |
      | quiz/grading:viewstudentnames | Prevent    | editingteacher        | Course       | C1        |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher"
    When I navigate to "Results > Manual grading by student" in current page administration
    When I follow "Also show questions that have been graded automatically"
    Then I should not see "S1 Student1" in the "student1" "table_row"
    And I should see "Custom ID"
    Then I should see "CF234" in the "student1" "table_row"