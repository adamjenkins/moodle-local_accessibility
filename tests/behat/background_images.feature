@local @local_accessibility @javascript @la_background_images
Feature: Photos drawn as background images follow the Images setting
  In order to read a course whose tiles or cards show photos as background images
  As a user
  I need those photos hidden or dimmed like other images, with the text on them left as it is (issue #3)

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
    And the following "activities" exist:
      | activity | course | idnumber | name        | content           |
      | page     | C1     | page1    | Photo tiles | <p>Photo tiles</p> |

  Scenario: Hidden drops the background photo and keeps the tile's text
    Given the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_images | hide  |
    And I am on the "page1" "Activity" page logged in as "student1"
    When the HTML "<ul><li id='t-tile' style='background-image: url(/pix/moodlelogo.png); height: 60px;'><a href='#'><h3>Week one</h3></a></li></ul><div id='t-plain' style='background-color: #eee;'>Plain</div>" is added to the main region
    Then the computed "background-image" of "#t-tile" should be "none"
    And "Week one" "text" should be visible
    And the computed "background-color" of "#t-plain" should not be "rgba(0, 0, 0, 0)"

  Scenario: Dimmed veils the background photo until the tile is hovered, and Off leaves it alone
    Given the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_images | dim   |
    And I am on the "page1" "Activity" page logged in as "student1"
    When the HTML "<ul><li id='t-tile' style='background-image: url(/pix/moodlelogo.png); height: 60px;'><a id='t-link' href='#'><h3>Week one</h3></a></li></ul>" is added to the main region
    Then the computed "box-shadow" of "#t-tile" should not be "none"
    And the computed "opacity" of "#t-tile" should be "1"
    And the computed "background-image" of "#t-tile" should not be "none"
    When I hover "#t-link" "css_element"
    Then the computed "box-shadow" of "#t-tile" should be "none"
    When I click on "Accessibility settings" "button"
    And I click on "Images, Dimmed" "button"
    And I click on "[data-feature='images'][data-value='off']" "css_element"
    Then the page root should not have attribute "data-a11y-images"
    And the computed "box-shadow" of "#t-tile" should be "none"
