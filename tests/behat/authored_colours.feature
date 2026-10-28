@local @local_accessibility @javascript @la_authored_colours
Feature: Colours written into authored text give way to the colour scheme
  In order to read pasted or old content in my colour scheme
  As a user
  I need its inline colours, backgrounds and legacy colour attributes replaced by the scheme's

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
      | activity | course | idnumber | name    | content                                                                                                                                                                                                                                                                             |
      | page     | C1     | page1    | Colours | <p><span id="c-inline" style="color: #aa0000;">Inline colour</span></p><p id="c-bgshort" style="background: #ffff66;">Background shorthand</p><p><font id="c-font" color="#aa0000">Legacy font colour</font></p><table><tr><td id="c-bgcolor" bgcolor="#ffffcc">Table cell</td></tr></table> |
    And the following "user preferences" exist:
      | user     | preference                 | value |
      | student1 | local_accessibility_colour | dark  |

  Scenario: In the dark scheme, authored colours and backgrounds take the scheme's text on a clear background
    When I am on the "page1" "Activity" page logged in as "student1"
    Then the page root should have attribute "data-a11y-colour" with value "dark"
    And the computed "color" of "#c-inline" should be "rgb(232, 238, 243)"
    And the computed "color" of "#c-bgshort" should be "rgb(232, 238, 243)"
    And the computed "background-color" of "#c-bgshort" should be "rgba(0, 0, 0, 0)"
    And the computed "color" of "#c-font" should be "rgb(232, 238, 243)"
    And the computed "color" of "#c-bgcolor" should be "rgb(232, 238, 243)"
    And the computed "background-color" of "#c-bgcolor" should be "rgba(0, 0, 0, 0)"
