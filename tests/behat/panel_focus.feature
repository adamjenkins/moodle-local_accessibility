@local @local_accessibility @javascript @la_focus
Feature: Keyboard focus in the accessibility panel
  In order to see where I am in the panel
  As a keyboard user
  I need a visible focus indicator on every panel control that differs from the selected look

  Scenario Outline: Focus is visible on tiles, a profile, drawer options and the chosen swatch with <colour> colours
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                 | value    |
      | student1 | local_accessibility_colour | <colour> |
      | student1 | local_accessibility_links  | outline  |
    And I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I press the tab key
    Then the "#local-accessibility-panel .la-profile" element should show a keyboard focus indicator
    And the "#local-accessibility-panel .la-tile[data-tile='links']" element should show a keyboard focus indicator
    And the "#local-accessibility-panel .la-tile[data-tile='size']" element should show a keyboard focus indicator
    When I set the focus on the "Links, Underline and outline" "button"
    And I press the enter key
    Then the "#la-drawer-links .la-option[aria-checked='true']" element should show a keyboard focus indicator
    And the "#la-drawer-links .la-option[aria-checked='false']" element should show a keyboard focus indicator
    When I press the escape key
    And I set the focus on the "[data-tile='colour']" "css_element"
    And I press the enter key
    Then the "[data-view='colour'] .la-swatch[aria-checked='true']" element should show a keyboard focus indicator
    And the "[data-view='colour'] .la-back" element should show a keyboard focus indicator

    Examples:
      | colour      |
      | default     |
      | cream       |
      | yellowblack |
