<?php

namespace Idemas\ApprovalWorkflow\Repository;

use Idemas\ApprovalWorkflow\Utilities\Utils;
use PDO;

class FlowRepository
{
  public static function getByType($db, $companyId, $type)
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'wf.*',
      ])
      ->from('wf_flows wf')
      ->where('wf.company_id = :companyId AND wf.type = :type')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':companyId' => $companyId, ':type' => $type]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function getStepsById($db, $flowId)
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'wfs.*',
      ])
      ->from('wf_flow_steps wfs')
      ->where('wfs.flow_id = :flow_id')
      ->orderBy(['wfs.order'])
      ->getStatement();

    $stmt = $db->prepare($selectSql);
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
    $qf = Utils::GetQueryFactory($db);

    $selectSql = $qf->newSelect()
      ->cols([
        'wf.*',
      ])
      ->from('wf_flow_step_approvers wf')
      ->where('wf.flow_step_id = :flow_step_id')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
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
        $selectSql = $qf->newSelect()
          ->cols([
            't.user_id',
          ])
          ->from('wf_approver_group_users t')
          ->where('t.approver_group_id = :approver_group_id')
          ->getStatement();

        $stmt = $db->prepare($selectSql);
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
            $selectSql = $qf->newSelect()
              ->cols([
                't.user_id',
              ])
              ->from('wf_department_users t')
              ->where('t.department_id = :department_id AND t.job_level = \'MANAGER\'')
              ->getStatement();

            $stmt = $db->prepare($selectSql);
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
            $selectSql = $qf->newSelect()
              ->cols([
                't.user_id',
              ])
              ->from('wf_department_users t')
              ->where('t.department_id = :department_id AND t.job_level = \'HEAD\'')
              ->getStatement();

            $stmt = $db->prepare($selectSql);
            $stmt->execute([':department_id' => self::getParamValue($approvalParemeters, 'departmentId')]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
            array_push($tmp, ...$rows);
          }

          // Handle asset-coordinator
        } else if ($data == 'asset-coordinator') {
          $selectSql = $qf->newSelect()
            ->cols([
              't.user_id',
            ])
            ->from('wf_asset_coordinator_users t')
            ->where('t.sset_category_id = :asset_category_id')
            ->getStatement();

          $stmt = $db->prepare($selectSql);
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