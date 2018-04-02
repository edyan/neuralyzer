<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

class RoboFile extends \Robo\Tasks
{
    /**
     * A test command just to make sure robo works on that computer
     */
    public function dockertest()
    {
        $this->stopOnFail(true);

        $this->taskDockerStop('robo_test')->run();

        $this->taskDockerRun('edyan/php:7.1')
             ->name('robo_test')
             ->detached()
             ->option('--rm')
             ->run();

        $this->taskDockerExec('robo_test')
             ->interactive()
             ->exec($this->taskExec('php -v'))
             ->run();

        $this->taskDockerStop('robo_test')->run();
    }


    /**
     * Run All Unit Test
     * @param  array  $opts
     */
    public function test(
        $opts = ['php' => '7.1', 'db' => 'mysql', 'keep-cts' => false, 'wait' => 5]
    ) {
        $this->stopOnFail(true);

        $this->setupDocker($opts['php'], $opts['db'], $opts['wait']);

        // Run the tests
        $this->taskDockerExec('robo_php')
            ->interactive()
            ->option('--user', 'www-data')
            ->exec($this->taskExec('/bin/bash -c "cd /var/www/html ; vendor/bin/phpunit"'))
            ->run();

        if ($opts['keep-cts'] === false) {
            $this->destroyDocker();
        }
    }


    /**
     * Build an executable phar
     */
    public function phar()
    {
        // Create a collection builder to hold the temporary
        // directory until the pack phar task runs.
        $collection = $this->collectionBuilder();
        $workDir = $collection->tmpDir();
        $buildDir = "$workDir/neuralyzer";

        $prepTasks = $this->collectionBuilder();
        $preparationResult = $prepTasks
            ->taskFilesystemStack()
                ->mkdir($workDir)
                ->taskCopyDir([__DIR__ . '/src' => $buildDir . '/src'])
            ->taskFilesystemStack()
                ->copy(__DIR__ . '/bin/neuralyzer', $buildDir . '/bin/neuralyzer')
                ->copy(__DIR__ . '/composer.json', $buildDir . '/composer.json')
                ->copy(__DIR__ . '/composer.lock', $buildDir . '/composer.lock')
                ->copy(__DIR__ . '/LICENSE', $buildDir . '/LICENSE')
                ->copy(__DIR__ . '/README.md', $buildDir . '/README.md')

            ->taskComposerInstall()
                ->dir($buildDir)
                ->noDev()
                ->noScripts()
                ->printOutput(true)
                ->optimizeAutoloader()
                ->run();

        // Exit if the preparation step failed
        if (!$preparationResult->wasSuccessful()) {
            return $preparationResult;
        }

        // Decide which files we're going to pack
        $files = \Symfony\Component\Finder\Finder::create()->ignoreVCS(true)
            ->files()
            ->name('*.php')
            ->name('*.exe') // for 1symfony/console/Resources/bin/hiddeninput.exe
            ->path('src')
            ->path('vendor')
            ->notPath('docs')
            ->notPath('/vendor\/.*\/[Tt]est/')
            ->in(is_dir($buildDir) ? $buildDir : __DIR__);

        // Build the phar
        return $collection
            ->taskPackPhar('neuralyzer.phar')
                ->compress()
                ->addFile('bin/neuralyzer', 'bin/neuralyzer')
                ->addFiles($files)
                ->executable('bin/neuralyzer')
            ->taskFilesystemStack()
                ->chmod(__DIR__ . '/neuralyzer.phar', 0755)
            ->run();
    }


    public function release()
    {
        $this->stopOnFail(true);

        $this->gitVerifyEverythingIsCommited();
        $this->gitVerifyBranchIsMaster();
        $this->gitVerifyBranchIsUpToDate();

        // Verify everything is pulled
        $this->say('Need to implement a check to make sure everyhting has been pulled');

        $version = null;
        $currentVersion = \Edyan\Neuralyzer\Console\Application::VERSION;
        while (empty($version)) {
            $version = $this->ask("Whats the version number ? (current : $currentVersion)");
        }
        $versionDesc = null;
        while (empty($versionDesc)) {
            $versionDesc = $this->ask('Describe your release');
        }

        $this->say("Preparing version $version");

        // Patch the right files
        $this->taskReplaceInFile(__DIR__ . '/src/Console/Application.php')
             ->from("const VERSION = '$currentVersion';")
             ->to("const VERSION = '$version';")
             ->run();

        $this->phar();

        // Commit a bump version
        $this->taskGitStack()
             ->add(__DIR__ . '/src/Console/Application.php')
             ->add(__DIR__ . '/neuralyzer.phar')
             ->commit("Bump version $version")
             ->push('origin', 'master')
             ->tag($version)
             ->push('origin', $version)
             ->run();

        // Create a release
        $this->taskGitHubRelease($version)
             ->uri('edyan/neuralyzer')
             ->description($versionDesc)
             ->run();

        $this->say('Release ready, you can push');
    }


