<?php

namespace Shiyan\FileToSqlite;

use Shiyan\FileToSqlite\IteratorRegex\Scenario\FileToSqlite as Scenario;
use Shiyan\IteratorRegex\IteratorRegex;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copies data from a file to an SQLite database.
 */
class FileToSqlite {

  /**
   * Option default values.
   */
  protected const OPTION_DEFAULT_VALUES = [
    'table|t' => InputOption::VALUE_REQUIRED,
    'integer|i' => [],
    'blob' => [],
    'real' => [],
    'numeric' => [],
    'primary|p' => [],
    'append|a' => FALSE,
  ];

  /**
   * Copies data from a file to an SQLite database.
   *
   * @command file-to-sqlite
   *
   * @param string $source
   *   Path to the source file.
   * @param string $destination
   *   Path to the SQLite database file. If not exists, it will be created.
   * @param string $pattern
   *   Regular expression pattern with named subpatterns.
   *
   * @option $table
   *   Table name. By default, the source file name is used.
   * @option $integer
   *   List of integer fields.
   * @option $blob
   *   List of blob fields.
   * @option $real
   *   List of real fields.
   * @option $numeric
   *   List of numeric fields.
   * @option $primary
   *   Primary key(s).
   * @option $append
   *   If the table exists, this option allows to insert into it anyway.
   */
  public function run(OutputInterface $output, string $source, string $destination, string $pattern, array $options = self::OPTION_DEFAULT_VALUES): void {
    $scenario = new Scenario($output, $source, $destination, $pattern, $options);
    $iterator_regex = new IteratorRegex();

    $iterator_regex($scenario);
  }

}
