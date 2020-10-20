@enrol @enrol_fee
Feature: Signing up for a course with a fee enrolment method

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | summary |
      | Course 1 | C1        | topics |         |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
    And payment plugin "paypal" is enabled
    And the following "core_payment > payment accounts" exist:
      | name           | gateways |
      | Account1       | paypal   |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Fee" "table_row"
    And I log out

  @javascript
  Scenario: Student can see the payment prompt on the course enrolment page
    When I log in as "manager1"
    And I am on "Course 1" course homepage
    And I navigate to "Users > Enrolment methods" in current page administration
    And I select "Fee" from the "Add method" singleselect
    And I set the following fields to these values:
      | Payment account | Account1 |
      | Enrol cost      | 10       |
      | Currency        | Euro     |
    And I press "Add method"
    And I log out
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And I should see "This course requires a payment for entry."
    #And I should see "Cost: EUR 10.00" # TODO for some reason behat does not "see" this text.
    And I press "Pay enrolment fee"
    And I should see "PayPal" in the "Select Payment Type" "dialogue"
    And I click on "Cancel" "button" in the "Select Payment Type" "dialogue"
    And I log out
