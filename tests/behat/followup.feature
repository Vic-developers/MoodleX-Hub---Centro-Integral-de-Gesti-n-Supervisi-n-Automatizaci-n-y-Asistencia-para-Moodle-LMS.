@local @local_moodlexhub
Feature: Review academic evidence and handle a follow-up case

  Scenario: An authorized teacher records and closes a course-access case
    Given the following "users" exist:
      | username | firstname | lastname |
      | learner  | Nerea     | Rojas    |
      | helper   | Mina      | Vega     |
    And the following "courses" exist:
      | fullname        | shortname |
      | Follow-up lab   | F1        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | learner | F1     | student        |
      | helper  | F1     | editingteacher |
    And I log in as "helper"
    When I visit "/local/moodlexhub/academic.php?view=teacher"
    And I set the field "Course" to "Follow-up lab"
    And I press "Choose a course"
    And I follow "Academic follow-up"
    Then I should see "Nerea Rojas"
    And I should see "No recorded course access in the last 14 days"
    When I press "Open follow-up case"
    And I press "Assign to me"
    And I set the field "Intervention or reason" to "Requested an access check."
    And I press "Save intervention"
    Then I should see "Requested an access check."
    When I set the field "Intervention or reason" to "Access restored."
    And I press "Close case"
    Then I should see "Case closed"
    And I should see "Access restored."
    When I follow "Academic follow-up"
    And I set the field "View" to "Closed cases"
    And I press "Apply filters"
    Then I should see "Nerea Rojas"
