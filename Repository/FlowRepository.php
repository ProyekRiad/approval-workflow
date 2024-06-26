<?php

namespace Idemas\ApprovalWorkflow\Repository;

use PDO;

class FlowRepository
{
  public static function getByType($db, $companyId, $type)
  {
    $stmt = $db->prepare('SELECT * FROM `wf_flows` WHERE `company_id` = :companyId AND `type` = :type');
    $stmt->execute([':companyId' => $companyId, ':type' => $type]);
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

      // Handle Type USER
      if ($approver['type'] == 'USER') {
        array_push($tmp, $approver['data']);

        // Handle Type GROUP
      } else if ($approver['type'] == 'GROUP') {
        $stmt = $db->prepare('SELECT user_id FROM `wf_approver_group_users` WHERE `approver_group_id` = :approver_group_id');
        $stmt->execute([':approver_group_id' => $approver['data']]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
        array_push($tmp, ...$rows);

        // Handle Type SYSTEM_GROUP
      } else if ($approver['type'] == 'SYSTEM_GROUP') {
        $data = $approver['data'];

        // Handle department-manager
        if ($data == 'department-manager') {
          $overrideUserId = self::getParamValue($approvalParemeters, 'overrideManagerUserId');
          if ($overrideUserId) {
            array_push($tmp, $overrideUserId);
          } else {
            $stmt = $db->prepare('SELECT user_id FROM `wf_department_users` WHERE `department_id` = :department_id AND `job_level` = \'MANAGER\'');
            $stmt->execute([':department_id' => self::getParamValue($approvalParemeters, 'departmentId')]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
            array_push($tmp, ...$rows);
          }

          // Handle department-head
        } else if ($data == 'department-head') {
          $overrideUserId = self::getParamValue($approvalParemeters, 'overrideHeadUserId');
          if ($overrideUserId) {
            array_push($tmp, $overrideUserId);
          } else {
            $stmt = $db->prepare('SELECT user_id FROM `wf_department_users` WHERE `department_id` = :department_id AND `job_level` = \'HEAD\'');
            $stmt->execute([':department_id' => self::getParamValue($approvalParemeters, 'departmentId')]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
            array_push($tmp, ...$rows);
          }

          // Handle asset-coordinator
        } else if ($data == 'asset-coordinator') {
          $stmt = $db->prepare('SELECT user_id FROM `wf_asset_coordinator_users` WHERE `asset_category_id` = :asset_category_id');
          $stmt->execute([':asset_category_id' => self::getParamValue($approvalParemeters, 'assetCategoryId')]);
          $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
          array_push($tmp, ...$rows);

          // Handle origin-asset-user
        } else if ($data == 'origin-asset-user') {
          $userId = self::getParamValue($approvalParemeters, 'originAssetUserId');
          if ($userId)
            array_push($tmp, $userId);

          // Handle destination-asset-user
        } else if ($data == 'destination-asset-user') {
          $userId = self::getParamValue($approvalParemeters, 'destinationAssetUserId');
          if ($userId)
            array_push($tmp, $userId);
        }
      }
    }

    if (count($tmp) <= 0)
      return [];

    return UserRepository::getByIds($db, $tmp);
  }
}