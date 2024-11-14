<?php

namespace Idemas\ApprovalWorkflow\Repository;

use Idemas\ApprovalWorkflow\Utilities\Utils;
use PDO;

class UserRepository
{
  public static function getById($db, $id)
  {
    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'u.id',
        'u.email',
        'u.username',
        'p.name',
      ])
      ->from('user u')
      ->innerJoin('profile p', 'p.user_id = u.id')
      ->where('u.id = :userId')
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute([':userId' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result;
  }

  public static function getByIds($db, $ids)
  {
    $placeholder = str_repeat('?,', count($ids) - 1) . '?';

    $selectSql = Utils::GetQueryFactory($db)
      ->newSelect()
      ->cols([
        'u.id',
        'u.email',
        'u.username',
        'p.name',
        'u.fcmToken',
      ])
      ->from('user u')
      ->innerJoin('profile p', 'p.user_id = u.id')
      ->where("u.id IN ($placeholder)")
      ->getStatement();

    $stmt = $db->prepare($selectSql);
    $stmt->execute($ids);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $result;
  }
}