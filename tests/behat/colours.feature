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
    And I click on "Colours, Site default" "button"

  Scenario: Choosing a preset sets the scheme and its mode
    When I click on "[data-view='colour'] [data-scheme='yellowblack']" "css_element"
    And I wait until the page is ready
    Then the page root should have attribute "data-a11y-colour" with value "yellowblack"
    And the page root should have attribute "data-bs-theme" with value "dark"
    And I click on "Accessibility settings" "button"
    And "Colours, Yellow on black" "button" should exist

  Scenario: A swatch can be chosen by keyboard
    When I press the tab key
    Then the focused element is "[data-view='colour'] [data-scheme='default']" "css_element"
    When I press the right key
    And I press the right key
    And I press the right key
    And I press the enter key
    And I wait until the page is ready
    Then the page root should have attribute "data-a11y-colour" with value "blackwhite"

  Scenario: Custom colours are auto-adjusted to 7:1
    When I click on "Custom colours" "button"
    And I set the colour field "Background" to "#3a6ea5"
    And I set the colour field "Text" to "#ffffff"
    Then I should see "Adjusted to reach 7:1"
    When I click on "Apply" "button"
    Then the page root should have attribute "data-a11y-colour" with value "custom"
    And "Colours, Custom" "button" should exist
    And the focused element is "Custom colours" "button"

  Scenario: Near-identical exact colours are refused
    When I click on "Custom colours" "button"
    And I set the field "Use my exact colours" to "1"
    And I set the colour field "Background" to "#ffffff"
    And I set the colour field "Text" to "#fafafa"
    Then I should see "almost invisible together"
    And the "Apply" "button" should be disabled

  Scenario: Escape closes the colour editor, then the colour view, then the dialog
    When I click on "Custom colours" "button"
    Then "Apply" "button" should be visible
    And "[data-view='colour'] .la-swatches" "css_element" should not be visible
    When I press escape in the accessibility dialog
    Then "Apply" "button" should not be visible
    And "[data-view='colour'] .la-swatches" "css_element" should be visible
    And the focused element is "Custom colours" "button"
    When I press escape in the accessibility dialog
    Then ".la-grid" "css_element" should be visible
    And the focused element is "Colours, Site default" "button"
    And "local-accessibility-panel" "region" should be visible
    When I press escape in the accessibility dialog
    Then "local-accessibility-panel" "region" should not be visible
