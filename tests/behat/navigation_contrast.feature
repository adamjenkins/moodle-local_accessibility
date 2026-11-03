@local @local_accessibility @javascript @la_navigation_contrast
Feature: Navigation, icons in links and form controls follow the accessibility settings
  In order to read the navigation and use every control with my settings
  As a user
  I need menu items, navbar icons and highlighted links to keep their contrast, and the large cursor over controls (issue #4)

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname | summary |
      | Course 1 | C1        |         |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: Under a dark scheme a hovered course tab keeps the scheme's surface, and navbar icons take its text colour
    Given the following "user preferences" exist:
      | user     | preference                 | value        |
      | student1 | local_accessibility_colour | highcontrast |
    And I am on the "C1" "Course" page logged in as "student1"
    # The course tabs are .nav-link up to Moodle 5.2 and .mds-nav-pill from 5.3; High contrast's hover surface is #383838.
    When I hover ".secondary-navigation .nav-link:not(.active), .secondary-navigation .mds-nav-pill:not(.mds-nav-pill--selected)" "css_element"
    Then the computed "background-color" of ".secondary-navigation .nav-link:not(.active), .secondary-navigation .mds-nav-pill:not(.mds-nav-pill--selected)" should be "rgb(56, 56, 56)"
    And the computed "color" of ".navbar .popover-region-toggle .icon" should be "rgb(255, 255, 255)"

  Scenario: Under a dark scheme the messaging panel takes the scheme's surface
    Given the following "user preferences" exist:
      | user     | preference                 | value        |
      | student1 | local_accessibility_colour | highcontrast |
    And I log in as "student1"
    When I visit "/message/index.php"
    Then the computed "background-color" of ".message-app" should be "rgb(22, 22, 22)"

  Scenario: An icon inside a highlighted link follows the link's text colour
    Given the following "user preferences" exist:
      | user     | preference                | value     |
      | student1 | local_accessibility_links | highlight |
    And I am on the "C1" "Course" page logged in as "student1"
    When the HTML "<p><a id='t-link' href='#'><i id='t-icon' class='icon fa fa-bell' style='color: #ffffff'></i> Bell</a></p>" is added to the main region
    Then the computed "color" of "#t-icon" should be "rgb(29, 33, 37)"

  Scenario: The large cursor shows over checkboxes, radios and selects but not in text fields
    Given the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_cursor | large |
    And I am on the "C1" "Course" page logged in as "student1"
    When the HTML "<p><input type='checkbox' id='t-check'> <input type='radio' id='t-radio'> <select id='t-select'><option>A</option></select> <input type='text' id='t-text'> <textarea id='t-area'></textarea></p>" is added to the main region
    Then the computed "cursor" of "#t-check" should not be "auto"
    And the computed "cursor" of "#t-radio" should not be "auto"
    And the computed "cursor" of "#t-select" should not be "auto"
    And the computed "cursor" of "#t-text" should be "text"
    And the computed "cursor" of "#t-area" should be "text"
