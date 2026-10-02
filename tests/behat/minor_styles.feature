@local @local_accessibility @javascript @la_minorstyles
Feature: Content styles leave structure, maths and theme behaviour alone
  In order to keep pages usable while my settings are on
  As a user
  I need each setting to change only what it is meant to change

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |

  Scenario: Spacing leaves rendered maths alone
    Given the following "user preferences" exist:
      | user     | preference                  | value |
      | student1 | local_accessibility_spacing | wcag  |
    And I log in as "student1"
    When the main region contains core controls
    And the page has the extra style ".MathJax, .MathJax span { letter-spacing: normal; word-spacing: normal; }"
    Then the computed "letter-spacing" of "#la-test-maths" should be "normal"
    And the computed "letter-spacing" of "#la-test-controls p" should not be "normal"

  Scenario: Stop motion also stops smooth scrolling of the page itself
    Given the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_motion | on    |
    And I log in as "student1"
    When the page has the extra style ":root { scroll-behavior: smooth; }"
    Then the computed "scroll-behavior" of "html" should be "auto"
