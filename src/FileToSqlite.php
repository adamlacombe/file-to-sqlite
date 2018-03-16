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
    'table' => InputOption::VALUE_REQUIRED,
    'integer' => [],
    'blob' => [],
    'real' => [],
    'numeric' => [],
    'primary' => InputOption::VALUE_REQUIRED,
  ];

  /**
   * Copies data from a file to an SQLite database.
   *
   * @command file-to-sqlite
   *
   * @param string $source
   *   Path to the source file.
   * @param string $destination
   *   Path to where to create an SQLite database.
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
   *   Primary key.
   */
  public function run(OutputInterface $output, string $source, string $destination, string $pattern, array $options = self::OPTION_DEFAULT_VALUES): void {
    $scenario = new Scenario($output, $source, $destination, $pattern, $options);
    $iterator_regex = new IteratorRegex();

    $iterator_regex($scenario);
  }

}
