<?php

namespace Idemas\ApprovalWorkflow\v2\Repository;

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
        'fs.level as step_name'
      ])
      ->from('wf2_approvals a')
      ->leftJoin('wf2_approval_steps fs', 'fs.id = a.approval_step_id AND fs.approval_id = a.id')
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
      'approval_step_id' => $result['approval_step_id'],
      'approval_step_name' => $result['step_name'],
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
      ->into('wf2_approvals')
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

  public static function update($db, $approvalId, $status, $approvalStepId, $parameters)
  {
    if ($parameters) {
      $updateSql = Utils::GetQueryFactory($db)
        ->newUpdate()
        ->table('wf2_approvals')
        ->cols([
          'approval_step_id',
          'status',
          'parameters',
        ])
        ->where('id = :approval_id')
        ->getStatement();

      $stmt = $db->prepare($updateSql);
      $stmt->execute([
        ':approval_id' => $approvalId,
        ':status' => $status,
        ':approval_step_id' => $approvalStepId,
        ':parameters' => json_encode($parameters)
      ]);
    } else {
      $updateSql = Utils::GetQueryFactory($db)
        ->newUpdate()
        ->table('wf2_approvals')
        ->cols([
          'approval_step_id',
          'status',
        ])
        ->where('id = :approval_id')
        ->getStatement();

      $stmt = $db->prepare($updateSql);
      $stmt->execute([
        ':approval_id' => $approvalId,
        ':status' => $status,
        ':approval_step_id' => $approvalStepId,
      ]);
    }
  }

  public static function getApprovalSteps($db, $approvalId): mixed
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'was.*',
      ])
      ->from('wf2_approval_steps was')
      ->where('was.approval_id = :approval_id')
      ->orderBy(['was.level'])
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approval_id' => $approvalId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function isUserHasPermission($db, $approvalId, $userId): bool
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'was.*'
      ])
      ->from('wf2_approval_steps was')
      ->where('was.approval_id = :approval_id AND was.user_id = :user_id')
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
      ->from('wf2_approval_steps was')
      ->innerJoin('user u', 'u.id = was.user_id')
      ->innerJoin('wf2_approvals wa', 'wa.id = was.approval_id')
      ->where('was.approval_id = :approval_id AND was.id = wa.approval_step_id')
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
      ->innerJoin('wf2_approvals wa', 'wa.user_id = u.id')
      ->where('wa.id = :approval_id')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approval_id' => $approvalId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Mengambil daftar step approval yang cocok dengan approval yang dipilih
  public static function getMatchedFlowSteps($db, $approvalId): array
  {// Ambil data approval
    $approval = self::getCurrentStatus($db, $approvalId);
    $parameters = $approval['parameters'];

    // Ambil data flow steps
    // Ambil semua step yang ada dari flow sesuai data approval
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'afi.*'
      ])
      ->from('approver_flow_item afi')
      ->where('afi.approver_flow_id = :approver_flow_id AND afi.is_deleted = 0')
      ->orderBy(['afi.level'])
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approver_flow_id' => $approval['flow_id']]);
    $flowSteps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil max id dari flowSteps sesuai amount
    $maxId = null;
    foreach ($flowSteps as $flowStep) {
      $startFee = is_numeric($flowStep['start_fee']) ? floatval($flowStep['start_fee']) : 0;
      $endFee = is_numeric($flowStep['end_fee']) ? floatval($flowStep['end_fee']) : 0;

      $amount = $parameters['amount'];
      if ($amount >= $startFee && $amount <= $endFee || ($startFee == 0 && $endFee == 0)) {
        $maxId = $flowStep['id'];
      }
    }

    // Ambil data flowsteps hingga maxId untuk dijadikan approval steps
    $approvalSteps = [];
    $maxIdFound = false;
    foreach ($flowSteps as $flowStep) {
      if ($maxIdFound)
        continue;

      array_push($approvalSteps, $flowStep);

      if ($maxId == $flowStep['id'])
        $maxIdFound = true;
    }

    return $approvalSteps;
  }

  // Menghapus data approver yang lama, dan menggantikan dengan approver yang baru
  public static function buildApprovalSteps($db, $approvalId)
  {
    $approvalSteps = self::getMatchedFlowSteps($db, $approvalId);

    // Hapus semua data step sebelumnya
    $deleteSql = Utils::GetQueryFactory($db)
      ->newDelete()
      ->from('wf2_approval_steps')
      ->where('approval_id = :approval_id')
      ->getStatement();

    $stmt = $db->prepare($deleteSql);
    $stmt->execute([':approval_id' => $approvalId]);

    // Insert approval steps baru
    if ($approvalSteps && count($approvalSteps) > 0) {
      $insertSql = Utils::GetQueryFactory($db)
        ->newInsert()
        ->into('wf2_approval_steps')
        ->cols([
          'approval_id',
          'flow_id',
          'flow_item_id',
          'company_id',
          'user_id',
          'level',
          'start_fee',
          'end_fee',
          'sla',
          'category',
        ])
        ->getStatement();

      $stmt = $db->prepare($insertSql);
      foreach ($approvalSteps as $approvalStep) {
        $stmt->execute([
          'approval_id' => $approvalId,
          'flow_id' => $approvalStep['approver_flow_id'],
          'flow_item_id' => $approvalStep['id'],
          'company_id' => $approvalStep['company_id'],
          'user_id' => $approvalStep['user_id'],
          'level' => $approvalStep['level'],
          'start_fee' => $approvalStep['start_fee'],
          'end_fee' => $approvalStep['end_fee'],
          'sla' => $approvalStep['sla'],
          'category' => $approvalStep['category'],
        ]);
      }
    }
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