    private function setupDocker(
        string $php = '7.1',
        string $dbType = 'mysql',
        int $wait = 10
    ) {
        $this->destroyDocker();

        if (!in_array($dbType, ['mysql', 'postgres', 'sqlsrv'])) {
            throw new \InvalidArgumentException('Database can be only mysql, postgres or sqlsrv');
        }

        $this->startDb($dbType);
        $this->say("Waiting $wait seconds $dbType to start");
        sleep($wait);
        // Now create a DB For SQL Server as there is no option in the docker image
        if ($dbType === 'sqlsrv') {
            $this->createSQLServerDB();
        }

        $this->startPHP($php, $dbType);
    }

    private function startDb($type)
    {
        $image = $type;
        if ($type === 'sqlsrv') {
            $image = 'microsoft/mssql-server-linux:2017-latest';
        }

        $dbCt = $this->taskDockerRun($image)->detached()->name('robo_db')->option('--rm');
        if ($type === 'mysql') {
            $dbCt = $dbCt->env('MYSQL_ROOT_PASSWORD', 'rootRoot44root')->env('MYSQL_DATABASE', 'test_db');
        } elseif ($type === 'postgres') {
            $dbCt = $dbCt->env('POSTGRES_PASSWORD', 'rootRoot44root')->env('POSTGRES_DB', 'test_db');
        } elseif ($type === 'sqlsrv') {
            $dbCt = $dbCt->env('ACCEPT_EULA', 'Y')->env('SA_PASSWORD', 'rootRoot44root');
        }

        $dbCt->run();
    }


    private function createSQLServerDB()
    {
        $createSqlQuery = '/opt/mssql-tools/bin/sqlcmd -U sa -P rootRoot44root ';
        $createSqlQuery.= '-S localhost -Q "CREATE DATABASE test_db"';
        $this->taskDockerExec('robo_db')
             ->interactive()
             ->exec($this->taskExec($createSqlQuery))
             ->run();
    }


    private function startPHP(string $version, string $dbType)
    {
        $dbUser = 'root';
        if ($dbType === 'postgres') {
            $dbUser = 'postgres';
        } elseif ($dbType === 'sqlsrv') {
            $dbUser = 'sa';
        }

        $this->taskDockerRun('edyan/php:' . $version . '-sqlsrv')
            ->detached()->name('robo_php')->option('--rm')
            ->env('FPM_UID', getmyuid())->env('FPM_GID', getmygid())
            ->env('DB_HOST', 'robo_db')->env('DB_DRIVER', $dbType)
            ->env('DB_PASSWORD', 'rootRoot44root')->env('DB_USER', $dbUser)
            ->volume(__DIR__, '/var/www/html')
            ->link('robo_db', 'robo_db')
            ->run();
    }


    private function destroyDocker()
    {
        $cts = ['robo_db', 'robo_php'];
        foreach ($cts as $ct) {
            $this->stopContainer($ct);
        }
    }


    private function stopContainer(string $ct)
    {
        $process = new \Symfony\Component\Process\Process("docker ps | grep $ct | wc -l");
        $process->run();

        if ((int)$process->getOutput() === 0) {
            return;
        }

        $this->say('Destroying container ' . $ct);
        $this->taskDockerStop($ct)->run();
    }

    private function gitVerifyBranchIsMaster()
    {
        $branch = $this->taskGitStack()
                        ->silent(true)
                        ->exec('rev-parse --abbrev-ref HEAD')
                        ->run();
        if ($branch->getMessage() !== 'master') {
            throw new \RuntimeException('You must be on the master branch');
        }
    }

    private function gitVerifyEverythingIsCommited()
    {
        $modifiedFiles = $this->taskGitStack()
                              ->silent(true)
                              ->exec('status -s')
                              ->run();
        if (!empty($modifiedFiles->getMessage())) {
            throw new \RuntimeException('Some files have not been commited yet');
        }
    }

    private function gitVerifyBranchIsUpToDate()
    {
        $modifiedFiles = $this->taskGitStack()
                              ->silent(true)
                              ->exec('fetch --dry-run')
                              ->run();
        if ($modifiedFiles->getMessage() !== 'Is Up ToDate') {
            throw new \RuntimeException('Your local repo is not up to date, run "git pull"');
        }
    }


}
