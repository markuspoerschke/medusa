<?php

namespace Khepin\Medusa;

use Psr\Log\LoggerInterface;

class Updater
{
    /**
     * @var string[]
     */
    private array $repositories = [];
    private Queue $queue;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->queue = new Queue($logger);
    }

    public function start(): void
    {
        // add planned downloads to queue
        foreach ($this->repositories as $package) {
            $this->add($package);
        }

        $this->queue->start();
    }

    public function addRepository(string $repository): void
    {
        $this->repositories[] = $repository;
    }

    private function add(string $repository): void
    {
        $this->logger->info("Start updating of {$repository}");

        $command = (new ChainedCommand(sprintf('cd %s && git fetch --prune', $repository)))
            ->then(new ChainedCommand(sprintf('cd %s && git update-server-info -f', $repository)));

        $this->queue->addChainedCommand($command);
    }
}
