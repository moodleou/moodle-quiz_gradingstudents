@ou @ou_vle @mod @mod_quiz @quiz @quiz_gradingstudents @javascript
Feature: Grading by students

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname  | name           |
      | text     | frog       | Favourite frog |
    And the following "users" exist:
      | username | firstname | lastname | email               | idnumber | profile_field_frog |
      | teacher  | T1        | Teacher  | teacher@moodle.com  | T1000    | green frog         |
      | marker   | M1        | Marker   | marker@moodle.com   | M1000    | African Dwarf      |
      | student1 | S1        | Student1 | student1@moodle.com | S1000    | little frog        |
      | student2 | S2        | Student2 | student2@moodle.com | S2000    | yellow frog        |
      | student3 | S3        | Student3 | student3@moodle.com | S3000    | chubby frog        |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | marker   | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "groupings" exist:
      | name         | course  | idnumber |
      | Tutor groups | C1      | tging    |
    And the following "groups" exist:
      | name         | course | idnumber |
      | Tutor group  | C1     | tg       |
      | Marker group | C1     | mg       |
    And the following "grouping groups" exist:
      | grouping | group |
      | tging    | tg    |
    And the following "group members" exist:
      | user     | group |
      | teacher  | tg    |
      | student1 | tg    |
      | student2 | tg    |
      | marker   | mg    |
      | student3 | mg    |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | course | idnumber | groupmode | grouping |
      | quiz       | Quiz 1 | C1     | q1       | 1         | tging    |
    And the following "questions" exist:
      | questioncategory | qtype       | name  |
      | Test questions   | description | Info  |
      | Test questions   | shortanswer | SA    |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | Info     | 1    |         |
      | SA       | 1    |         |
    And the following config values are set as admin:
      | showuseridentity | username,idnumber,profile_field_frog |

  Scenario: report with no attempts
    When I am on the "Quiz 1" "quiz_gradingstudents > Report" page logged in as "teacher"
    Then I should see "Manual grading by student"
    And I should see "Quiz 1"
    And I should see "Nothing to display"

  Scenario: Report with attempts
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 2    | Frog     |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      | 2    | Cat      |

    When I am on the "Quiz 1" "quiz_gradingstudents > Report" page logged in as "teacher"
    Then I should see "Manual grading by student"
    And I follow "Also show questions that have been graded automatically"
    And I should see "S1000"
    And I should see "S2000"
    And I should see "1 grade all"
    And "Attempt 1" "link" should exist

    # Adjust the mark for Student1
    And I click on "update grades" "link" in the "S1000" "table_row"
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

    And I should see "Also show questions that have been graded automatically"
    And I should not see "Automatically graded"
    And I follow "Also show questions that have been graded automatically"
    And I should see "Hide questions that have been graded automatically"
    And I should see "Automatically graded"

  Scenario: Admin with permission can see custom fields and student name
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Snake    |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Rabbit   |
    When I am on the "Quiz 1" "quiz_gradingstudents > Report" page logged in as "admin"
    And I follow "Also show questions that have been graded automatically"
    Then I should see "S1 Student1" in the "student1" "table_row"
    And I should see "Favourite frog"
    And I should see "little frog" in the "student1" "table_row"
    And "All" "link" should exist
    And "S1 Student1" "table_row" should appear before "S2 Student2" "table_row"
    And I follow "First name"
    And I follow "First name"
    And "S2 Student2" "table_row" should appear before "S1 Student1" "table_row"
    And "yellow frog" "table_row" should appear before "little frog" "table_row"
    And I follow "Favourite frog"
    And I follow "Favourite frog"
    And "little frog" "table_row" should appear before "yellow frog" "table_row"

  Scenario: Teacher without permission can see custom fields and not student name, initial bars.
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | frog     |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Duck     |
    And the following "permission overrides" exist:
      | capability                    | permission | role                  | contextlevel | reference |
      | quiz/grading:viewstudentnames | Prevent    | editingteacher        | Course       | C1        |
    When I am on the "Quiz 1" "quiz_gradingstudents > Report" page logged in as "teacher"
    And I follow "Also show questions that have been graded automatically"
    Then I should not see "S1 Student1" in the "student1" "table_row"
    And "All" "link" should not exist
    And I should see "Favourite frog"
    And I should see "little frog" in the "student1" "table_row"

  Scenario: A marker cannot access the report in separate group
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | frog     |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | Duck     |
    When I am on the "Quiz 1" "quiz_gradingstudents > Report" page logged in as "marker"
    Then I should see "Quiz 1"
    And I should see "Separate groups: All participants"
    And I should see "Sorry, but you need to be part of a group to see this page."
