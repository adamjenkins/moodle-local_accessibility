@local @local_accessibility @javascript @la_bootstrap4
Feature: The panel without Bootstrap 5 custom properties
  In order to use the panel on Moodle 4.5
  As a user
  I need the panel, read bar and link outline to keep their colours where Bootstrap 4 defines no --bs-* properties

  Scenario: The panel, read bar and highlighted links keep their colours and indicators
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                | value |
      | student1 | local_accessibility_links | outline |
      | student1 | local_accessibility_size  | 150     |
    And I log in as "student1"
    And the main region contains core controls
    And I click on "Accessibility settings" "button"
    When the page has no Bootstrap 5 custom properties
    And I press the tab key
    Then the computed "background-color" of "#local-accessibility-panel" should not be "rgba(0, 0, 0, 0)"
    And the computed "border-top-style" of "#local-accessibility-panel" should not be "none"
    And the computed "background-color" of "#local-accessibility-panel .la-head" should not be "rgba(0, 0, 0, 0)"
    And the "#local-accessibility-panel .la-title" element should have a text contrast of at least 4.5:1
    And the "#local-accessibility-panel .la-tile .la-label" element should have a text contrast of at least 4.5:1
    And the "#local-accessibility-panel .la-tile .la-value" element should have a text contrast of at least 4.5:1
    And the "#local-accessibility-panel .la-tile[data-tile='size']" element should stand out from its background
    And the "#local-accessibility-panel .la-tile[data-tile='size']" element should look different from the "#local-accessibility-panel .la-tile[data-tile='font']" element
    And the "#local-accessibility-panel .la-tile[data-tile='size']" element should show a keyboard focus indicator
    And the computed "background-color" of ".la-readbar" should not be "rgba(0, 0, 0, 0)"
    And the computed "border-top-style" of ".la-readbar" should not be "none"
    And the computed "outline-style" of "#la-test-link" should not be "none"
    When I set the focus on the "Links, Underline and outline" "button"
    And I press the enter key
    Then the "#la-drawer-links .la-option[aria-checked='true']" element should stand out from its background
    And the "#la-drawer-links .la-option[aria-checked='true']" element should look different from the "#la-drawer-links .la-option[aria-checked='false']" element
    And the "#la-drawer-links .la-option[aria-checked='false']" element should show a keyboard focus indicator
