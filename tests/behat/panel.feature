@local @local_accessibility @javascript
Feature: Accessibility panel
  In order to read comfortably
  As a user
  I need to change display settings from an accessible panel

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |

  Scenario: Open with the button, change text size, and keep it after reload
    Given I log in as "student1"
    When I click on "Accessibility settings" "button"
    Then "local-accessibility-panel" "region" should be visible
    When I click on "Text size, 100%" "button"
    And I click on "[data-feature='size'][data-value='125']" "css_element"
    Then the page root should have attribute "data-a11y-size" with value "125"
    And I reload the page
    Then the page root should have attribute "data-a11y-size" with value "125"

  Scenario: Keyboard shortcut and Escape
    Given I log in as "student1"
    When I press the accessibility shortcut
    Then "local-accessibility-panel" "region" should be visible
    When I press the escape key
    Then "local-accessibility-panel" "region" should not be visible

  Scenario: Guests keep settings in this browser
    Given the following config values are set as admin:
      | forcelogin | 0 |
    And I am on site homepage
    When I click on "Accessibility settings" "button"
    And I click on "Links, Off" "button"
    And I click on "[data-feature='links'][data-value='outline']" "css_element"
    And I reload the page
    Then the page root should have attribute "data-a11y-links" with value "outline"

  Scenario: Reset clears everything
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    And I click on "Text size, 100%" "button"
    And I click on "[data-feature='size'][data-value='125']" "css_element"
    And the page root should have attribute "data-a11y-size" with value "125"
    When I click on "Reset" "button" in the "local-accessibility-panel" "region"
    And I wait until the page is ready
    Then the page root should not have attribute "data-a11y-size"

  Scenario: Guests get the floating launcher even when the launcher is in the user menu only
    Given the following config values are set as admin:
      | forcelogin | 0 |
    And the following config values are set as admin:
      | launcher | menu | local_accessibility |
    When I am on site homepage
    Then ".local-accessibility-launcher" "css_element" should exist
    And I log in as "student1"
    And ".local-accessibility-launcher" "css_element" should not exist

  Scenario: Applying a profile also applies its colour scheme
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Low vision" "button"
    Then the page root should have attribute "data-a11y-colour" with value "highcontrast"
    And the page root should have attribute "data-a11y-size" with value "180"
    And the page root should have attribute "data-a11y-links" with value "outline"

  Scenario: Without an on-device voice a saved Read aloud does not leave the read bar on screen
    Given the following "user preferences" exist:
      | user     | preference               | value |
      | student1 | local_accessibility_read | on    |
    And I log in as "student1"
    And the page root should have attribute "data-a11y-read" with value "on"
    When the browser has no on-device voices
    Then ".la-readbar" "css_element" should not be visible

  Scenario: Hidden images keep their alt text for screen readers, once
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | name         | content                                                   |
      | page     | C1     | Picture page | <p><img src="/pix/moodlelogo.png" alt="Moodle logo"></p> |
    And the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_images | hide  |
    When I am on the "Picture page" "page activity" page logged in as "student1"
    Then the page root should have attribute "data-a11y-images" with value "hide"
    And the alt text of each content image should reach assistive technology exactly once
    When I click on "Accessibility settings" "button"
    And I click on "Images, Hidden, alt text shown" "button"
    And I click on "[data-feature='images'][data-value='off']" "css_element"
    Then the page root should not have attribute "data-a11y-images"
    And the alt text of each content image should reach assistive technology exactly once

  Scenario: Opening the panel focuses the first setting, not Reset All
    Given I log in as "student1"
    When I click on "Accessibility settings" "button"
    Then the focused element is "Text size, 100%" "button"

  Scenario: A size the server refuses is put back and announced
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    And the following config values are set as admin:
      | lock_size | 1 | local_accessibility |
    When I click on "Text size, 100%" "button"
    And I click on "Larger" "button" in the "[data-view='size']" "css_element"
    Then "//div[contains(@class, 'la-live')][contains(., 'could not be saved')]" "xpath_element" should exist
    And "Text size, 100%" "button" should exist
    And the page root should not have attribute "data-a11y-size"
    And the "size" stepper field should show "100"
