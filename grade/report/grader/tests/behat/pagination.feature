@gradereport @gradereport_grader
Feature: grader report pagination
  In order to consume the content of the report better
  As a teacher
  I need the report to be paginated

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following config values are set as admin:
      | grade_report_studentsperpageoptions | 1,2,3 |
      | grade_report_studentsperpage        | 2     |

  @javascript
  Scenario: Default is used when teachers have no preference yet
    When I am on the "Course 1" "Course" page logged in as "teacher1"
    And I navigate to "View > Grader report" in the course gradebook
    Then the field "perpage" matches value "2"

  @javascript
  Scenario: Teachers can have their preference for the number of students
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C2     | editingteacher |
    When I am on the "Course 1" "Course" page logged in as "teacher1"
    And I navigate to "View > Grader report" in the course gradebook
    And I set the field "perpage" to "3"
    And I am on the "Course 2" "Course" page
    And I navigate to "View > Grader report" in the course gradebook
    Then the field "perpage" matches value "3"

  @javascript
  Scenario: Teachers can change the number of students shown on the report
    When I am on the "Course 1" "Course" page logged in as "teacher1"
    And I navigate to "View > Grader report" in the course gradebook
    And I set the field "perpage" to "2"
    Then I should see "2" in the ".stickyfooter .pagination" "css_element"
    And I should not see "3" in the ".stickyfooter .pagination" "css_element"

  @javascript
  Scenario: The pagination bar is only displayed when there is more than one page
    When I am on the "Course 1" "Course" page logged in as "teacher1"
    And I navigate to "View > Grader report" in the course gradebook
    Then ".stickyfooter .pagination" "css_element" should exist
    And I set the field "perpage" to "3"
    Then ".stickyfooter .pagination" "css_element" should not exist
