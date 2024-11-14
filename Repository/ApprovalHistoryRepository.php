<?php

namespace Idemas\ApprovalWorkflow\Repository;

use Idemas\ApprovalWorkflow\Utilities\Utils;
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
    $insertSql = Utils::GetQueryFactory($db)
      ->newInsert()
      ->into('wf_approval_histories')
      ->cols([
        'approval_id',
        'flow_step_id',
        'user_id',
        'title',
        'flag',
        'notes',
        'file',
        'date_time',
      ])
      ->getStatement();

    $stmt = $db->prepare($insertSql);
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
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'wah.id',
        'wah.approval_id',
        'wah.flow_step_id',
        'wfs.order as flow_step_order',
        'wfs.flow_id as flow_step_flow_id',
        'wfs.name as flow_step_name',
        'wfs.condition as flow_step_condition',
        'wah.user_id',
        'u.email as user_email',
        'u.username as user_username',
        'p.name as user_name',
        'wah.title',
        'wah.flag',
        'wah.notes',
        'wah.file',
        'wah.date_time',
      ])
      ->from('wf_approval_histories wah')
      ->leftJoin('user u', 'u.id = wah.user_id')
      ->leftJoin('profile p', 'p.user_id = u.id')
      ->leftJoin('wf_flow_steps wfs', 'wfs.id = wah.flow_step_id')
      ->where('wah.approval_id = :approval_id')
      ->orderBy(['wah.date_time ASC']);

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':approval_id' => $approvalId]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $result;
  }
}