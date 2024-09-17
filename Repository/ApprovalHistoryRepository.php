<?php

namespace Idemas\ApprovalWorkflow\Repository;

use PDO;

const HFLAG_CREATED = "created";
const HFLAG_RESET = "reset";
const HFLAG_APPROVED = "approved";
const HFLAG_REJECTED = "rejected";
const HFLAG_SYSTEM_REJECTED = "system_rejected";
const HFLAG_DONE = "done";
const HFLAG_SKIP = "skip";

class ApprovalHistoryRepository
{
  public static function insert($db, $approvalId, $flowStepId, $userId, $title, $flag, $notes, $file)
  {
    $stmt = $db->prepare("
INSERT INTO `wf_approval_histories` (`approval_id`, `flow_step_id`, `user_id`, `title`, `flag`, `notes`, `file`, `date_time`)
VALUES (:approval_id, :flow_step_id, :user_id, :title, :flag, :notes, :file, :date_time) 
        ");
    $stmt->execute([
      ':approval_id' => $approvalId,
      ':flow_step_id' => $flowStepId,
      ':user_id' => $userId,
      ':title' => $title,
      ':flag' => $flag,
      ':notes' => $notes,
      ':file' => $file,
      ':date_time' => time()
    ]);
  }

  public static function getAllByApprovalId($db, $approvalId): array
  {
    $stmt = $db->prepare("
SELECT
	wah.id,
	wah.approval_id,
	wah.flow_step_id,
	wfs.`order` as flow_step_order,
	wfs.flow_id as flow_step_flow_id,
	wfs.name as flow_step_name,
	wfs.`condition` as flow_step_condition,
	wah.user_id,
	u.email as user_email,
	u.username as user_username,
	p.name as user_name,
	wah.title,
	wah.flag,
	wah.notes,
	wah.file,
	wah.date_time
FROM
	wf_approval_histories wah
LEFT JOIN `user` u on
	u.id = wah.user_id
LEFT JOIN `profile` p ON
	`p`.`user_id` = `u`.`id`
LEFT JOIN wf_flow_steps wfs On
	wfs.id = wah.flow_step_id
WHERE
	wah.approval_id = :approval_id
ORDER BY
  wah.date_time ASC;
      ");
    $stmt->execute([':approval_id' => $approvalId]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $result;
  }
}