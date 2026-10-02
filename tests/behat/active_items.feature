@local @local_accessibility @javascript @la_activeitems
Feature: Current page and active menu items under a colour scheme
  In order to see which page I am on
  As a user
  I need the current pagination item and the active menu item to stay readable

  Scenario Outline: The current page and the active menu item stay readable under <colour>
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                 | value    |
      | student1 | local_accessibility_colour | <colour> |
    And I log in as "student1"
    When the main region contains core controls
    Then the "#la-test-page" element should have a text contrast of at least 4.5:1
    And the "#la-test-menuitem" element should have a text contrast of at least 4.5:1
    And the "#la-test-link" element should have a text contrast of at least 7:1

    Examples:
      | colour       |
      | blackwhite   |
      | cream        |
      | yellowblack  |
      | dark         |
