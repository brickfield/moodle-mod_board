@mod @mod_board @javascript
Feature: Allow for multilingual names in mod_board columns

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | First     | Student  | student1@example.com |
      | student2 | Second    | Student  | student2@example.com |
      | student3 | Third     | Student  | student3@example.com |
      | teacher1 | First     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity       | board                  |
      | course         | C1                     |
      | name           | Sample board           |
      | groupmode      | 0                      |
      | singleusermode | 0                      |
    And the following config values are set as admin:
      | media_selection | 0 | mod_board |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"

  Scenario: Users may rename a column using multilang filter
    Given I am on the "Sample board" "board activity" page logged in as "teacher1"
    When I change mod_board "1" column name to "<span class=\"multilang\" lang=\"en\">Best Heading</span><span class=\"multilang\" lang=\"de\">Titel</span>"
    Then I should see "Best Heading"
    But I should not see "Titel"
