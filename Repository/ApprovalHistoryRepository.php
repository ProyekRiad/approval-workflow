<?php

namespace Idemas\ApprovalWorkflow\Repository;

const HFLAG_CREATED = "created";
const HFLAG_RESET = "reset";
const HFLAG_APPROVED = "approved";
const HFLAG_REJECTED = "rejected";
const HFLAG_SYSTEM_REJECTED = "system_rejected";
const HFLAG_DONE = "done";
const HFLAG_SKIP = "skip";

class ApprovalHistoryRepository
{
  public static function insert($db, $approvalId, $userId, $title, $flag, $notes, $file)
  {
    $stmt = $db->prepare("
INSERT INTO `wf_approval_histories` (`approval_id`, `user_id`, `title`, `flag`, `notes`, `file`, `date_time`)
VALUES (:approval_id, :user_id, :title, :flag, :notes, :file, :date_time) 
        ");
    $stmt->execute([
      ':approval_id' => $approvalId,
      ':user_id' => $userId,
      ':title' => $title,
      ':flag' => $flag,
      ':notes' => $notes,
      ':file' => $file,
      ':date_time' => time()
    ]);
  }
}