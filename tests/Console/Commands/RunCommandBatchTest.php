<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Tests\ConfigurationDB;

class RunCommandBatchTest extends AbstractRunCommandMode
{
    protected $mode = 'batch';
    protected $exceptedSQLOutput = [
        'pdo_mysql' => '|.*LOAD DATA LOCAL INFILE.*|',
        'pdo_pgsql' => "|.*COPY .+ FROM '.+' ... Managed by pgsqlCopyFromFile|",
        'pdo_sqlsrv' => '|.*BULK INSERT.+|',
    ];

    public function testExecuteWithSQL()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')
          && substr(gethostbyname(getenv('DB_HOST')), 0, 3) !== '127') {
            $this->markTestSkipped(
                "Can't run a batch query if the file is remote with SQL Server"
            );
        }

        parent::testExecuteWithSQL();
    }

    public function testExecuteWithLimitInsert()
    {
        // we can't define fields list with sqlserver
        // and send an empty value to postgres
        // so 2 different methods
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->executeWithLimitInsert('config-insert-batch.right.yaml');
            return;
        }

        $this->executeWithLimitInsert('config-insert.right.yaml');
    }
}
