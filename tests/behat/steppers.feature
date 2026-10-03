@local @local_accessibility @javascript @la_steppers
Feature: Numeric settings change by any amount with − and + steppers
  In order to set exactly the size and spacing I need
  As a user
  I need to step a number up and down, or type it, with no fixed list of values

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |

  Scenario: Letter spacing steps, takes a typed negative value, refuses junk and goes back to the site default
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Spacing, Site default" "button"
    Then the "letterspacing" stepper field should show ""
    When I click on "More" "button" in the ".la-stepper[data-feature='letterspacing']" "css_element"
    Then the page root should have attribute "data-a11y-letterspacing" with value "1"
    And the "letterspacing" stepper field should show "0.01"
    And "//div[contains(@class, 'la-live')][contains(., 'Letter spacing: 0.01 em')]" "xpath_element" should exist
    When I click on "Less" "button" in the ".la-stepper[data-feature='letterspacing']" "css_element"
    And I click on "Less" "button" in the ".la-stepper[data-feature='letterspacing']" "css_element"
    Then the page root should have attribute "data-a11y-letterspacing" with value "-1"
    When I type "-0.05" in the "letterspacing" stepper and press enter
    Then the page root should have attribute "data-a11y-letterspacing" with value "-5"
    And the "style" attribute of "html" "css_element" should contain "--a11y-ls: -0.05em"
    And "Spacing, Letter -0.05" "button" should exist
    And "//div[contains(@class, 'la-live')][contains(., 'Letter spacing: -0.05 em')]" "xpath_element" should exist
    When I type "1e3" in the "letterspacing" stepper and press enter
    Then "//div[contains(@class, 'la-live')][contains(., '1e3 was not applied to Letter spacing')]" "xpath_element" should exist
    And the "letterspacing" stepper field should show "-0.05"
    And the page root should have attribute "data-a11y-letterspacing" with value "-5"
    When I reload the page
    Then the page root should have attribute "data-a11y-letterspacing" with value "-5"
    And I click on "Accessibility settings" "button"
    And I click on "Spacing, Letter -0.05" "button"
    When I click on "Site default" "button" in the ".la-stepper[data-feature='letterspacing']" "css_element"
    Then the page root should not have attribute "data-a11y-letterspacing"
    And "Spacing, Site default" "button" should exist
    And I reload the page
    And the page root should not have attribute "data-a11y-letterspacing"

  Scenario: Word spacing and line height step by their own amounts, with a live sample
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Spacing, Site default" "button"
    And I click on "More" "button" in the ".la-stepper[data-feature='wordspacing']" "css_element"
    Then the page root should have attribute "data-a11y-wordspacing" with value "2"
    And the "style" attribute of ".la-stepper[data-feature='wordspacing'] .la-stepsample" "css_element" should contain "word-spacing: 0.02em"
    When I click on "Less" "button" in the ".la-stepper[data-feature='lineheight']" "css_element"
    Then the page root should have attribute "data-a11y-lineheight" with value "140"
    And the "lineheight" stepper field should show "1.4"
    When I type "0.5" in the "lineheight" stepper and press enter
    Then the page root should have attribute "data-a11y-lineheight" with value "50"
    And "Spacing, Line 0.5 · Word 0.02" "button" should exist

  Scenario: Restricted to non-negative values, negative letter spacing is refused
    Given the following config values are set as admin:
      | numericlimits | nonnegative | local_accessibility |
    And the following "user preferences" exist:
      | user     | preference                        | value |
      | student1 | local_accessibility_letterspacing | -5    |
    And I log in as "student1"
    # The stored negative value is out of range now, so the site default is used.
    Then the page root should not have attribute "data-a11y-letterspacing"
    And I click on "Accessibility settings" "button"
    When I click on "Spacing, Site default" "button"
    And I click on "Less" "button" in the ".la-stepper[data-feature='letterspacing']" "css_element"
    Then the page root should have attribute "data-a11y-letterspacing" with value "0"
    And the "aria-disabled" attribute of ".la-stepper[data-feature='letterspacing'] [data-action='stepdown']" "css_element" should contain "true"
    When I type "-0.05" in the "letterspacing" stepper and press enter
    Then "//div[contains(@class, 'la-live')][contains(., '-0.05 was not applied to Letter spacing: enter a number from 0 to 5')]" "xpath_element" should exist
    And the page root should have attribute "data-a11y-letterspacing" with value "0"
    And the "letterspacing" stepper field should show "0"

  Scenario: Line width steps down from full width and shows its bar
    Given I log in as "student1"
    And I click on "Accessibility settings" "button"
    When I click on "Line width, Full width" "button"
    Then the "aria-disabled" attribute of ".la-stepper[data-feature='narrow'] [data-action='stepup']" "css_element" should contain "true"
    When I click on "Less" "button" in the ".la-stepper[data-feature='narrow']" "css_element"
    Then the page root should have attribute "data-a11y-narrow" with value "90"
    And "Line width, 90 characters" "button" should exist
    And the "aria-disabled" attribute of ".la-stepper[data-feature='narrow'] [data-action='stepup']" "css_element" should contain "false"
    When I type "45" in the "narrow" stepper and press enter
    Then the page root should have attribute "data-a11y-narrow" with value "45"
    And the "style" attribute of ".la-stepper[data-feature='narrow'] .la-barfill" "css_element" should contain "inline-size: 50%"
    When I click on "Site default" "button" in the ".la-stepper[data-feature='narrow']" "css_element"
    Then the page root should not have attribute "data-a11y-narrow"
