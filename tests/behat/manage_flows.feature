@tool @tool_flowboard
Feature: Managing flows
  In order to automate what Moodle does when something happens
  As an administrator
  I need to create a flow, publish it, and see what it has done

  The flow list, the editor and the history screen are all plain links, forms
  and tables — no custom JavaScript widget stands between a keyboard-only or
  screen-reader visitor and any of them, which is the accessible editor phase
  1 promises (D12): the visual canvas is a later phase's addition on top of
  this, not a replacement for it.

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

  Scenario: Building a flow from the "role assigned" template and running it
    When I click on "Somebody is given a role in a course" "link"
    And I set the field "Name" to "Welcome teachers to the forum"
    And I set the field "Stable name" to "welcome-teachers"
    And I set the field "Pattern" to "FORO-GEN"
    And I press "Save flow"
    Then I should see "Welcome teachers to the forum"
    And I should see "needs: mod/forum:managesubscriptions"

    When I click on "Make live" "link" in the "Welcome teachers to the forum" "table_row"
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
