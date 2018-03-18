<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

class RoboFile extends \Robo\Tasks
{
    public function dockertest()
    {
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
    public function test($opts = ['php' => '7.1', 'db' => 'mysql', 'keep-cts' => false])
    {
        $this->setupDocker($opts['php'], $opts['db']);

        $this->taskDockerExec('robo_php')
            ->interactive()
            ->option('--user', 'www-data')
            ->exec($this->taskExec('/bin/bash -c "cd /var/www/html ; vendor/bin/phpunit"'))
            ->run();

        if ($opts['keep-cts'] === false) {
            $this->destroyDocker();
        }
    }


    private function setupDocker(string $php = '7.1', string $dbType = 'mysql')
    {
        $this->destroyDocker();

        if (!in_array($dbType, ['mysql', 'postgres'])) {
            throw new \InvalidArgumentException('Database can be only mysql or postgres');
        }

        $this->startDb($dbType);
        $this->startPHP($php, $dbType);
    }

    private function startDb($type)
    {
        $dbCt = $this->taskDockerRun($type)->detached()->name('robo_db')->option('--rm');
        if ($type === 'mysql') {
            $dbCt = $dbCt->env('MYSQL_ROOT_PASSWORD', 'root')
                         ->env('MYSQL_DATABASE', 'test_db');
        } elseif ($type === 'postgres') {
            $dbCt = $dbCt->env('POSTGRES_PASSWORD', 'root')
                         ->env('POSTGRES_USER', 'root')
                         ->env('POSTGRES_DB', 'test_db');
        }

        $dbCt->run();

        $this->say("Waiting 10 seconds $type to start");
        sleep(10);
    }


    private function startPHP(string $version, string $dbType)
    {
        $driver = $dbType === 'postgres' ? 'pdo_pgsql' : 'pdo_mysql';

        $this->taskDockerRun('edyan/php:' . $version)
            ->detached()->name('robo_php')->option('--rm')
            ->env('FPM_UID', getmyuid())->env('FPM_GID', getmygid())
            ->env('DB_HOST', 'mysql')->env('DB_PASSWORD', 'root')->env('DB_DRIVER', $driver)
            ->volume(__DIR__, '/var/www/html')
            ->link('robo_db', 'mysql')
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

    /**
     * Build phar executable.
     */
    public function pharBuild()
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

            ->taskCopyDir([
                __DIR__ . '/src' => $buildDir . '/src'
            ])

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
                ->printed(true)
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
}
