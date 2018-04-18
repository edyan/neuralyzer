[![Build Status](https://travis-ci.org/edyan/neuralyzer.svg?branch=master)](https://travis-ci.org/edyan/neuralyzer)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/edyan/neuralyzer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/edyan/neuralyzer/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/edyan/neuralyzer/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/edyan/neuralyzer/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/edyan/neuralyzer/badges/build.png?b=master)](https://scrutinizer-ci.com/g/edyan/neuralyzer/build-status/master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/8ccf2e5e-3797-4d7a-8aa1-23a24e6bba5c/mini.png)](https://insight.sensiolabs.com/projects/c04e15ab-fff2-4aad-9c8e-7d3d4eba7a04)


edyan/neuralyzer
=====

## Summary
This project is a library and a command line tool that **anonymizes** a database (action = update in config). It uses [Faker](https://github.com/fzaninotto/Faker) to generate data and replace the rows in tables.

It is also able to **generate** fake data to insert it into tables (action = insert in config). For example, you can load
a table with millions of fake records for load tests.

It uses [Doctrine DBAL](https://github.com/doctrine/dbal) to abstract interactions with
databases. It's then supposed to be able to work with any database type.
Currently it works with MySQL, PostgreSQL and SQLServer.

Neuralyzer has an option to clean tables by injecting a `DELETE FROM` with a `WHERE` critera
before launching the anonymization (see the config parameters `delete` and `delete_from`).


## Installation as a library
```bash
composer require edyan/neuralyzer
```


## Installation as an executable
You can even download the executable directly :
```bash
$ wget https://raw.githubusercontent.com/edyan/neuralyzer/master/neuralyzer.phar
$ sudo mv neuralyzer.phar /usr/local/bin/neuralyzer
$ sudo chmod +x /usr/local/bin/neuralyzer
$ neuralyzer
```


## Usage
The easiest way to use that tool is to start with the command line tool. After cloning the project, run:
```bash
bin/neuralyzer
```


### Generate the configuration automatically
Neuralyzer is able to read a database and generate the configuration for you.
The command `config:generate` accepts the following options:
```
Options:
  -D, --driver=DRIVER              Driver (check Doctrine documentation to have the list) [default: "pdo_mysql"]
  -H, --host=HOST                  Host [default: "127.0.0.1"]
  -d, --db=DB                      Database Name
  -u, --user=USER                  User Name [default: "www-data"]
  -p, --password=PASSWORD          Password (or it'll be prompted)
  -f, --file=FILE                  File [default: "neuralyzer.yml"]
      --protect                    Protect IDs and other fields
      --ignore-table=IGNORE-TABLE  Table to ignore. Can be repeated (multiple values allowed)
      --ignore-field=IGNORE-FIELD  Field to ignore. Regexp in the form "table.field". Can be repeated (multiple values allowed)
```

#### Example
```bash
bin/neuralyzer config:generate --db test -u root --ignore-table config --ignore-field ".*\.id.*"
```

That produces a file which looks like:
```yaml
guesser_version: '3.0'
language: en_US
entities:
    authors:
        cols:
            first_name: { method: firstName }
            last_name: { method: lastName }
        action: update # Will update existing data, "insert" would create new data
    books:
        cols:
            name: { method: sentence, params: [8] }
            date_modified: { method: date, params: ['Y-m-d H:i:s', now] }
        action: update
```

You have to modify the file to change its configuration.
For example, if you need to remove data while anonymizing and change the
language (see [Faker's doc](https://github.com/fzaninotto/Faker/tree/master/src/Faker/Provider)
for available languages), do :

```yaml
guesser_version: '3.0'
# be careful that some languages have only a few methods.
# Example : https://github.com/fzaninotto/Faker/tree/master/src/Faker/Provider/fr_FR
language: en_US
entities:
    authors:
        cols:
            first_name: { method: firstName }
            last_name: { method: lastName }
        action: update
    books:
        action: update
        delete: true
        delete_where: "name LIKE 'Bad Book%'"
        cols:
            name: { method: sentence, params: [8] }
```

**INFO**: You can also use delete in standalone, without anonymizing anything. That will delete everything in books:
```yaml
guesser_version: '3.0'
entities:
    authors:
        cols:
            first_name: { method: firstName }
            last_name: { method: lastName }
        action: update
    books:
        delete: true
```

If you wanted to delete everything + insert new lines :
```yaml
guesser_version: '3.0'
entities:
    authors:
        cols:
            first_name: { method: firstName }
            last_name: { method: lastName }
        action: update
    books:
        delete: true
        cols:
            name: { method: sentence, params: [8] }
        action: insert
```


### Run the anonymizer
To run the anonymizer, the command is simply "run" and expects:
```
Options:
  -D, --driver=DRIVER      Driver (check Doctrine documentation to have the list) [default: "pdo_mysql"]
  -H, --host=HOST          Host [default: "127.0.0.1"]
  -d, --db=DB              Database Name
  -u, --user=USER          User Name [default: "www-data"]
  -p, --password=PASSWORD  Password (or prompted)
  -c, --config=CONFIG      Configuration File [default: "neuralyzer.yml"]
  -t, --table=TABLE        Do a single table
      --pretend            Don't run the queries
  -s, --sql                Display the SQL
  -l, --limit=LIMIT        Limit the number of written records (update or insert). 100 by default for insert
```
#### Example
```bash
bin/neuralyzer run --db test -u root --sql
```

That produces that kind of output:
```bash
Anonymizing authors
 2/2 [============================] 100%

Queries:
UPDATE authors SET first_name = 'Don', last_name = 'Wisoky' WHERE id = '1'
UPDATE authors SET first_name = 'Sasha', last_name = 'Denesik' WHERE id = '2'

....
```

**WARNING**: On a huge table, `--sql` will produce a HUGE output. Use it for debugging purpose.


## Library
The library is made to be integrated with any Tool such as a CLI tool. It contains:
* A Configuration Reader and a Configuration Writer
* A Guesser
* A DB Anonymizer


### Guesser
The guesser is the central piece of the config generator.
It guesses, according to the field name or field type what type of faker method to apply.

It can be extended very easily as it has to be injected to the Writer.


### Configuration Writer
The writer is helpful to generate a yaml file that contains all tables and fields from a DB. A basic usage could be the following:

```php
<?php
// You need to instanciate a \PDO Object first
$writer = new Edyan\Neuralyzer\Configuration\Writer;
$data = $writer->generateConfFromDB($pdo, new Edyan\Neuralyzer\Guesser);
$writer->save($data, 'neuralyzer.yml');
```

If you need, you can protect some cols (with regexp) or tables:
```php
<?php
// You need to pass some parameters required by Doctrine DBAL
// See : http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
$db = new Edyan\Neuralyzer\Anonymizer\DB([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'dbname' => 'test',
    'user' => 'root',
    'password' => 'root'
]);

// Then call the writer
$writer = new Edyan\Neuralyzer\Configuration\Writer;
$writer->protectCols(true); // will protect primary keys
// define cols to protect (must be prefixed with the table name)
$writer->setProtectedCols([
    '.*\.id',
    '.*\..*_id',
    '.*\.date_modified',
    '.*\.date_entered',
    '.*\.date_created',
    '.*\.deleted',
]);
// Define tables to ignore, also with regexp
$writer->setIgnoredTables([
    'acl_.*',
    'config',
    'email_cache',
]);
// Write the configuration
$data = $writer->generateConfFromDB($db, new Edyan\Neuralyzer\Guesser);
$writer->save($data, 'neuralyzer.yml');
```

### Configuration Reader
The configuration Reader is the exact opposite of the Writer. Its main job is to validate that the configuration
of the yaml file is correct then to provide methods to access its parameters. Example:
```php
<?php
// will throw an exception if it's not valid
$reader = new Edyan\Neuralyzer\Configuration\Reader('neuralyzer.yml');
$tables = $reader->getEntities();
```

### DB Anonymizer
The only anonymizer currently available is the DB one. It expects a PDO and a Configuration Reader objects:
```php
<?php
// You need to pass some parameters required by Doctrine DBAL
// See : http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
$db = new Edyan\Neuralyzer\Anonymizer\DB([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'dbname' => 'test',
    'user' => 'root',
    'password' => 'root'
]);

$db->setConfiguration(
    new Edyan\Neuralyzer\Configuration\Reader('neuralyzer.yml')
);

```

Once initialized, the method that anonymize the table is the following:
```php
<?php
public function processEntity($entity, $callback = null, $pretend = true, $returnRes = false, $limit = 0);
```

Parameters:
* `Entity`: such as table name (required)
* `Callback` (callable / optional) to use a progressbar for example
* `Pretend`: SQL Queries won't be executed
* `ReturnRes`: SQL Queries will be returned
* `Limit`: Limit the number of queries (insert or update)


Full Example:
```php
<?php

require_once 'vendor/autoload.php';

$reader = new Edyan\Neuralyzer\Configuration\Reader('neuralyzer.yml');
$db = new Edyan\Neuralyzer\Anonymizer\DB([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'dbname' => 'test',
    'user' => 'root',
    'password' => 'root'
]);

$db->setConfiguration($reader);

// Get tables
$tables = $reader->getEntities();
foreach ($tables as $table) {
    $res = $db->getConn()->createQueryBuilder()
              ->select('COUNT(1) AS total')->from($table)->execute()->fetchAll()[0];

    if ((int)$res['total'] === 0) {
        $output->writeln("<info>$table is empty</info>");
        continue;
    }

    $queries = $db->processEntity($table);
}
```

## Configuration Reference
`bin/neuralyzer config:example` provides a default configuration with all parameters explained :
```yaml
config:
    # Set the guesser class
    guesser:              Edyan\Neuralyzer\Guesser
    # Set the version of the guesser the conf has been written with
    guesser_version:      '3.0'

    # Faker's language, make sure all your methods have a translation
    language:             en_US

    # List all entities, theirs cols and actions
    entities:             # Required, Example: people
        # Prototype
        -
            # Either "update" or "insert" data
            action:               update
            # Should we delete data with what is defined in "delete_where" ?
            delete:               false
            # Condition applied in a WHERE if delete is set to "true"
            delete_where:         ~ # Example: '1 = 1'
            cols:
                # Examples:
                first_name:
                    method:              firstName
                last_name:
                    method:              lastName
                # Prototype
                -
                    method:               ~ # Required
                    params:               []
```


## Development
Neuralyzer uses [Robo](https://robo.li) to run its tests (via Docker) and build its phar.

Clone the project, run `composer install` then...

### Run the tests
* Change the `--wait` option if you have a lot of errors because DB is not ready.
* Change the `--php` option for `7.1` or `7.2`

#### With MySQL
```bash
$ vendor/bin/robo test --php 7.1 --wait 10 --db mysql --db-version 5
```
#### With PostgreSQL
```bash
$ vendor/bin/robo test --php 7.1 --wait 10 --db pgsql --db-version 10
```
#### With SQL Server
**Warning** : 2 tests *fail*, because of strange behaviors of SQL Server ... or Doctrine / Dbal. PHPUnit can't compare 2 Datasets because the fields are not in the same order.
```bash
$ vendor/bin/robo test --php 7.1 --wait 15 --db sqlsrv
```

### Build a release (with a phar and a git tag)
```bash
$ php -d phar.readonly=0 vendor/bin/robo release
```

### Build the phar only
```bash
$ php -d phar.readonly=0 vendor/bin/robo phar
```
