<?php

namespace Idemas\ApprovalWorkflow\v2\Repository;

use Idemas\ApprovalWorkflow\Utilities\Utils;
use PDO;

class FlowRepository
{
  public static function getById($db, $companyId, $flowId)
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'af.*',
      ])
      ->from('approver_flow af')
      ->where('af.company_id = :companyId AND af.id = :id AND af.is_deleted = 0')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':companyId' => $companyId, ':id' => $flowId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
}