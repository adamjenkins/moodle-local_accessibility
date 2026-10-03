@local @local_accessibility @javascript @la_formcontrols
Feature: Form controls under a colour scheme
  In order to use forms with my colours
  As a user
  I need buttons readable and checked boxes distinct from unchecked ones

  Scenario Outline: Submit buttons, checkboxes, radios and text fields under <colour>
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "user preferences" exist:
      | user     | preference                 | value    |
      | student1 | local_accessibility_colour | <colour> |
    And I log in as "student1"
    When the main region contains core controls
    Then the "#la-test-submit" element should have a text contrast of at least 4.5:1
    And the checked control "#la-test-checked" should look different from the unchecked "#la-test-unchecked"
    And the checked control "#la-test-radio" should look different from the unchecked "#la-test-radio-off"
    And the "#la-test-text" element should have a text contrast of at least 7:1

    Examples:
      | colour       |
      | blackwhite   |
      | cream        |
      | yellowblack  |
      | dark         |
