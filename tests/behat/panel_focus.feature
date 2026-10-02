@local @local_accessibility @javascript @la_focus
Feature: Keyboard focus in the accessibility panel
  In order to see where I am in the panel
  As a keyboard user
  I need a visible focus indicator on every panel control that differs from the selected look

  Scenario Outline: Focus is visible on the selected swatch, a profile and a pressed tile with <colour> colours
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                 | value    |
      | student1 | local_accessibility_colour | <colour> |
      | student1 | local_accessibility_links  | on       |
    And I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I press the tab key
    Then the "#local-accessibility-panel .la-swatch[aria-pressed='true']" element should show a keyboard focus indicator
    And the "#local-accessibility-panel .la-profile" element should show a keyboard focus indicator
    And the "#local-accessibility-panel .la-tile[aria-pressed='true']" element should show a keyboard focus indicator
    And the "#local-accessibility-panel .la-tile[aria-pressed='false']" element should show a keyboard focus indicator

    Examples:
      | colour      |
      | default     |
      | cream       |
      | yellowblack |
