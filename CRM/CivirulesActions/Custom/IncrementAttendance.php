<?php

class CRM_CivirulesActions_Custom_IncrementAttendance extends CRM_Civirules_Action {

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
  }

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    $activity  = $triggerData->getEntityData('Activity');
    $activityContact = $triggerData->getEntityData('ActivityContact');
    $customFieldID   = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_CustomField', 'Number_of_Attendances', 'id', 'name');
    if ($customFieldID && !empty($activity) && !empty($activityContact)) {
      $statusClause  = "";
      $ruleCondition = new CRM_Civirules_BAO_RuleCondition();
      $ruleCondition->rule_id = $triggerData->getTrigger()->getRuleId();
      if ($ruleCondition->find(true)) {
        $data = unserialize($ruleCondition->condition_params);
        if (!empty($data['status_id'])) {
          $op = !empty($data['operator']) ? 'NOT IN' : 'IN';
          $statusClause = " AND a.status_id $op (" . implode(', ', $data['status_id']) . ")";
        }
      }

      if (empty($activity['activity_type_id']) && $activity['activity_id']) {
        // might be an update, for e.g from scheduled to completed
        $activity['activity_type_id'] = CRM_Core_DAO::getFieldValue("CRM_Activity_DAO_Activity", $activity['activity_id'], 'activity_type_id');
      }
      $sql = "SELECT count(*) 
        FROM  civicrm_activity_contact ac
        JOIN  civicrm_activity a on ac.activity_id = a.id
        WHERE ac.contact_id = %1 AND ac.record_type_id = %2
        AND   a.activity_type_id = %3 {$statusClause}";
      $params = [
        1 => [$activityContact['contact_id'], 'Positive'],
        2 => [$activityContact['record_type_id'], 'Positive'],
        3 => [$activity['activity_type_id'], 'Positive'],
      ];
      $num = CRM_Core_DAO::singleValueQuery($sql, $params);
      $result = civicrm_api3('CustomValue', 'create', array(
        'sequential' => 1,
        'entity_id'  => $contactId,
        "custom_{$customFieldID}" => $num,
      ));
    }
  }

}
