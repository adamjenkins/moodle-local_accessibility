@local @local_accessibility @javascript @la_choices
Feature: Choosing any value from a drawer or a detail view
  In order to set exactly the display I need
  As a user
  I need to see every option for a setting and choose one by keyboard or pointer

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |

  Scenario: Choose an alignment from its drawer by keyboard
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I set the focus on the "Alignment, Site default" "button"
    And I press the enter key
    Then "#la-drawer-align" "css_element" should be visible
    And the focused element is "[data-feature='align'][data-value='default']" "css_element"
    When I press the right key
    And I press the right key
    Then the focused element is "[data-feature='align'][data-value='center']" "css_element"
    And the page root should not have attribute "data-a11y-align"
    When I press the enter key
    Then the page root should have attribute "data-a11y-align" with value "center"
    And "Alignment, Centre" "button" should exist
    And "//div[contains(@class, 'la-live')][contains(., 'Alignment: Centre')]" "xpath_element" should exist
    And "#la-drawer-align" "css_element" should be visible
    When I press the escape key
    Then "#la-drawer-align" "css_element" should not be visible
    And the focused element is "Alignment, Centre" "button"
    And "local-accessibility-panel" "region" should be visible
    And I reload the page
    And the page root should have attribute "data-a11y-align" with value "center"

  Scenario: Clicking an open drawer's tile again closes it
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Links, Off" "button"
    Then "#la-drawer-links" "css_element" should be visible
    When I click on "Focus ring, Standard" "button"
    Then "#la-drawer-links" "css_element" should not be visible
    And "#la-drawer-focus" "css_element" should be visible
    When I click on "Focus ring, Standard" "button"
    Then "#la-drawer-focus" "css_element" should not be visible

  Scenario: Choose a text size in its detail view and go back
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Text size, 100%" "button"
    Then "Text size" "heading" should be visible
    And ".la-grid" "css_element" should not be visible
    When I click on "[data-feature='size'][data-value='150']" "css_element"
    Then the page root should have attribute "data-a11y-size" with value "150"
    And the computed "font-size" of "html" should be "24px"
    When I click on "All settings" "button" in the "[data-view='size']" "css_element"
    Then ".la-grid" "css_element" should be visible
    And the focused element is "Text size, 150%" "button"

  Scenario: The size stepper walks every size, 125 included
    Given the following "user preferences" exist:
      | user     | preference               | value |
      | student1 | local_accessibility_size | 120   |
    And I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Text size, 120%" "button"
    And I click on "Larger" "button" in the "[data-view='size']" "css_element"
    Then the page root should have attribute "data-a11y-size" with value "125"
    When I click on "Larger" "button" in the "[data-view='size']" "css_element"
    Then the page root should have attribute "data-a11y-size" with value "130"
    When I click on "Smaller" "button" in the "[data-view='size']" "css_element"
    Then the page root should have attribute "data-a11y-size" with value "125"

  Scenario: The spacing view sets line, letter and word spacing and summarises them on the tile
    Given I log in as "student1"
    And the main region contains core controls
    And I click on "Accessibility settings" "button"
    When I click on "Spacing, Site default" "button"
    And I click on "[data-feature='lineheight'][data-value='180']" "css_element"
    And I click on "[data-feature='letterspacing'][data-value='12']" "css_element"
    And I click on "[data-feature='wordspacing'][data-value='16']" "css_element"
    Then the page root should have attribute "data-a11y-lineheight" with value "180"
    And the page root should have attribute "data-a11y-letterspacing" with value "12"
    And the page root should have attribute "data-a11y-wordspacing" with value "16"
    And the computed line height of "#la-test-controls p" should be 1.8 times its font size
    And the computed "letter-spacing" of "#la-test-controls p" should not be "normal"
    When I press the escape key
    Then the focused element is "Spacing, Line 1.8 · Letter 0.12 · Word 0.16" "button"
    And ".la-grid" "css_element" should be visible

  Scenario Outline: The panel follows the user's text size of <size>%
    Given the following "user preferences" exist:
      | user     | preference               | value  |
      | student1 | local_accessibility_size | <size> |
    And I log in as "student1"
    When I click on "Accessibility settings" "button"
    Then the computed "font-size" of "#local-accessibility-panel" should be "<px>"

    Examples:
      | size | px   |
      | 100  | 16px |
      | 150  | 24px |

  Scenario Outline: The header leaves room for the settings at 300% text and the widest spacing on a <viewport> screen
    Given the following "user preferences" exist:
      | user     | preference                        | value |
      | student1 | local_accessibility_size          | 300   |
      | student1 | local_accessibility_lineheight    | 250   |
      | student1 | local_accessibility_letterspacing | 30    |
      | student1 | local_accessibility_wordspacing   | 60    |
    And the browser emulates a <viewport> screen
    And I log in as "student1"
    When I click on "Accessibility settings" "button"
    Then the accessibility panel header should take at most 35% of the panel's height

    Examples:
      | viewport |
      | 320x568  |
      | 568x320  |
      | 1366x768 |

  Scenario: Images can be dimmed or hidden from the drawer
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | name         | content                                                   |
      | page     | C1     | Picture page | <p><img src="/pix/moodlelogo.png" alt="Moodle logo"></p> |
    And I am on the "Picture page" "page activity" page logged in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Images, Shown" "button"
    And I click on "[data-feature='images'][data-value='dim']" "css_element"
    Then the page root should have attribute "data-a11y-images" with value "dim"
    And the computed "opacity" of "#region-main img[alt='Moodle logo']" should be "0.4"
    When I click on "[data-feature='images'][data-value='hide']" "css_element"
    Then the page root should have attribute "data-a11y-images" with value "hide"
    And the alt text of each content image should reach assistive technology exactly once

  Scenario: A locked spacing tile shows its view but changes nothing
    Given the following config values are set as admin:
      | lock_spacing       | 1   | local_accessibility |
      | default_lineheight | 150 | local_accessibility |
    And I log in as "student1"
    And I click on "Accessibility settings" "button"
    Then the "aria-disabled" attribute of "[data-tile='spacing']" "css_element" should contain "true"
    When I click on "[data-tile='spacing']" "css_element"
    And I click on "[data-feature='lineheight'][data-value='250']" "css_element"
    Then the page root should have attribute "data-a11y-lineheight" with value "150"

  Scenario: A save the server refuses puts the option back and says so
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    And I click on "Alignment, Site default" "button"
    And the following config values are set as admin:
      | lock_align | 1 | local_accessibility |
    When I click on "[data-feature='align'][data-value='right']" "css_element"
    Then "//div[contains(@class, 'la-live')][contains(., 'could not be saved')]" "xpath_element" should exist
    And "Alignment, Site default" "button" should exist
    And the "aria-checked" attribute of "[data-feature='align'][data-value='default']" "css_element" should contain "true"
    And the page root should not have attribute "data-a11y-align"
