[![Build Status](https://travis-ci.com/edyan/neuralyzer.svg?branch=master)](https://travis-ci.com/edyan/neuralyzer)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/edyan/neuralyzer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/edyan/neuralyzer/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/edyan/neuralyzer/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/edyan/neuralyzer/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/edyan/neuralyzer/badges/build.png?b=master)](https://scrutinizer-ci.com/g/edyan/neuralyzer/build-status/master)



edyan/neuralyzer
=====

## Summary
This project is a library and a command line tool that **anonymizes** a database by updating data
or generating fake data (update vs insert). It uses [Faker](https://github.com/fzaninotto/Faker)
to generate data from rules defined in a configuration file.

As it can do row per row or use batch mechanisms, you can load tables with
dozens of millions of fake records.

It uses [Doctrine DBAL](https://github.com/doctrine/dbal) to abstract interactions with
databases. It's then supposed to be able to work with any database type.
Currently it works (tested extensively) with MySQL, PostgreSQL and SQLServer.

~~Neuralyzer has an option to clean tables by injecting a `DELETE FROM` with a `WHERE` critera
before launching the anonymization (see the config parameters `delete` and `delete_where`).~~ 

Neuralyzer had an option to clean tables but it's now managed by pre and post actions : 
```yaml
entities:
    books:
        cols:
            title: { method: sentence, params: [8], unique: true }
        action: update
        pre_actions: 
            - db.query("DELETE FROM books")
post_actions:
    - db.query("DELETE FROM books WHERE title LIKE '%war%'")

```


## Installation as a library
```bash
composer require edyan/neuralyzer
```


## Installation as an executable
You can even download the executable directly (example with v3.1):
```bash
$ wget https://github.com/edyan/neuralyzer/raw/v3.1.0/neuralyzer.phar
$ sudo mv neuralyzer.phar /usr/local/bin/neuralyzer
$ sudo chmod +x /usr/local/bin/neuralyzer
$ neuralyzer
```


## Usage
The easiest way to use that tool is to start with the command line tool.
After cloning the project and running a `composer install`, try:
```bash
$ bin/neuralyzer
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
bin/neuralyzer config:generate --db test_db -u root -p root --ignore-table config --ignore-field ".*\.id.*"
```

That produces a file which looks like:
```yaml
entities:
    authors:
        cols:
            first_name: { method: firstName, unique: false }
            last_name: { method: lastName, unique: false }
        action: update # Will update existing data, "insert" would create new data
        pre_actions: {  }
        post_actions: {  }

    books:
        cols:
            name: { method: sentence, params: [8] }
            date_modified: { method: date, params: ['Y-m-d H:i:s', now] }
        action: update
        pre_actions: {  }
        post_actions: {  }
        
guesser: Edyan\Neuralyzer\Guesser
guesser_version: '3.0'
language: en_US
```

You have to modify the file to change its configuration. For example, if you need to remove data
while anonymizing and change the language
(see [Faker's doc](https://github.com/fzaninotto/Faker/tree/master/src/Faker/Provider) for available languages), do :

```yaml
# be careful that some languages have only a few methods.
# Example : https://github.com/fzaninotto/Faker/tree/master/src/Faker/Provider/fr_FR
language: fr_FR
```

**INFO**: You can also use delete in standalone, without anonymizing anything. That will delete everything in books:
```yaml
entities:
    authors:
        cols:
            first_name: { method: firstName, unique: false }
            last_name: { method: lastName, unique: false }
        action: update
    books:
        pre_actions: 
            - db.query("DELETE FROM books")
```

If you wanted to delete everything then insert 1000 new books:
```yaml
guesser_version: '3.0'
entities:
    authors:
        cols:
            first_name: { method: firstName, unique: false }
            last_name: { method: lastName, unique: false }
        action: update
    books:
        cols:
            name: { method: sentence, params: [8] }
        action: insert
        pre_actions: 
            - db.query("DELETE FROM books")
        limit: 1000
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

    -m, --mode=MODE          Set the mode : batch or queries [default: "batch"]
```
#### Example
```bash
bin/neuralyzer run --db test_db -u root -p root
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

require_once 'vendor/autoload.php';

// Create a container
$container = Edyan\Neuralyzer\ContainerFactory::createContainer();
// Configure DB Utils, required
$dbUtils = $container->get('Edyan\Neuralyzer\Utils\DBUtils');
// See Doctrine DBAL configuration :
// https://www.doctrine-project.org/projects/doctrine-dbal/en/2.7/reference/configuration.html
$dbUtils->configure([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'dbname' => 'test_db',
    'user' => 'root',
    'password' => 'root',
]);

$writer = new \Edyan\Neuralyzer\Configuration\Writer;
$data = $writer->generateConfFromDB($dbUtils, new \Edyan\Neuralyzer\Guesser);
$writer->save($data, 'neuralyzer.yml');
```


If you need, you can protect some cols (with regexp) or tables:
```php
<?php
// ...
$writer = new \Edyan\Neuralyzer\Configuration\Writer;
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
$data = $writer->generateConfFromDB($dbUtils, new \Edyan\Neuralyzer\Guesser);
$writer->save($data, 'neuralyzer.yml');
```


### Configuration Reader
The configuration Reader is the exact opposite of the Writer. Its main job is to validate that the configuration
of the yaml file is correct then to provide methods to access its parameters. Example:
```php
<?php
require_once 'vendor/autoload.php';

// will throw an exception if it's not valid
$reader = new Edyan\Neuralyzer\Configuration\Reader('neuralyzer.yml');
$tables = $reader->getEntities();
```


### DB Anonymizer
The only anonymizer currently available is the DB one. It expects a PDO and a Configuration Reader objects:
```php
<?php

require_once 'vendor/autoload.php';

// Create a container
$container = Edyan\Neuralyzer\ContainerFactory::createContainer();
$expression = $container->get('Edyan\Neuralyzer\Utils\Expression');
// Configure DB Utils, required
$dbUtils = $container->get('Edyan\Neuralyzer\Utils\DBUtils');
// See Doctrine DBAL configuration :
// https://www.doctrine-project.org/projects/doctrine-dbal/en/2.7/reference/configuration.html
$dbUtils->configure([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'dbname' => 'test_db',
    'user' => 'root',
    'password' => 'root',
]);

$db = new \Edyan\Neuralyzer\Anonymizer\DB($expression, $dbUtils);
$db->setConfiguration(
    new \Edyan\Neuralyzer\Configuration\Reader('neuralyzer.yml')
);

```


Once initialized, the method that anonymize the table is the following:
```php
<?php
public function processEntity(string $entity, callable $callback = null): array;
```

Parameters:
* `Entity`: such as table name (required)
* `Callback` (callable / optional) to use a progress bar for example

A few options can be set by calling :
```php
<?php
// Limit of fake generated records for updates and creates.
// Default : 0 = everything to update / nothing to insert
public function setLimit(int $limit);
// Don't do anything, default true
public function setPretend(bool $pretend);
// Return or not a result, default false
public function setReturnRes(bool $returnRes);
```


Full Example:
```php
<?php

require_once 'vendor/autoload.php';

// Create a container
$container = Edyan\Neuralyzer\ContainerFactory::createContainer();
$expression = $container->get('Edyan\Neuralyzer\Utils\Expression');
// Configure DB Utils, required
$dbUtils = $container->get('Edyan\Neuralyzer\Utils\DBUtils');
// See Doctrine DBAL configuration :
// https://www.doctrine-project.org/projects/doctrine-dbal/en/2.7/reference/configuration.html
$dbUtils->configure([
    'driver' => 'pdo_mysql',
    'host' => 'mysql',
    'dbname' => 'test_db',
    'user' => 'root',
    'password' => 'root',
]);

$reader = new \Edyan\Neuralyzer\Configuration\Reader('neuralyzer.yml');

$db = new \Edyan\Neuralyzer\Anonymizer\DB($expression, $dbUtils);
$db->setConfiguration($reader);
$db->setPretend(false);
// Get tables
$tables = $reader->getEntities();
foreach ($tables as $table) {
    $total = $dbUtils->countResults($table);

    if ($total === 0) {
        fwrite(STDOUT, "$table is empty" . PHP_EOL);
        continue;
    }
    fwrite(STDOUT, "$table anonymized" . PHP_EOL);

    $db->processEntity($table);
}

```


## Pre and Post Actions
You can set an array of `pre_actions` and `post_actions` that will be 
executed *before* and *after* neuralyzer starts to anonymize an entity.

These actions are actually symfony expressions (see [Symfony Expression Language](https://)) 
that rely on *Services*. These Services are loaded from the `Service/` directory.

For now there is only one service : `Database` that contains a method `query` usable like that : 
`db.query("DELETE FROM table")`.


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
            delete:               ~ # Deprecated (delete and delete_where have been deprecated. Use now pre and post_actions)

            # Condition applied in a WHERE if delete is set to "true"
            delete_where:         ~ # Deprecated (delete and delete_where have been deprecated. Use now pre and post_actions), Example: '1 = 1'
            cols:

                # Examples:
                first_name:          
                    method:              firstName
                last_name:           
                    method:              lastName

                # Prototype
                -

                    # Faker method to use, see doc : https://github.com/fzaninotto/Faker
                    method:               ~ # Required

                    # Set this option to true to generate unique values for that field (see faker->unique() generator)
                    unique:               false

                    # Faker's parameters, see Faker's doc
                    params:               []

            # Limit the number of written records (update or insert). 100 by default for insert
            limit:                0

            # The list of expressions language actions to executed before neuralyzing. Be careful that "pretend" has no effect here.
            pre_actions:          []

            # The list of expressions language actions to executed after neuralyzing. Be careful that "pretend" has no effect here.
            post_actions:         []

```

## Custom application logic

When using custom doctrine types doctrine will produce an error that the type is not know.
This can be solved by providing a bootstrap file to register the custom doctrine type.

bootstrap.php
```php
<?php

require_once '../vendor/autoload.php';

\Doctrine\DBAL\Types\Type::addType('custom_type', 'Namespace\Of\The\Custom\Type');
```

Then provide the bootstrap file to the run command:

```bash
bin/neuralyzer run --db test_db -u root -p root -b bootstrap.php
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
$ vendor/bin/robo test --php 7.2 --wait 10 --db mysql --db-version 5
$ vendor/bin/robo test --php 7.3 --wait 10 --db mysql --db-version 5
```
#### With PostgreSQL 10 (9 is also working) 
```bash
$ vendor/bin/robo test --php 7.1 --wait 10 --db pgsql --db-version 10
$ vendor/bin/robo test --php 7.2 --wait 10 --db pgsql --db-version 10
$ vendor/bin/robo test --php 7.3 --wait 10 --db pgsql --db-version 10
```
#### With SQL Server
**Warning** : 2 tests *fail*, because of strange behaviors of SQL Server ... or Doctrine / Dbal. PHPUnit can't compare 2 Datasets because the fields are not in the same order.
```bash
$ vendor/bin/robo test --php 7.1 --wait 15 --db sqlsrv
$ vendor/bin/robo test --php 7.2 --wait 15 --db sqlsrv
$ vendor/bin/robo test --php 7.3 --wait 15 --db sqlsrv
```

### Build a release (with a phar and a git tag)
```bash
$ php -d phar.readonly=0 vendor/bin/robo release
```

### Build the phar only
```bash
$ php -d phar.readonly=0 vendor/bin/robo phar
```
