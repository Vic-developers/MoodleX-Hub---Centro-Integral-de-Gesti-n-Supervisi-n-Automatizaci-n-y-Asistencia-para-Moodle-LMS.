@local @local_moodlexhub
Feature: Access the MoodleX Hub dashboard
  In order to find MoodleX Hub tools
  As a site user
  I need to see links that match my permissions and preferences

  Scenario: An administrator sees the dashboard and its administrative tools
    Given I log in as "admin"
    When I visit "/local/moodlexhub/index.php"
    Then I should see "Dashboard preferences"
    And I should see "Technical monitoring"
    And I should see "MoodleX Hub settings"

  Scenario: A student sees the dashboard without administrative shortcuts
    Given the following "users" exist:
      | username | firstname | lastname |
      | learner  | Test      | Learner  |
    And I log in as "learner"
    When I visit "/local/moodlexhub/index.php"
    Then I should see "Dashboard preferences"
    And I should not see "Technical monitoring"
