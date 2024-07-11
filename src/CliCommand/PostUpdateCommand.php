<?php

namespace Ufo\JsonRpcBundle\CliCommand;

use Composer\Script\Event;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class PostUpdateCommand extends Command
{
    protected static string $defaultName = 'ufo:json-rpc:post-update';
    private string $configFile = __DIR__.'/../../../config/packages/ufo_json_rpc.yaml';
    private string $newConfigFile = __DIR__.'/../../../install/ufo_json_rpc.yaml';

    protected function configure(): void
    {
        $this
            ->setDescription('Handles updates for UfoJsonRpcBundle.');
    }

    public static function run(Event $event): void
    {
        $installedPackage = $event->getComposer()->getRepositoryManager()->getLocalRepository()->findPackage('ufo-tech/json-rpc-bundle', '*');
        $currentVersion = $installedPackage->getPrettyVersion();

        if (version_compare($currentVersion, '7.0.0', '<')) {
            $command = new self();
            $command->execute();
        }
    }

    protected function execute(InputInterface $input = null, OutputInterface $output = null)
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->configFile) && $filesystem->exists($this->newConfigFile)) {
            $oldConfig = file_get_contents($this->configFile);
            $newConfig = file_get_contents($this->newConfigFile);

            // Comment out the old config
            $commentedOldConfig = preg_replace('/^/m', '# ', $oldConfig);

            // Combine new and old config
            $updatedConfig = $newConfig . "\n" . $commentedOldConfig;

            file_put_contents($this->configFile, $updatedConfig);

            if ($output) {
                $output->writeln('Configuration files have been updated.');
            }
        }

        return Command::SUCCESS;
    }
}