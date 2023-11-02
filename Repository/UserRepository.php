<?php

namespace Idemas\ApprovalWorkflow\Repository;

use PDO;

class UserRepository
{
  public static function getById($db, $id)
  {
    $stmt = $db->prepare("
SELECT
	`u`.`id`,
	`u`.`email`,
	`u`.`username`,
	`p`.`name`
from
	`user` u
INNER JOIN `profile` p ON
	`p`.`user_id` = `u`.`id`
WHERE `u`.`id` = :userId
      ");
    $stmt->execute([':userId' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result;
  }

  public static function getByIds($db, $ids)
  {
    $placeholder = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $db->prepare("
SELECT
	`u`.`id`,
	`u`.`email`,
	`u`.`username`,
	`p`.`name`,
	`u`.`fcmToken`
from
	`user` u
INNER JOIN `profile` p ON
	`p`.`user_id` = `u`.`id`
WHERE `u`.`id` IN ($placeholder)
      ");
    $stmt->execute($ids);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $result;
  }
}