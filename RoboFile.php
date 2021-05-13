<?php

declare(strict_types=1);

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class RoboFile extends \Robo\Tasks
{
    private $phpVersion = 7.2;
    private $composer = '/usr/local/bin/composer';
    private $dbType = 'mysql';
    private $dbVersion = 'latest';
    private $dbWait = 10;
    private $dbPass = 'rootRoot44root';

    /**
     * A test command just to make sure robo works on that computer
     */
    public function dockertest(): void
    {
        $this->io()->title('Run various docker commands to make sur it works on that computer');

        $this->say('Will try to stop an existing container if we find it');
        $this->stopContainer('robo_test');

        $this->stopOnFail(true);

        $this
            ->taskDockerRun('edyan/php:7.2')
            ->printOutput(false)
            ->printMetadata(false)
            ->name('robo_test')
            ->detached()
            ->option('--rm')
            ->run();

        $this
            ->taskDockerExec('robo_test')
            ->printOutput(true)
            ->printMetadata(true)
            ->interactive()
            ->exec($this->taskExec('php -v'))
            ->run();

        $this
            ->taskDockerStop('robo_test')
            ->printOutput(false)
            ->printMetadata(false)
            ->run();
    }

    /**
     * Run All Unit Test
     *
     * @param  array  $opts
     */
    public function test(array $opts = [
        'php' => '7.2',
        'db' => 'mysql',
        'keep-cts' => false,
        'wait' => 10,
        'db-version' => 'latest',
        'no-coverage' => false,
        'file' => '',
    ]): void
    {
        $this->stopOnFail(true);

        $this->phpVersion = $opts['php'];
        $this->dbType = $opts['db'];
        $this->dbVersion = $opts['db-version'];
        $this->dbWait = (int) $opts['wait'];
        $this->noCoverage = $opts['no-coverage'];

        $this->setupDocker();

        // Install composer and do a composer install to make sure
        // dependencies are compatible with that PHP version
        $this->installComposer();
        $this->doComposerInstall();

        $cmd = '/bin/bash -c "cd /var/www/html ; vendor/bin/phpunit ';
        $coverageOpt = '--no-coverage ';

        // Run the tests
        $task = $this
            ->taskDockerExec('robo_php')
            ->interactive()
            ->option('--user', 'www-data');

        if ($this->noCoverage === false) {
            $task = $task->option('--env', 'XDEBUG_MODE=coverage');
            $coverageOpt = '--coverage-clover=coverage.xml ';
        }

        $task
            ->exec($this->taskExec($cmd . $coverageOpt . $opts['file'] . '"'))
            ->run();

        if ($opts['keep-cts'] === false) {
            $this->destroyDocker();
        }
    }

    /**
     * Build an executable phar
     */
    public function phar($opts = ['neuralyzer-version' => 'dev']): \Robo\Result
    {
        $this->io()->title('Build neuralyzer.phar');

        if ((int) ini_get('phar.readonly') === 1) {
            throw new \RuntimeException(
                'You must have phar.readonly = 1 or run' . PHP_EOL .
                'php -d phar.readonly=0 vendor/bin/robo (phar|release)'
            );
        }

        // Create a collection builder to hold the temporary
        // directory until the pack phar task runs.Call to undefined method RoboFile::startProgressIndicator()
        $collection = $this->collectionBuilder();
        $workDir = $collection->tmpDir();
        $buildDir = "${workDir}/neuralyzer";

        $preparationResult = $this->preparePharTask($workDir, $buildDir);

        // Exit if the preparation step failed
        if (! $preparationResult->wasSuccessful()) {
            return $preparationResult;
        }

        $currentVersion = \Edyan\Neuralyzer\Console\Application::VERSION;
        $this->say("Setting version number to {$opts['neuralyzer-version']}");
        $this
            ->taskReplaceInFile($buildDir . '/src/Console/Application.php')
            ->from("const VERSION = '${currentVersion}';")
            ->to("const VERSION = '{$opts['neuralyzer-version']}';")
            ->run();

        $this->say('Force Robo to compress up to 1500 files in a phar');
        $this
            ->taskReplaceInFile(__DIR__ . '/vendor/consolidation/robo/src/Task/Development/PackPhar.php')
            ->from('if (count($this->files) > 1000)')
            ->to('if (count($this->files) > 1500)')
            ->run();

        $fakerLang = Finder::create()
            ->ignoreVCS(true)
            ->files()
            ->name('*')
            ->path('src/Faker/Dictionary')
            ->in(is_dir($buildDir) ? $buildDir : __DIR__);

        // Build the phar
        return $collection
            ->taskPackPhar('neuralyzer.phar')
            ->compress()
            ->addFile('bin/neuralyzer', 'bin/neuralyzer')
            ->addFile('config/services.yml', 'config/services.yml')
            ->addFiles($this->getFilesForPhar($buildDir))
            ->addFiles($fakerLang)
            ->executable('bin/neuralyzer')
            ->taskFilesystemStack()
            ->chmod(__DIR__ . '/neuralyzer.phar', 0755)
            ->run();
    }

    /**
     * Create a GitHub release
     */
    public function release(): void
    {
        $this->io()->title('Create a GitHub release');

        if (empty(\Robo\Robo::config()->get('settings.github_token'))) {
            throw new \RuntimeException(
                'You must set a github token in robo config (settings.github_token)'
            );
        }

        $this->stopOnFail(true);

        $this->gitVerifyEverythingIsCommited();
        $this->gitVerifyBranchIsMaster();
        $this->gitVerifyBranchIsUpToDate();

        $version = null;
        $currentVersion = \Edyan\Neuralyzer\Console\Application::VERSION;
        while (empty($version)) {
            $version = $this->ask("Whats the version number ? (current : ${currentVersion})");
        }
        $versionDesc = null;
        while (empty($versionDesc)) {
            $versionDesc = $this->ask('Describe your release');
        }

        $this->say("Preparing version ${version}");

        $this->phar(['neuralyzer-version' => $version]);

        // Commit a bump version
        $this->taskGitStack()
            ->add(__DIR__ . '/src/Console/Application.php')
            ->add(__DIR__ . '/neuralyzer.phar')
            ->commit("Bump version ${version}")
            ->push('origin', 'master')
            ->tag($version)
            ->push('origin', $version)
            ->run();

        // Create a release
        $this->taskGitHubRelease($version)
            ->name($versionDesc)
            ->tag($version)
            ->description('')
            ->owner('edyan')
            ->repo('neuralyzer')
            ->accessToken(\Robo\Robo::config()->get('settings.github_token'))
            ->run();

        $this->say('Release ready, you can push');
    }

    /**
     * Do a composer update with the minimum PHP version
     */
    public function composerUpdate(): void
    {
        $this->io()->title('Do a composer update');

        $this->stopContainer('robo_php');

        $this
            ->taskDockerRun('edyan/php:' . $this->phpVersion)
            ->printOutput(false)
            ->printMetadata(false)
            ->detached()->name('robo_php')->option('--rm')
            ->volume(__DIR__, '/var/www/html')
            ->run();

        $this->installComposer();

        $cmd = "php {$this->composer} --prefer-dist --no-progress -o update";
        $this
            ->taskDockerExec('robo_php')
            ->printMetadata(false)
            ->option('--user', 'www-data')
            ->exec($this->taskExec("/bin/bash -c 'cd /var/www/html ; $cmd'"))
            ->run();

    }

    private function setupDocker(): void
    {
        $this->destroyDocker();

        if (! in_array($this->dbType, ['mysql', 'pgsql', 'sqlsrv'])) {
            throw new \InvalidArgumentException('Database can be only mysql, pgsql or sqlsrv');
        }

        // Start DB And display a progress bar
        $this->startDb();
        $this->waitForDB();
        $this->startPHP();
    }

    private function startDb(): void
    {
        $dbCt = $this
            ->taskDockerRun($this->getDBImageName())
            ->printOutput(false)
            ->detached()
            ->name('robo_db')
            ->option('--rm');

        if ($this->dbType === 'mysql') {
            $dbCt = $dbCt
                ->env('MYSQL_ROOT_PASSWORD', $this->dbPass)
                ->env('MYSQL_DATABASE', 'test_db');
            $dbCt = $dbCt->exec('--default-authentication-plugin=mysql_native_password --enable-local-infile');
        } elseif ($this->dbType === 'pgsql') {
            $dbCt = $dbCt->env('POSTGRES_PASSWORD', $this->dbPass)->env('POSTGRES_DB', 'test_db');
        } elseif ($this->dbType === 'sqlsrv') {
            $dbCt = $dbCt->env('ACCEPT_EULA', 'Y')->env('SA_PASSWORD', $this->dbPass);
        }

        $dbCt->run();
    }

    private function getDBImageName(): string
    {
        $image = $this->dbType . ':' . $this->dbVersion;
        if ($this->dbType === 'sqlsrv') {
            $dbVersion = $this->dbVersion === 'latest' ? '2019-latest' : $this->dbVersion;
            $image = 'mcr.microsoft.com/mssql/server:' . $dbVersion;
        } elseif ($this->dbType === 'pgsql') {
            $image = 'postgres:' . $this->dbVersion;
        }

        return $image;
    }

    private function waitForDB(): void
    {
        $this->say("Waiting {$this->dbWait} seconds for DB to start");
        $progressBar = new ProgressBar($this->getOutput(), $this->dbWait);
        $progressBar->start();
        for ($i = 0; $i < $this->dbWait; ++$i) {
            sleep(1);
            $progressBar->advance();
        }
        $progressBar->finish();

        echo PHP_EOL;
    }

    private function startPHP(): void
    {
        if (! in_array($this->phpVersion, ['7.2', '7.3', '7.4', '8.0'])) {
            throw new \InvalidArgumentException('PHP Version must be 7.2, 7.3, 7.4 or 8.0');
        }

        $dbUser = 'root';
        if ($this->dbType === 'pgsql') {
            $dbUser = 'postgres';
        } elseif ($this->dbType === 'sqlsrv') {
            $dbUser = 'sa';
        }

        $this
            ->taskDockerRun('edyan/php:' . $this->phpVersion . '-sqlsrv')
            ->printOutput(false)
            ->detached()->name('robo_php')->option('--rm')
            ->env('DB_HOST', 'robo_db')->env('DB_DRIVER', 'pdo_' . $this->dbType)
            ->env('DB_PASSWORD', $this->dbPass)->env('DB_USER', $dbUser)
            ->volume(__DIR__, '/var/www/html')
            ->link('robo_db', 'robo_db')
            ->run();
    }

    private function destroyDocker(): void
    {
        $cts = ['robo_db', 'robo_php'];
        foreach ($cts as $ct) {
            $this->stopContainer($ct);
        }
    }

    private function stopContainer(string $ct): void
    {
        $dockerPs = new Process([
            'docker', 'container', 'ps', '--format', '{{.ID}}', '--all', '--filter', "name=${ct}",
        ]);
        $dockerPs->run();

        if (empty($dockerPs->getOutput())) {
            $this->say('Container ' . $ct . ' does not exist');
            return;
        }

        $this->say('Destroying container ' . $ct);
        $this->taskDockerStop($ct)->silent(true)->run();
    }

    private function installComposer(): void
    {
        $this->say('Install composer');
        $url = 'https://getcomposer.org/download/latest-stable/composer.phar';
        $this
            ->taskDockerExec('robo_php')
            ->printMetadata(false)
            ->exec($this->taskExec("php -r \"copy('$url', '{$this->composer}');\""))
            ->run();
    }

    private function doComposerInstall(): void
    {
        $this->say('Do a composer install');

        $cmd = "php {$this->composer} --dry-run --quiet --no-autoloader --no-progress install";
        $this
            ->taskDockerExec('robo_php')
            ->printMetadata(false)
            ->option('--user', 'www-data')
            ->exec($this->taskExec("/bin/bash -c 'cd /var/www/html ; $cmd'"))
            ->run();
    }

    private function gitVerifyBranchIsMaster(): void
    {
        $branch = $this->taskGitStack()
            ->silent(true)
            ->exec('rev-parse --abbrev-ref HEAD')
            ->run();
        if ($branch->getMessage() !== 'master') {
            throw new \RuntimeException('You must be on the master branch');
        }
    }

    private function gitVerifyEverythingIsCommited(): void
    {
        $modifiedFiles = $this->taskGitStack()
            ->silent(true)
            ->exec('status -s')
            ->run();
        if (! empty($modifiedFiles->getMessage())) {
            throw new \RuntimeException('Some files have not been commited yet');
        }
    }

    private function gitVerifyBranchIsUpToDate(): void
    {
        $modifiedFiles = $this->taskGitStack()
            ->silent(true)
            ->exec('fetch --dry-run')
            ->run();
        if (! empty($modifiedFiles->getMessage())) {
            throw new \RuntimeException('Your local repo is not up to date, run "git pull"');
        }
    }

    private function preparePharTask(string $workDir, string $buildDir): \Robo\Result
    {
        $prepTasks = $this->collectionBuilder();
        return $prepTasks
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
    }

    private function getFilesForPhar(string $buildDir): Finder
    {
        // Decide which files we're going to pack
        return Finder::create()
            ->ignoreVCS(true)
            ->files()
            ->name('*.php')
            ->name('*.exe') // for symfony/console/Resources/bin/hiddeninput.exe
            ->path('src')
            ->path('vendor')
            ->notPath('docs')
            ->notPath('/vendor\/.*\/[Tt]est/')
            // incomplete and need to reduce for phar compression
            //->notPath('ro_MD')
            //->notPath('sr_Cyrl_RS')
            //->notPath('sr_Latn_RS')
            ->in(is_dir($buildDir) ? $buildDir : __DIR__);
    }
}
