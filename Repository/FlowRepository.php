<?php

namespace Idemas\ApprovalWorkflow\Repository;

use PDO;

class FlowRepository
{
  public static function getByType($db, $type)
  {
    $stmt = $db->prepare('SELECT * FROM `wf_flows` WHERE `type` = :type');
    $stmt->execute([':type' => $type]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function getStepsById($db, $flowId)
  {
    $stmt = $db->prepare('SELECT * FROM `wf_flow_steps` WHERE `flow_id` = :flow_id ORDER BY `order`');
    $stmt->execute([':flow_id' => $flowId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  private static function getParamValue($params, $key)
  {
    if (!$params)
      return null;
    return array_key_exists($key, $params) ? $params[$key] : null;
  }

  public static function getStepUsers($db, $stepId, $approvalParemeters)
  {
    $stmt = $db->prepare('SELECT * FROM `wf_flow_step_approvers` WHERE `flow_step_id` = :flow_step_id');
    $stmt->execute([':flow_step_id' => $stepId]);
    $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    for ($i = 0; $i < count($approvers); $i++) {
      $approver = $approvers[$i];

      if ($approver['type'] == 'USER') {
        array_push($tmp, $approver['data']);
      } else if ($approver['type'] == 'GROUP') {
        $stmt = $db->prepare('SELECT user_id FROM `wf_approver_group_users` WHERE `approver_group_id` = :approver_group_id');
        $stmt->execute([':approver_group_id' => $approver['data']]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
        array_push($tmp, ...$rows);
      } else if ($approver['type'] == 'SYSTEM_GROUP') {
        $data = $approver['data'];
        if ($data == 'department-manager') {
          $stmt = $db->prepare('SELECT user_id FROM `wf_department_users` WHERE `department_id` = :department_id AND `job_level` = \'MANAGER\'');
          $stmt->execute([':department_id' => self::getParamValue($approvalParemeters, 'departmentId')]);
          $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
          array_push($tmp, ...$rows);
        } else if ($data == 'department-head') {
          $stmt = $db->prepare('SELECT user_id FROM `wf_department_users` WHERE `department_id` = :department_id AND `job_level` = \'HEAD\'');
          $stmt->execute([':department_id' => self::getParamValue($approvalParemeters, 'departmentId')]);
          $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
          array_push($tmp, ...$rows);
        } else if ($data == 'asset-coordinator') {
          $stmt = $db->prepare('SELECT user_id FROM `wf_asset_coordinator_users` WHERE `asset_category_id` = :asset_category_id');
          $stmt->execute([':asset_category_id' => self::getParamValue($approvalParemeters, 'assetCategoryId')]);
          $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
          array_push($tmp, ...$rows);
        }
      }
    }

    if (count($tmp) <= 0)
      return [];

    return UserRepository::getByIds($db, $tmp);
  }
}