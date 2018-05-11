<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Tests\ConfigurationDB;

class RunCommandBatchTest extends AbstractRunCommandMode
{
    protected $mode = 'batch';
    protected $exceptedSQLOutput = [
        'pdo_mysql' => '|.*LOAD DATA LOCAL INFILE.*|',
        'pdo_pgsql' => "|.*COPY .+ (.+) FROM '.+' ... Managed by pgsqlCopyFromFile|",
    ];
}
