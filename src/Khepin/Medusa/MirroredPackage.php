<?php

namespace Khepin\Medusa;

final class MirroredPackage
{
    private string $name;
    private string $gitUrl;

    public function __construct(string $name, string $gitUrl)
    {
        $this->name = $name;
        $this->gitUrl = $gitUrl;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGitUrl(): string
    {
        return $this->gitUrl;
    }
}
