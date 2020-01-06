<?php

namespace Khepin\Medusa;

final class ChainedCommand
{
    private string $command;
    private ?ChainedCommand $nextCommand;

    public function __construct(string $command)
    {
        $this->command = $command;
    }

    public function then(ChainedCommand $command): self
    {
        $new = clone $this;
        $new->nextCommand = $command;

        return $new;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function hasNextCommand(): bool
    {
        return $this->nextCommand !== null;
    }

    public function getNextCommand(): ChainedCommand
    {
        return $this->nextCommand;
    }
}
