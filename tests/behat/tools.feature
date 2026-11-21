@local @local_moodlexhub
Feature: Open MoodleX Hub tools and save personal information
  In order to use MoodleX Hub
  As an authorized Moodle user
  I can open the relevant tools and persist my own entries

  Scenario: An administrator can open the operational areas
    Given I log in as "admin"
    When I visit "/local/moodlexhub/users.php"
    Then I should see "Users"
    When I visit "/local/moodlexhub/monitoring.php"
    Then I should see "Technical monitoring"
    When I visit "/local/moodlexhub/reports.php"
    Then I should see "Create report"
    When I visit "/local/moodlexhub/automation.php"
    Then I should see "Create automation rule"
    When I visit "/local/moodlexhub/integrations.php"
    Then I should see "Webhook destinations"
    When I visit "/local/moodlexhub/knowledge.php"
    Then I should see "Create knowledge entry"

  Scenario: A signed-in learner can save a private note
    Given the following "users" exist:
      | username | firstname | lastname |
      | learner  | Test      | Learner  |
    And I log in as "learner"
    When I visit "/local/moodlexhub/student.php"
    And I follow "Add private note"
    And I set the following fields to these values:
      | Title | My study plan                  |
      | Note  | Review the next topic on Friday |
    And I press "Save changes"
    Then I should see "My study plan"
    And I should see "Review the next topic on Friday"

  Scenario: A signed-in learner can create a support ticket
    Given the following "users" exist:
      | username | firstname | lastname |
      | learner  | Test      | Learner  |
    And I log in as "learner"
    When I visit "/local/moodlexhub/support.php"
    And I follow "Create ticket"
    And I set the following fields to these values:
      | Subject              | Help with enrolment |
      | Describe the problem | I need help with my course access. |
    And I press "Create ticket"
    Then I should see "Help with enrolment"
    And I should see "I need help with my course access."
