<?php

namespace Shiyan\FileToSqlite\Iterate\Scenario;

use Shiyan\Iterate\Scenario\ConsoleProgressBarTrait;
use Shiyan\Iterate\Scenario\ScenarioInterface;
use Shiyan\LiteSqlInsert\Connection;
use Shiyan\LiteSqlInsert\Iterate\Scenario\BaseInsertTrait;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Defines a basic FileToSqlite feature for Iterate Scenarios.
 */
trait ToSqliteTrait {

  use BaseInsertTrait, ConsoleProgressBarTrait {
    BaseInsertTrait::preRun as protected insertPreRun;
    BaseInsertTrait::postRun as protected insertPostRun;
    ConsoleProgressBarTrait::preRun as protected progressPreRun;
    ConsoleProgressBarTrait::postRun as protected progressPostRun;
  }

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
   * Indicates whether the $destination file exists.
   *
   * @var bool
   */
  protected $destinationExists;

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
   * Regular expression pattern.
   *
   * @var string
   */
  protected $pattern;

  /**
   * The default field type.
   *
   * @var string
   */
  protected $defaultFieldType = 'text';

  /**
   * Sets the destination path.
   *
   * @param string $destination
   *   Path to the SQLite database file. If not exists, it will be created.
   *
   * @return $this|\Shiyan\Iterate\Scenario\ScenarioInterface
   *   The called object.
   */
  public function setDestination(string $destination): ScenarioInterface {
    $this->destination = $destination;

    return $this;
  }

  /**
   * Sets options array.
   *
   * @param array $options
   *   Associative array. See \Shiyan\FileToSqlite\FileToSqlite::run() for
   *   possible elements.
   *
   * @return $this|\Shiyan\Iterate\Scenario\ScenarioInterface
   *   The called object.
   *
   * @see \Shiyan\FileToSqlite\FileToSqlite::run()
   */
  public function setOptions(array $options): ScenarioInterface {
    $this->options = $options;

    return $this;
  }

  /**
   * Sets regular expression pattern to use with regex based scenarios.
   *
   * @param string $pattern
   *   The regex pattern with named subpatterns.
   *
   * @return $this|\Shiyan\Iterate\Scenario\ScenarioInterface
   *   The called object.
   */
  public function setPattern(string $pattern): ScenarioInterface {
    $this->pattern = $pattern;

    return $this;
  }

  /**
   * Gets the pattern to try to guess field names from it.
   *
   * This is for regex based scenarios.
   *
   * @return string
   *   Regular expression pattern.
   */
  protected function getPattern(): string {
    return $this->pattern;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTable(): string {
    $this->table = $this->getOption('table', $this->table);

    if (!isset($this->table)) {
      $iterator = $this->getIterator();

      if ($iterator instanceof \SplFileObject) {
        $this->table = pathinfo($iterator->getFileInfo(), PATHINFO_FILENAME);
      }
    }

    if (!isset($this->table)) {
      throw new \LogicException('Table name is not provided.');
    }

    return $this->table;
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
   * Returns the Filesystem instance.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   *   The Filesystem instance.
   */
  protected function getFs(): Filesystem {
    if (!isset($this->filesystem)) {
      $this->filesystem = new Filesystem();
    }

    return $this->filesystem;
  }

  /**
   * Validates destination path.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
   *   If the destination exists and it's not a writable file or if it cannot be
   *   created.
   */
  protected function validateDestination(): void {
    $destination = new \SplFileInfo($this->destination);
    $real_path = $destination->getRealPath();
    $this->destinationExists = $real_path !== FALSE;

    if ($this->destinationExists) {
      $this->destination = $real_path;
      $destination = new \SplFileInfo($this->destination);

      if (!$destination->isFile()) {
        throw new InvalidArgumentException($destination . ' is not a file.');
      }
      if (!$destination->isWritable()) {
        throw new InvalidArgumentException($destination . ' is not writable.');
      }
    }
    else {
      $parent = $destination->getPathInfo();

      if (!$parent->isDir()) {
        throw new InvalidArgumentException($parent . ' is not a directory.');
      }
      if (!$parent->isWritable()) {
        throw new InvalidArgumentException($parent . ' is not writable.');
      }
    }
  }

  /**
   * Validates fields.
   *
   * @throws \Symfony\Component\Console\Exception\InvalidOptionException
   *   If options contain non-existing or repeating (type) fields.
   */
  protected function validateFields(): void {
    $fields = array_fill_keys($this->getFields(), FALSE);

    foreach ($this->getOption('primary', []) as $field) {
      if (!isset($fields[$field])) {
        throw new InvalidOptionException('The "--primary" option contains non-existent field "' . $field . '".');
      }
    }

    foreach (['integer', 'blob', 'real', 'numeric', 'text'] as $type) {
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
   * Checks if the table exists in the DB.
   *
   * @return bool
   *   Whether the table exists or not.
   */
  protected function tableExists(): bool {
    $sql = 'SELECT 1 FROM sqlite_master WHERE type = :type AND name = :name';
    $args = [':type' => 'table', ':name' => $this->getTable()];

    $statement = $this->getConnection()->prepare($sql);
    $this->getConnection()->executeStatement($statement, $args);

    return (bool) $statement->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function preRun(): void {
    $this->validateDestination();
    $this->validateFields();

    $this->dbFile = $this->getFs()->tempnam(sys_get_temp_dir(), 'file-to-sqlite-');
    if ($this->destinationExists) {
      $this->getFs()->copy($this->destination, $this->dbFile, TRUE);
    }

    $pdo = new \PDO('sqlite:' . $this->dbFile);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // The syncing feature is unnecessary, because we're writing to a temporary
    // file. The journaling mode is unnecessary, because we don't use rollbacks.
    // @link https://www.sqlite.org/pragma.html#pragma_synchronous
    // @link https://www.sqlite.org/pragma.html#pragma_journal_mode
    $pdo->exec('PRAGMA synchronous = OFF');
    $pdo->exec('PRAGMA journal_mode = OFF');

    $this->connection = new Connection($pdo);

    if (!$this->destinationExists || !$this->tableExists()) {
      $fields = array_fill_keys($this->getFields(), strtoupper($this->defaultFieldType));

      foreach (['integer', 'blob', 'real', 'numeric', 'text'] as $type) {
        foreach ($this->getOption($type, []) as $field) {
          $fields[$field] = strtoupper($type);
        }
      }

      array_walk($fields, function (&$type, $field) {
        $type = $field . ' ' . $type;
      });

      $primaries = implode(', ', $this->getOption('primary', []));
      if ($primaries !== '') {
        $primaries = ', PRIMARY KEY (' . $primaries . ')';
      }

      $pdo->exec('CREATE TABLE ' . $this->getTable() . ' (' . implode(', ', $fields) . $primaries . ')');
    }
    elseif (!$this->getOption('append')) {
      $message = 'Table exists in the destination database.';

      // Check whether the table is "appendable".
      if ($this->getOption('append') === FALSE) {
        $message .= ' To insert into existing table, use the "--append" option.';
      }

      throw new RuntimeException($message);
    }

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
    $this->getFs()->rename($this->dbFile, $this->destination, $this->destinationExists);
  }

}
