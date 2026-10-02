@local @local_accessibility @javascript @la_forcedcolours
Feature: The panel under forced colours
  In order to see which settings are on in a high contrast mode
  As a forced-colours user
  I need tile values and pressed tiles to stay visible

  Scenario: Value dots and pressed tiles stay visible under forced colours
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_size   | 150   |
      | student1 | local_accessibility_images | on    |
    And I log in as "student1"
    And the browser emulates forced colours
    When I click on "Accessibility settings" "button"
    Then the "#local-accessibility-panel .la-tile[data-feature='size'] .la-dots i.la-on" element should stand out from its background
    And the "#local-accessibility-panel .la-tile[data-feature='size'] .la-dots i:not(.la-on)" element should stand out from its background
    And the "#local-accessibility-panel .la-tile[data-feature='size'] .la-dots i.la-on" element should look different from the "#local-accessibility-panel .la-tile[data-feature='size'] .la-dots i:not(.la-on)" element
    And the "#local-accessibility-panel .la-tile[data-feature='size']" element should look different from the "#local-accessibility-panel .la-tile[data-feature='font']" element
    And the "#local-accessibility-panel .la-tile[data-feature='images']" element should look different from the "#local-accessibility-panel .la-tile[data-feature='motion']" element
