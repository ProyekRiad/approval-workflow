<?php

namespace Idemas\ApprovalWorkflow\Utilities;

use Aura\SqlQuery\QueryFactory;
use PDO;

class Utils
{
  public static function GetQueryFactory($pdoDb): QueryFactory
  {
    // NOTE: Jika menggunakan ODBC, maka cara ini harus diimprove supaya 
    // bisa mengenali database apa yang digunakan ODBC tersebut
    $driver = $pdoDb->getAttribute(PDO::ATTR_DRIVER_NAME);

    return new QueryFactory($driver);
  }
}