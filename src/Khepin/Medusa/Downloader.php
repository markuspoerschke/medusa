<?php
/**
 * @copyright 2013 Sébastien Armand
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Khepin\Medusa;

use Psr\Log\LoggerInterface;

class Downloader
{
    /**
     * @var MirroredPackage[]
     */
    private array $plannedDownloads = [];
    private string $storageDirectory;
    private Queue $queue;

    private LoggerInterface $logger;

    public function __construct(string $storageDirectory, LoggerInterface $logger)
    {
        $this->storageDirectory = $storageDirectory;
        $this->logger = $logger;
        $this->queue = new Queue($logger);
    }

    public function start(): void
    {
        // add planned downloads to queue
        foreach ($this->plannedDownloads as $package) {
            $this->add($package);
        }

        $this->queue->start();
    }

    public function addPackage(MirroredPackage $package): void
    {
        $this->plannedDownloads[] = $package;
    }

    private function add(MirroredPackage $package): void
    {
        $targetDirectory = $this->storageDirectory.'/'.$package->getName().".git";

        if (is_dir($targetDirectory)) {
            $this->logger->info("{$package->getName()} exists. Skipping.");
            return;
        }

        $this->logger->info("Start mirroring of {$package->getName()}");

        $command = (new ChainedCommand(sprintf('git clone --mirror %s %s', $package->getGitUrl(), $targetDirectory)))
            ->then(new ChainedCommand(sprintf('cd %s && git fsck', $targetDirectory)))
            ->then(new ChainedCommand(sprintf('cd %s && git update-server-info -f', $targetDirectory)));

        $this->queue->addChainedCommand($command);
    }
}
