@tool @tool_flowboard @javascript
Feature: Managing flows
  In order to automate what Moodle does when something happens
  As an administrator
  I need to draw a flow, publish it, and see what it has done

  The flow itself is drawn on a visual canvas, but the very same drawing can
  be built and edited through a plain, keyboard-navigable outline instead —
  the "List" view toggle switches between them without losing anything, which
  is how this plugin meets D12 without the canvas and the accessible path
  ever being two different flows.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | idnumber |
      | Course 1 | C1        | C1       |
    And the following "activities" exist:
      | activity | course | name           | idnumber  |
      | forum    | C1     | General forum  | FORO-GEN  |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Terry     | Teacher  |
    And I log in as "admin"
    And I navigate to "Plugins > Admin tools > Flowboard > Flows" in site administration

  Scenario: Drawing a flow through the list view and running it
    When I click on "New flow" "link"
    And I set the field "Name" to "Welcome teachers to the forum"
    And I set the field "Stable name" to "welcome-teachers"
    And I press "Save flow"
    Then I should see "Welcome teachers to the forum"

    And I click on "List" "button"
    And I select "When something happens" from the "Start with" select
    And I click on "Add" "button"
    And I set the field "Event" to "\core\event\role_assigned"
    And I set the field "About" to "relateduserid"
    And I select "Subscribe to matching forums" from the "After \"out\", add" select
    And I click on "Add" "button"
    And I set the field "Match" to "the activity's idnumber"
    And I set the field "Pattern" to "FORO-GEN"
    And I press "Publish"
    Then I should see "Welcome teachers to the forum"

    And I click on "Make live" "link" in the "Welcome teachers to the forum" "table_row"
    Then I should see "Live"

    When I am on "Course 1" course homepage
    And I enrol "Terry Teacher" user as "Teacher"
    And I run all adhoc tasks
    And I navigate to "Plugins > Admin tools > Flowboard > Flows" in site administration
    And I click on "History" "link" in the "Welcome teachers to the forum" "table_row"
    Then I should see "Succeeded"
    And I should see "Terry Teacher"
    And I should see "trigger_event"
    And I should see "action_forum_subscribe"
