# File to SQLite

A command-line utility for copying data from a file to an SQLite database.

## Requirements

* PHP &ge; 7.1
* [Composer](https://getcomposer.org)

## Installation

```
composer global require --optimize-autoloader shiyan/file-to-sqlite
```

Make sure that the `COMPOSER_HOME/vendor/bin` dir is in your `PATH` env var.
More info in the composer help: `composer global -h`

If you have the [CGR](https://github.com/consolidation/cgr) installed, then run
the following command instead of the one above:

```
cgr -o shiyan/file-to-sqlite
```

## Usage

```
file-to-sqlite [options] [--] <source> <destination> <pattern>
```

##### Arguments:
```
source             Path to the source file.
destination        Path to the SQLite database file. If not exists, it will be
                   created.
pattern            Regular expression pattern with named subpatterns.
```

##### Options:
```
--table=TABLE      Table name. By default, the source file name is used.
--integer=INTEGER  List of integer fields. (multiple values allowed)
--blob=BLOB        List of blob fields. (multiple values allowed)
--real=REAL        List of real fields. (multiple values allowed)
--numeric=NUMERIC  List of numeric fields. (multiple values allowed)
--primary=PRIMARY  Primary key.
--append           If the table exists, this option allows to insert into it
                   anyway.
```
