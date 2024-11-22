<?php

namespace Idemas\ApprovalWorkflow\Repository;

use Idemas\ApprovalWorkflow\Utilities\Utils;
use PDO;

class ApprovalRepository
{
  public static function getCurrentStatus($db, $approvalId)
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'a.*',
        'fs.name as step_name'
      ])
      ->from('wf_approvals a')
      ->leftJoin('wf_flow_steps fs', 'fs.id = a.flow_step_id')
      ->where('a.id = :id')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':id' => $approvalId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result)
      throw new \Exception("Approval with ID '$approvalId' not found!");

    return [
      'id' => $result['id'],
      'flow_id' => $result['flow_id'],
      'status' => $result['status'],
      'flow_step_id' => $result['flow_step_id'],
      'flow_step_name' => $result['step_name'],
      'parameters' => $result['parameters'] ? json_decode($result['parameters'], true) : null,
    ];
  }

  public static function getRunningApprovals($db, $companyId)
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'a.*'
      ])
      ->from('wf_approvals a')
      ->leftJoin('wf_flow_steps fs', 'fs.id = a.flow_step_id')
      ->where('a.company_id = :companyId AND a.status = \'ON_PROGRESS\'')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':companyId' => $companyId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($results as $result) {
      array_push($tmp, [
        'id' => $result['id'],
        'flow_id' => $result['flow_id'],
        'status' => $result['status'],
        'flow_step_id' => $result['flow_step_id'],
        'parameters' => $result['parameters'] ? json_decode($result['parameters'], true) : null,
      ]);
    }

    return $tmp;
  }

  public static function insert($db, $companyId, $flowId, $userId, $parameters): int
  {
    $insertSql = Utils::GetQueryFactory($db)
      ->newInsert()
      ->into('wf_approvals')
      ->cols([
        'company_id',
        'flow_id',
        'status',
        'user_id',
        'parameters',
      ])
      ->set('status', '\'ON_PROGRESS\'')
      ->getStatement();

    $stmt = $db->prepare($insertSql);
    $stmt->execute([
      ':company_id' => $companyId,
      ':flow_id' => $flowId,
      ':user_id' => $userId,
      ':parameters' => $parameters ? json_encode($parameters) : null,
    ]);

    return $db->lastInsertId();
  }

  public static function update($db, $approvalId, $status, $flowStepId, $parameters)
  {
    if ($parameters) {
      $updateSql = Utils::GetQueryFactory($db)
        ->newUpdate()
        ->table('wf_approvals')
        ->cols([
          'flow_step_id',
          'status',
          'parameters',
        ])
        ->where('id = :approval_id')
        ->getStatement();

      $stmt = $db->prepare($updateSql);
      $stmt->execute([
        ':approval_id' => $approvalId,
        ':status' => $status,
        ':flow_step_id' => $flowStepId,
        ':parameters' => json_encode($parameters)
      ]);
    } else {
      $updateSql = Utils::GetQueryFactory($db)
        ->newUpdate()
        ->table('wf_approvals')
        ->cols([
          'flow_step_id',
          'status',
        ])
        ->where('id = :approval_id')
        ->getStatement();

      $stmt = $db->prepare($updateSql);
      $stmt->execute([
        ':approval_id' => $approvalId,
        ':status' => $status,
        ':flow_step_id' => $flowStepId,
      ]);
    }
  }

  public static function isUserHasPermission($db, $approvalId, $userId): bool
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'waau.*'
      ])
      ->from('wf_approval_active_users waau')
      ->where('waau.approval_id = :approval_id AND waau.user_id = :user_id')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approval_id' => $approvalId, ':user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result != null;
  }

  public static function getCurrentApprovers($db, $approvalId): array
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'u.id as user_id',
        'u.username',
        'u.email',
        'u.fcmToken'
      ])
      ->from('wf_approval_active_users waau')
      ->innerJoin('user u', 'u.id = waau.user_id')
      ->where('waau.approval_id = :approval_id')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approval_id' => $approvalId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function getOwner($db, $approvalId): array
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'u.id as user_id',
        'u.username',
        'u.email',
        'u.fcmToken'
      ])
      ->from('user u')
      ->innerJoin('wf_approvals wa', 'wa.user_id = u.id')
      ->where('wa.id = :approval_id')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approval_id' => $approvalId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Menghapus data approver yang lama, dan menggantikan dengan approver yang baru
  public static function assignApprovers($db, $approvalId, $approvers)
  {
    // Hapus semua data approver sebelumnya
    $deleteSql = Utils::GetQueryFactory($db)
      ->newDelete()
      ->from('wf_approval_active_users')
      ->where('approval_id = :approval_id')
      ->getStatement();

    $stmt = $db->prepare($deleteSql);
    $stmt->execute([':approval_id' => $approvalId]);

    // Assign approvers yang baru
    if ($approvers && count($approvers) > 0) {
      $insertSql = Utils::GetQueryFactory($db)
        ->newInsert()
        ->into('wf_approval_active_users')
        ->cols([
          'approval_id',
          'user_id',
        ])
        ->getStatement();

      $stmt = $db->prepare($insertSql);
      foreach ($approvers as $approver) {
        $stmt->execute([':approval_id' => $approvalId, ':user_id' => $approver['id']]);
      }
    }
  }
}