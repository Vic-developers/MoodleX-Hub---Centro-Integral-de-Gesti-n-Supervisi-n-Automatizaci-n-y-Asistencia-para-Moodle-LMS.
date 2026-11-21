@local @local_moodlexhub
Feature: Use course diagnostics
  In order to maintain actual Moodle courses
  As a site administrator
  I can reach data and actions through the course catalogue

  Scenario: Audit a course and open its gradebook and question diagnostics
    Given the following "courses" exist:
      | fullname          | shortname |
      | Course for audit  | C1        |
    And I log in as "admin"
    When I visit "/local/moodlexhub/courses.php"
    And I follow "Course for audit"
    And I follow "Quality audit"
    And I press "Run audit"
    Then I should see "Course audit completed."
    And I should see "Course description"
    When I visit "/local/moodlexhub/courses.php"
    And I follow "Course for audit"
    And I follow "Gradebook diagnostics"
    Then I should see "Grade categories"
    When I press "Save diagnostic summary"
    Then I should see "Gradebook history"
    When I visit "/local/moodlexhub/courses.php"
    And I follow "Course for audit"
    And I click on ".moodlexhub-actions a[href*='/local/moodlexhub/questions.php']" "css_element"
    Then I should see "Version status"
    Then I should see "Matching titles suggest possible duplicates."
