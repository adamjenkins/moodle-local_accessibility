@local @local_accessibility @javascript
Feature: Colour schemes
  In order to read text I can see clearly
  As a user
  I need to choose page colours that keep a high contrast

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And I log in as "student1"
    And I click on "Accessibility settings" "button"

  Scenario: Choosing a preset sets the scheme and its mode
    When I click on "Yellow on black" "button"
    And I wait until the page is ready
    Then the page root should have attribute "data-a11y-colour" with value "yellowblack"
    And the page root should have attribute "data-bs-theme" with value "dark"

  Scenario: Custom colours are auto-adjusted to 7:1
    When I click on "Custom colours" "button"
    And I set the colour field "Background" to "#3a6ea5"
    And I set the colour field "Text" to "#ffffff"
    Then I should see "Adjusted to reach 7:1"
    When I click on "Apply" "button"
    Then the page root should have attribute "data-a11y-colour" with value "custom"

  Scenario: Near-identical exact colours are refused
    When I click on "Custom colours" "button"
    And I set the field "Use my exact colours" to "1"
    And I set the colour field "Background" to "#ffffff"
    And I set the colour field "Text" to "#fafafa"
    Then I should see "almost invisible together"
    And the "Apply" "button" should be disabled

  Scenario: Escape closes the colour editor before the dialog
    When I click on "Custom colours" "button"
    Then "Apply" "button" should be visible
    When I press escape in the accessibility dialog
    Then "Apply" "button" should not be visible
    And "local-accessibility-panel" "region" should be visible
    When I press escape in the accessibility dialog
    Then "local-accessibility-panel" "region" should not be visible
