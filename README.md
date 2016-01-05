inetprocess/neuralyzer
=====

## Summary
This project is a library and a command line tool that anonymizes (for now, but as it uses PDO,
it's easy to implement other DBs) a MySQL database. It uses [Faker](https://github.com/fzaninotto/Faker) to generate
the data and replace the rows in tables.

## CLI
The easiest way to use that tool is to start with the command line tool. After cloning the project, run:
```bash
bin/anon
```

### Generate the configuration
The main command is config:generate that expects some parameters:
```bash
Options:
      --host=HOST                  Host [default: "127.0.0.1"]
  -d, --db=DB                      Database Name
  -u, --user=USER                  User Name [default: "manu"]
  -p, --password=PASSWORD          Password (or it'll be prompted)
  -f, --file=FILE                  File [default: "anon.yml"]
      --protect                    Protect IDs and other fields
      --ignore-table=IGNORE-TABLE  Table to ignore. Can be repeated (multiple values allowed)
      --ignore-field=IGNORE-FIELD  Field to ignore. Regexp in the form "table.field". Can be repeated (multiple values allowed)

```

#### Example
```bash
bin/anon config:generate --db test -u root --ignore-table config --ignore-field ".*\.id.*"
```

That produces a file which looks like:
```yaml
guesser_version: 1.0.0b
entities:
    authors:
        cols:
            first_name: { method: firstName }
            last_name: { method: lastName }
    books:
        cols:
            name: { method: sentence, params: [8] }
            date_modified: { method: date, params: ['Y-m-d H:i:s', now] }
```

### Run the anonymizer
To run the anonymizer, the command is simply "run" and expects:
```bash
Options:
      --host=HOST          Host [default: "127.0.0.1"]
  -d, --db=DB              Database Name
  -u, --user=USER          User Name [default: "manu"]
  -p, --password=PASSWORD  Password (or it'll be prompted)
  -c, --config=CONFIG      Configuration File [default: "anon.yml"]
      --pretend            Don't run the queries
      --sql                Display the SQL
```
#### Example
```bash
bin/anon run --db test -u root --sql
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


## Library
The library is made to be integrated with any Tool such as a CLI tool. It contains:
* A Configuration Reader and a Configuration Writer
* A Guesser
* A DB Anonymizer


### Configuration Writer
The writer is helpful to generate a yaml file that contains all tables and fields from a DB. A basic usage could be the following:
```php
// You need to instanciate a \PDO Object first
$writer = new Inet\Anon\Configuration\Writer;
$data = $writer->generateConfFromDB($pdo, new Inet\Anon\Guesser);
$writer->save($data, 'anon.yaml');
```

If you need, you can protect some cols (with regexp) or tables:
```php
// You need to instanciate a \PDO Object first
$writer = new Inet\Anon\Configuration\Writer;
$writer->protectCols(true); // will protect primary keys
// define cols to protect (must be prefixed with the table name)
$writer->setProtectedCols(array(
    '.*\.id',
    '.*\..*_id',
    '.*\.date_modified',
    '.*\.date_entered',
    '.*\.date_created',
    '.*\.deleted',
));
// Define tables to ignore, also with regexp
$writer->setIgnoredTables(array(
    'acl_.*',
    'config',
    'email_cache',
));
// Write the configuration
$data = $writer->generateConfFromDB($pdo, new Inet\Anon\Guesser);
$writer->save($data, 'anon.yaml');
```

### Configuration Reader
The configuration Reader is the exact opposite of the Writer. Its main job is to validate that the configuration
of the yaml file is correct. Example:
```yaml
// will throw an exception
$reader = new Inet\Anon\Configuration\Reader('sugarcli_anon.yaml');
$tables = $reader->getEntities();
```

### Guesser
The guesser is the central piece of the tool. It guess, according to the field name or field type what type of
faker method to apply.

It can be extended very easily as it has to be injected to the Writer.

### DB Anonymizer
The only anonymizer currently available is the DB one. It expects a PDO and a Configuration Reader objects:
```php
$anon = new Inet\Anon\Anonymizer\DB($sugarPDO);
$anon->setConfiguration($reader);

```

Once initialized, the method that anonymize the table is the following:
```php
public function processEntity($table, $callback = null, $pretend = true, $returnResult = false);
```

Parameters:
* Table name (required)
* Callback (callable / optional) to use a progressbar for example
* Pretend : SQL Queries won't be executed
* returnResult: SQL Queries will be returned