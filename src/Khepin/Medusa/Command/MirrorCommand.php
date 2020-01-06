<?php
/**
 * @copyright 2013 Sébastien Armand
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Khepin\Medusa\Command;

use GuzzleHttp\Client;
use Khepin\Medusa\DependencyResolver;
use Khepin\Medusa\Downloader;
use Khepin\Medusa\MirroredPackage;
use Khepin\Medusa\SatisConfigUpdater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class MirrorCommand extends Command
{
    protected $guzzle;

    protected function configure()
    {
        $this
            ->setName('mirror')
            ->setDescription('Mirrors all repositories given a config file')
            ->setDefinition(array(
                new InputArgument('config', InputArgument::OPTIONAL, 'A config file', 'medusa.json')
            ))
            ->setHelp(<<<EOT
The <info>mirror</info> command reads the given medusa.json file and mirrors
the git repository for each package (including dependencies), so they can be used locally.
<warning>This will only work for repos hosted on github.com.</warning>
EOT
             )
        ;
    }

    /**
     * @param InputInterface  $input  The input instance
     * @param OutputInterface $output The output instance
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>First getting all dependencies</info>');
        $this->guzzle = new Client(['base_uri' => 'https://packagist.org']);
        $medusaConfig = $input->getArgument('config');
        $config = json_decode(file_get_contents($medusaConfig));
        /** @var MirroredPackage[] $repositories */
        $repositories = [];
        $logger = new ConsoleLogger($output);

        if (!$config) {
            throw new \Exception($medusaConfig . ': invalid json configuration');
        }

        // Check if there is a 'repositories' key in the config.
        // Otherwise we can ignore it.
        if (property_exists($config, 'repositories')) {
            foreach ($config->repositories as $repository) {
                if (property_exists($repository, 'name')) {
                    $repositories[] = new MirroredPackage(
                        $repository->name,
                        $repository->url
                    );
                }
            }
        }

        $dependencyResolver = new DependencyResolver(
            new Client(['base_uri' => 'https://packagist.org']),
            new ConsoleLogger($output)
        );
        foreach ($config->require as $dependencyPackageName) {
            $dependencyResolver->add($dependencyPackageName);
        }
        $dependencyResolver->start();

        $repositories = array_merge($repositories, $dependencyResolver->getResolvedPackages());

        $output->writeln('<info>Create mirror repositories</info>');
        $downloader = new Downloader($config->repodir, new ConsoleLogger($output));

        foreach ($repositories as $repo) {
            $downloader->addPackage($repo);
        }

        $downloader->start();

        // update satis configuration file
        $output->writeln('Update satis file');
        $satisConfigUpdater = new SatisConfigUpdater(
            $config->satisconfig,
            $config->satisurl,
            $config->repodir
        );

        foreach ($repositories as $repository) {
            $satisConfigUpdater->addPackage($repository->getName());
        }

        $satisConfigUpdater->write();

        $output->writeln('Done');
    }
}
