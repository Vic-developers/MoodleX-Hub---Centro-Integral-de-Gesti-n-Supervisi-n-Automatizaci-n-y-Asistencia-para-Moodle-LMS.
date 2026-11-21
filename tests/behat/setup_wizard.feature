@local @local_moodlexhub
Feature: Configure MoodleX Hub for the centre

  Scenario: An administrator checks roles and cron and activates a centre profile
    Given I log in as "admin"
    When I visit "/local/moodlexhub/setup_wizard.php"
    Then I should see "Open role definitions in Moodle"
    And I should see "Manage scoped academic follow-up cases"
    When I follow "Check cron"
    Then I should see "Open Moodle scheduled tasks"
    When I follow "Enable modules"
    And I set the field "Centre profile" to "Teaching and learning"
    And I press "Enable profile modules"
    Then I should see "Profile modules enabled."
    And I should see "Teacher panel"

  Scenario: An administrator saves and removes a category audit template
    Given the following "courses" exist:
      | fullname     | shortname |
      | Template lab | T1        |
    And I log in as "admin"
    When I visit "/local/moodlexhub/courses.php"
    And I follow "Template lab"
    And I follow "Quality audit"
    And I follow "Category audit template"
    And I set the field "Course description" to "0"
    And I press "Save changes"
    Then I should see "This category has its own template."
    When I press "Use inherited criteria"
    Then I should see "The current values are inherited."
