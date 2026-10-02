@local @local_accessibility @javascript
Feature: Admin controls
  In order to keep the site usable for everyone
  As an admin
  I need to set defaults that users cannot change

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |

  Scenario: A locked feature cannot be changed
    Given the following config values are set as admin:
      | default_spacing | wcag | local_accessibility |
      | lock_spacing    | 1    | local_accessibility |
    When I log in as "student1"
    Then the page root should have attribute "data-a11y-spacing" with value "wcag"
    And I click on "Accessibility settings" "button"
    And the "aria-disabled" attribute of "Spacing, Wider" "button" should contain "true"
    And I click on "Spacing, Wider" "button"
    And the page root should have attribute "data-a11y-spacing" with value "wcag"

  Scenario: A locked colour-related tile stays disabled when forced colours are off
    Given the following config values are set as admin:
      | default_links | on | local_accessibility |
      | lock_links    | 1  | local_accessibility |
    When I log in as "student1"
    And I click on "Accessibility settings" "button"
    Then the "aria-disabled" attribute of "Links, Highlighted" "button" should contain "true"
    And ".la-swatch[data-scheme='yellowblack']:not([aria-disabled='true'])" "css_element" should exist
    And I click on "Links, Highlighted" "button"
    And the "aria-disabled" attribute of "Links, Highlighted" "button" should contain "true"
    And the page root should have attribute "data-a11y-links" with value "on"

  Scenario: Embedded layout has no panel
    Given I log in as "admin"
    When I visit "/h5p/embed.php?url=/pluginfile.php/1/none/none/0/missing.h5p"
    Then ".local-accessibility-launcher" "css_element" should not exist
    And "#local-accessibility-panel" "css_element" should not exist
