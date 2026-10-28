@local @local_accessibility @javascript @la_inline_sizes
Feature: Text with a fixed inline font size follows the text size
  In order to read authored text that was given a fixed size in the editor
  As a user
  I need that text, its line height and any fixed box around it to follow the text size I choose (issue #2)

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
      | activity | course | idnumber | name         | content                                                                                                                                                                                                                                                                                         |
      | page     | C1     | page1    | Inline sizes | <p><span id="t-pt" style="font-size: 9pt;">Nine point</span></p><p id="t-px" style="font-size: 12px;">Twelve pixel</p><p><span id="t-outer" style="font-size: 18pt;">Outer <span id="t-inner" style="font-size: 6pt;">inner</span></span></p><p><span id="t-kw" style="font-size: small;">Keyword</span></p><p><font id="t-font" size="2">Legacy font tag</font></p><p id="t-lh" style="line-height: 15pt;">Fixed line height</p><div id="t-box" style="height: 30px; overflow: hidden;">Fixed box</div><img id="t-img" style="height: 20px;" alt=""> |

  Scenario: At 200% fixed inline sizes double, nested ones do not compound, and they follow a new size live
    Given the following "user preferences" exist:
      | user     | preference               | value |
      | student1 | local_accessibility_size | 200   |
    And I am on the "page1" "Activity" page logged in as "student1"
    Then the computed "font-size" of "#t-pt" should be "24px"
    And the computed "font-size" of "#t-px" should be "24px"
    And the computed "font-size" of "#t-outer" should be "48px"
    And the computed "font-size" of "#t-inner" should be "16px"
    And the computed "font-size" of "#t-kw" should be "26px"
    And the computed "font-size" of "#t-font" should be "26px"
    And the computed "line-height" of "#t-lh" should be "40px"
    And the computed "height" of "#t-box" should be "60px"
    And the computed "height" of "#t-img" should be "20px"
    When the HTML "<p id='t-late' style='font-size: 12px'>Loaded later</p><div contenteditable='true'><p id='t-edit' style='font-size: 12px'>Editable</p></div>" is added to the main region
    Then the computed "font-size" of "#t-late" should be "24px"
    And the computed "font-size" of "#t-edit" should be "12px"
    And the "style" attribute of "#t-edit" "css_element" should contain "font-size: 12px"
    When I click on "Accessibility settings" "button"
    And I click on "Text size, 200%" "button"
    And I click on "[data-feature='size'][data-value='150']" "css_element"
    Then the page root should have attribute "data-a11y-size" with value "150"
    And the computed "font-size" of "#t-px" should be "18px"
    And the computed "font-size" of "#t-late" should be "18px"

  Scenario: At the default size nothing is rewritten until a size is chosen
    Given I am on the "page1" "Activity" page logged in as "student1"
    Then the computed "font-size" of "#t-px" should be "12px"
    And the "style" attribute of "#t-px" "css_element" should contain "font-size: 12px"
    And the computed "font-size" of "#t-kw" should be "13px"
    When I click on "Accessibility settings" "button"
    And I click on "Text size, 100%" "button"
    And I click on "[data-feature='size'][data-value='150']" "css_element"
    Then the computed "font-size" of "#t-px" should be "18px"
    And the computed "font-size" of "#t-kw" should be "19.5px"
    And the HTML "<p id='t-late' style='font-size: 9pt'>Loaded later</p>" is added to the main region
    And the computed "font-size" of "#t-late" should be "18px"
    And the computed "line-height" of "#t-lh" should be "30px"
    And the computed "height" of "#t-box" should be "45px"
