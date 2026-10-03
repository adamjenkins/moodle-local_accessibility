@local @local_accessibility @javascript @la_forcedcolours
Feature: The panel under forced colours
  In order to see which settings are on in a high contrast mode
  As a forced-colours user
  I need changed tiles and chosen options to stay visible

  Scenario: Changed tiles and chosen options stay visible under forced colours
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_size   | 150   |
      | student1 | local_accessibility_images | hide  |
    And I log in as "student1"
    And the browser emulates forced colours
    When I click on "Accessibility settings" "button"
    Then the "#local-accessibility-panel .la-tile[data-tile='size']" element should stand out from its background
    And the computed "border-top-width" of "#local-accessibility-panel .la-tile[data-tile='size']" should be "3px"
    And the "#local-accessibility-panel .la-tile[data-tile='size']" element should look different from the "#local-accessibility-panel .la-tile[data-tile='font']" element
    And the "#local-accessibility-panel .la-tile[data-tile='images']" element should look different from the "#local-accessibility-panel .la-tile[data-tile='motion']" element
    And "Colours, Controlled by your device" "button" should exist
    When I click on "Images, Hidden, alt text shown" "button"
    Then the "#la-drawer-images .la-option[aria-checked='true']" element should stand out from its background
    And the "#la-drawer-images .la-option[aria-checked='true']" element should look different from the "#la-drawer-images .la-option[aria-checked='false']" element
