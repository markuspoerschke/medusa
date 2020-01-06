<?php

namespace Khepin\Medusa;

use Composer\Json\JsonFile;

class SatisConfigUpdater
{
    private JsonFile $satisConfig;
    private array $repositories = [];
    private ?string $satisUrl;
    private string $repositoryDirectory;

    public function __construct(string $satisConfigFile, ?string $satisUrl, string $repositoryDirectory)
    {
        $this->satisConfig = new JsonFile($satisConfigFile);
        $this->satisUrl = $satisUrl;
        $this->repositoryDirectory = $repositoryDirectory;
    }

    public function addPackage(string $packageName): void
    {
        $repositoryConfig = [
            'type' => 'git',
        ];

        if ($this->satisUrl !== null) {
            $repositoryConfig['url'] = $this->satisUrl.'/'.$packageName.'.git';
        } else {
            $url = ltrim(realpath($this->repositoryDirectory.'/'.$packageName.'.git'), '/');
            $repositoryConfig['url'] = 'file:///'.$url;
        }

        $this->repositories[] = $repositoryConfig;
    }

    public function write(): void
    {
        $config = $this->satisConfig->read();
        foreach ($this->repositories as $repository) {
            $config['repositories'][] = $repository;
        }

        $config['repositories'] = $this->deduplicate($config['repositories']);
        $this->satisConfig->write($config);
    }

    private function deduplicate(array $repositories): array
    {
        $newRepositories = [];

        foreach ($repositories as $repository) {
            $newRepositories[$repository['url']] = $repository;
        }

        return array_values($newRepositories);
    }
}
