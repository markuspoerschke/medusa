<?php

namespace Khepin\Medusa;

use Generator;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class Queue
{
    private const TIMEOUT = 3600;
    /**
     * @var ChainedCommand[]
     */
    private array $queue = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function start(): void
    {
        while (count($this->queue)) {
            $forEach = new EachPromise($this->getPromises(), ['concurrency' => 4]);
            $forEach->promise()->wait();
        }
    }

    public function addChainedCommand(ChainedCommand $command): void
    {
        $this->queue[] = $command;
    }

    private function getPromises(): Generator
    {
        while ($chainedCommand = array_pop($this->queue)) {
            $command = $chainedCommand->getCommand();

            $this->logger->info("Start $command");
            $process = new Process($command);
            $process->setTimeout(static::TIMEOUT);
            $promise = $this->wrapProcessInPromise($process);
            $promise->then(function () use ($chainedCommand, $command) {
                $this->logger->info("Finish $command");
                if ($chainedCommand->hasNextCommand()) {
                    $this->queue[] = $chainedCommand->getNextCommand();
                }
            });

            yield $promise;
        }
    }

    private function wrapProcessInPromise(Process $process): PromiseInterface
    {
        $promise = new Promise(fn() => $process->wait(), fn() => $process->stop());
        // todo add error handling
        $process->start(fn() => $promise->resolve($process->getExitCode()));

        return $promise;
    }
}
