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
      | default_lineheight | 150 | local_accessibility |
      | lock_spacing       | 1   | local_accessibility |
    When I log in as "student1"
    Then the page root should have attribute "data-a11y-lineheight" with value "150"
    And I click on "Accessibility settings" "button"
    And the "aria-disabled" attribute of "Spacing, Line 1.5" "button" should contain "true"
    And I click on "Spacing, Line 1.5" "button"
    And the "aria-disabled" attribute of ".la-stepper[data-feature='lineheight']" "css_element" should contain "true"
    And I click on "More" "button" in the ".la-stepper[data-feature='lineheight']" "css_element"
    And I click on "More" "button" in the ".la-stepper[data-feature='wordspacing']" "css_element"
    And the page root should have attribute "data-a11y-lineheight" with value "150"
    And the page root should not have attribute "data-a11y-wordspacing"

  Scenario: A locked colour-related tile stays disabled when forced colours are off
    Given the following config values are set as admin:
      | default_links | outline | local_accessibility |
      | lock_links    | 1       | local_accessibility |
    When I log in as "student1"
    And I click on "Accessibility settings" "button"
    Then the "aria-disabled" attribute of "Links, Underline and outline" "button" should contain "true"
    And ".la-swatch[data-scheme='yellowblack']:not([aria-disabled='true'])" "css_element" should exist
    And I click on "Links, Underline and outline" "button"
    And I click on "[data-feature='links'][data-value='highlight']" "css_element"
    And the "aria-disabled" attribute of "Links, Underline and outline" "button" should contain "true"
    And the page root should have attribute "data-a11y-links" with value "outline"

  Scenario: Embedded layout has no panel
    Given I log in as "admin"
    When I visit "/h5p/embed.php?url=/pluginfile.php/1/none/none/0/missing.h5p"
    Then ".local-accessibility-launcher" "css_element" should not exist
    And "#local-accessibility-panel" "css_element" should not exist

  Scenario: The panel's Read aloud check leaves the Manage features table alone
    Given I log in as "admin"
    When I visit "/local/accessibility/admin/features.php"
    And the browser has no on-device voices
    Then "tr[data-feature='read']" "css_element" should be visible
