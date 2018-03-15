<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

class RoboFile extends \Robo\Tasks
{
    /**
     * Run All Unit Test
     */
    public function test()
    {
        // runs PHPUnit tests
        $this->taskPHPUnit()->run();
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
