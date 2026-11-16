@mod @mod_simplequiz2 @javascript
Feature: Simple quiz web service calls survive a lost connection
  In order to keep answering through short network losses
  As a user
  I need simple quiz web service failures to be classified and retried safely

  Scenario: The simple quiz network module classifies failures and never resends a call
    Given I log in as "admin"
    When I am on fixture page "/mod/simplequiz2/tests/behat/fixtures/network_testpage.php"
    And I wait until "DONE" "text" exists
    Then I should see "PASS h"
    And I should see "PASS i"
    And I should not see "FAIL"
