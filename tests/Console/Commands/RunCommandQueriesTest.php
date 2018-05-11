<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Tests\ConfigurationDB;

class RunCommandQueriesTest extends AbstractRunCommandMode
{
    protected $mode = 'queries';
    protected $exceptedSQLOutput = [
        'pdo_mysql' => '|.*UPDATE guestbook SET.*|',
        'pdo_pgsql' => '|.*UPDATE guestbook SET.*|',
        'pdo_sqlsrv' => '|.*UPDATE guestbook SET.*|',
    ];
}
