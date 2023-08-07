<?php

namespace Idemas\ApprovalWorkflow\Repository;

use PDO;

class ApprovalRepository
{
  public static function getCurrentStatus($db, $approvalId)
  {
    $stmt = $db->prepare('
SELECT
	a.*,
	fs.`name` as `step_name`
FROM
	`wf_approvals` a
LEFT JOIN `wf_flow_steps` fs ON
	fs.`id` = a.`flow_step_id`
WHERE
  a.`id` = :id
');
    $stmt->execute([':id' => $approvalId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
      'id' => $result['id'],
      'flow_id' => $result['flow_id'],
      'status' => $result['status'],
      'flow_step_id' => $result['flow_step_id'],
      'flow_step_name' => $result['step_name'],
      'parameters' => $result['parameters'] ? json_decode($result['parameters'], true) : null,
    ];
  }

  public static function getRunningApprovals($db)
  {
    $stmt = $db->prepare('
SELECT
	a.*
FROM
	`wf_approvals` a
WHERE 
  a.`status` = \'ON_PROGRESS\'
');
    $stmt->execute();
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

  public static function insert($db, $flowId, $userId, $parameters): int
  {
    $stmt = $db->prepare('
INSERT
	INTO
	`wf_approvals` (`flow_id`,
	`status`,
	`user_id`,
	`parameters`)
VALUES (:flow_id,
\'ON_PROGRESS\',
:user_id,
:parameters)
    ');
    $stmt->execute([
      ':flow_id' => $flowId,
      ':user_id' => $userId,
      ':parameters' => $parameters ? json_encode($parameters) : null,
    ]);

    return $db->lastInsertId();
  }
  public static function update($db, $approvalId, $status, $flowStepId)
  {
    $stmt = $db->prepare('
UPDATE
	`wf_approvals`
SET
	`flow_step_id` = :flow_step_id,
	`status` = :status
WHERE
	`id` = :approval_id
    ');
    $stmt->execute([
      ':approval_id' => $approvalId,
      ':status' => $status,
      ':flow_step_id' => $flowStepId,
    ]);
  }

  public static function isUserHasPermission($db, $approvalId, $userId): bool
  {
    $stmt = $db->prepare('
SELECT
	*
FROM
	`wf_approval_active_users` waau
WHERE
	`waau`.`approval_id` = :approval_id
	AND `waau`.`user_id` = :user_id
');
    $stmt->execute([':approval_id' => $approvalId, ':user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result != null;
  }

  // Menghapus data approver yang lama, dan menggantikan dengan approver yang baru
  public static function assignApprovers($db, $approvalId, $approvers)
  {
    // Hapus semua data approver sebelumnya
    $stmt = $db->prepare('DELETE FROM `wf_approval_active_users` WHERE `approval_id` = :approval_id');
    $stmt->execute([':approval_id' => $approvalId]);

    // Assign approvers yang baru
    $stmt = $db->prepare("INSERT INTO `wf_approval_active_users` (`approval_id`, `user_id`) VALUES (:approval_id, :user_id)");
    foreach ($approvers as $approver) {
      $stmt->execute([':approval_id' => $approvalId, ':user_id' => $approver['id']]);
    }
  }
}