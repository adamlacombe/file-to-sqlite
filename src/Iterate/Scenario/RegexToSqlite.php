<?php

namespace Shiyan\FileToSqlite\Iterate\Scenario;

use Shiyan\Iterate\Scenario\BaseRegexScenario;
use Shiyan\LiteSqlInsert\Iterate\Scenario\InsertNamedMatchTrait;

/**
 * Regex based Iterate scenario to copy data from a file to an SQLite database.
 */
class RegexToSqlite extends BaseRegexScenario {

  use ToSqliteTrait, InsertNamedMatchTrait {
    ToSqliteTrait::preRun insteadof InsertNamedMatchTrait;
    ToSqliteTrait::postRun insteadof InsertNamedMatchTrait;
    InsertNamedMatchTrait::setConnection insteadof ToSqliteTrait;
    InsertNamedMatchTrait::getConnection insteadof ToSqliteTrait;
    InsertNamedMatchTrait::setTable insteadof ToSqliteTrait;
    ToSqliteTrait::getTable insteadof InsertNamedMatchTrait;
    InsertNamedMatchTrait::setFields insteadof ToSqliteTrait;
    InsertNamedMatchTrait::getFields insteadof ToSqliteTrait;
  }

}
