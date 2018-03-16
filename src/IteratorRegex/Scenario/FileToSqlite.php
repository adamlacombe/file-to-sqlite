<?php

namespace Shiyan\FileToSqlite\IteratorRegex\Scenario;

use Shiyan\IteratorRegex\Scenario\BaseScenario;
use Shiyan\IteratorRegex\Scenario\ConsoleProgressBarTrait;
use Shiyan\LiteSqlInsert\Connection;
use Shiyan\LiteSqlInsert\ConnectionInterface;
use Shiyan\LiteSqlInsert\IteratorRegex\Scenario\InsertNamedMatchTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Iterator Regex scenario to copy data from a file to an SQLite database.
 */
class FileToSqlite extends BaseScenario {

  use InsertNamedMatchTrait, ConsoleProgressBarTrait {
    InsertNamedMatchTrait::preRun as protected insertPreRun;
    InsertNamedMatchTrait::postRun as protected insertPostRun;
    ConsoleProgressBarTrait::preRun as protected progressPreRun;
    ConsoleProgressBarTrait::postRun as protected progressPostRun;
  }

  /**
   * A ConnectionInterface instance.
   *
   * @var \Shiyan\LiteSqlInsert\ConnectionInterface
   */
  protected $connection;

  /**
   * Database file temporary path.
   *
   * @var string
   */
  protected $dbFile;

  /**
   * Destination path.
   *
   * @var string
   */
  protected $destination;

  /**
   * Filesystem utility class instance.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $filesystem;

  /**
   * Options.
   *
   * @var array
   */
  protected $options;

  /**
   * An OutputInterface instance.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Regular expression pattern.
   *
   * @var string
   */
  protected $pattern;

  /**
   * FileToSqlite constructor.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   An OutputInterface instance.
   * @param string $source
   *   Path to the file with a source data.
   * @param string $destination
   *   Path to where to create an SQLite database.
   * @param string $pattern
   *   Regular expression pattern with named subpatterns.
   * @param array $options
   *   (optional) Options array with elements:
   *   - integer: Indexed array with names of integer fields.
   *   - blob: Indexed array with names of blob fields.
   *   - real: Indexed array with names of real fields.
   *   - numeric: Indexed array with names of numeric fields.
   *   - primary: Name of the primary key field.
   */
  public function __construct(OutputInterface $output, string $source, string $destination, string $pattern, array $options = []) {
    $file = new \SplFileObject($source, 'rb');
    $file->setFlags(\SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY);

    parent::__construct($file);

    $this->destination = $destination;
    $this->filesystem = new Filesystem();
    $this->options = $options;
    $this->output = $output;
    $this->pattern = $pattern;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConnection(): ConnectionInterface {
    return $this->connection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPattern(): string {
    return $this->pattern;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTable(): string {
    return 'main';
  }

  /**
   * {@inheritdoc}
   */
  protected function getOutput(): OutputInterface {
    return $this->output;
  }

  /**
   * Returns an option by name.
   *
   * @param string $name
   *   Option name.
   * @param mixed $default
   *   (optional) Default value to return if the option is not set.
   *
   * @return mixed
   *   Option value or default value.
   */
  protected function getOption(string $name, $default = NULL) {
    return $this->options[$name] ?? $default;
  }

  /**
   * Validates destination path.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   *   If the destination path already exists or cannot be created.
   */
  protected function validateDestination(): void {
    $destination = new \SplFileInfo($this->destination);

    if ($destination->getRealPath() !== FALSE) {
      throw new InvalidArgumentException($destination . ' already exists.');
    }

    $parent = $destination->getPathInfo();

    if (!$parent->isDir()) {
      throw new InvalidArgumentException($parent . ' is not a directory.');
    }
    if (!$parent->isWritable()) {
      throw new InvalidArgumentException($parent . ' is not writable.');
    }
  }

  /**
   * Validates fields.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   *   If there are no fields in the pattern or they are duplicated.
   * @throws \Symfony\Component\Console\Exception\InvalidOptionException
   *   If options contain non-existing or repeating (type) fields.
   */
  protected function validateFields(): void {
    $names = $this->getFields();
    $fields = array_fill_keys($names, FALSE);

    if (count($fields) != count($names)) {
      throw new InvalidArgumentException('Subpattern names must not be duplicated.');
    }
    if (!$fields) {
      throw new InvalidArgumentException('Pattern must contain named subpatterns.');
    }

    $primary = $this->getOption('primary');

    if ($primary && !isset($fields[$primary])) {
      throw new InvalidOptionException('The "--primary" option contains non-existent field "' . $primary . '".');
    }

    foreach (['integer', 'blob', 'real', 'numeric'] as $type) {
      foreach ($this->getOption($type, []) as $field) {
        if (!isset($fields[$field])) {
          throw new InvalidOptionException('The "--' . $type . '" option contains non-existent field "' . $field . '".');
        }
        if ($fields[$field] !== FALSE) {
          throw new InvalidOptionException('Options "--' . $type . '" and "--' . $fields[$field] . '" both contain the same field "' . $field . '".');
        }

        $fields[$field] = $type;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preRun(): void {
    $this->validateDestination();
    $this->validateFields();

    $this->dbFile = $this->filesystem->tempnam(sys_get_temp_dir(), 'file-to-sqlite-');

    $pdo = new \PDO('sqlite:' . $this->dbFile);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // The syncing feature is unnecessary, because we're writing to a temporary
    // file. The journaling mode is unnecessary, because we don't use rollbacks.
    // @link https://www.sqlite.org/pragma.html#pragma_synchronous
    // @link https://www.sqlite.org/pragma.html#pragma_journal_mode
    $pdo->exec('PRAGMA synchronous = OFF');
    $pdo->exec('PRAGMA journal_mode = OFF');

    $names = $this->getFields();
    $fields = array_combine($names, $names);

    foreach ($names as $field) {
      foreach (['integer', 'blob', 'real', 'numeric'] as $type) {
        if (in_array($field, $this->getOption($type, []))) {
          $fields[$field] .= ' ' . strtoupper($type);
          continue 2;
        }
      }

      $fields[$field] .= ' TEXT';
    }

    if ($field = $this->getOption('primary')) {
      $fields[$field] .= ' PRIMARY KEY';
    }

    $pdo->exec('CREATE TABLE ' . $this->getTable() . ' (' . implode(', ', $fields) . ')');

    $this->connection = new Connection($pdo);

    $this->insertPreRun();
    $this->progressPreRun();
  }

  /**
   * {@inheritdoc}
   */
  public function postRun(): void {
    $this->insertPostRun();
    $this->progressPostRun();
    $this->insert = NULL;
    $this->connection = NULL;
    $this->filesystem->rename($this->dbFile, $this->destination);
  }

}
