<?php
/**
 * @copyright 2013 Sébastien Armand
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Khepin\Medusa;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Finds all the dependencies on which a given package relies
 */
final class DependencyResolver
{
    private array $resolvedPackages = [];
    private array $queue = [];
    private Client $httpClient;
    private LoggerInterface $logger;

    public function __construct(Client $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function add(string $package): void
    {
        if (isset($this->resolvedPackages[$package])) {
            return;
        }

        if (in_array($package, $this->queue)) {
            return;
        }

        $this->queue[] = $package;
    }

    public function start(): void
    {
        while (count($this->queue)) {
            $forEach = new EachPromise($this->getPromises(), ['concurrency' => 5]);
            $forEach->promise()->wait();
        }
    }

    public function getResolvedPackages(): array
    {
        return array_values(array_filter($this->resolvedPackages));
    }

    private function getPromises(): Generator
    {
        while ($packageName = array_pop($this->queue)) {
            $promise = $this->resolve($packageName);
            if ($promise !== null) {
                yield $promise;
            }
        }
    }

    private function resolve(string $packageName): ?PromiseInterface
    {
        if ($this->isSystemPackage($packageName)) {
            return null;
        }

        $this->logger->info('Resolve dependencies for '.$packageName);
        // reserve slot to prevent multiple downloads of the same package
        $this->resolvedPackages[$packageName] = null;

        $promise = $this->httpClient->getAsync('/packages/'.$packageName.'.json');
        $promise->then(function(ResponseInterface $response): void {
            $package = json_decode($response->getBody()->getContents());

            if ($package === null) {
                // todo error handling
                return;
            }

            foreach ($package->package->versions as $version) {
                $packageName = $package->package->name;
                $this->resolvedPackages[$packageName] = new MirroredPackage(
                    $packageName,
                    $package->package->repository
                );

                if (!isset($version->require)) {
                    continue;
                }

                foreach ($version->require as $dependencyPackageName => $version) {
                    $this->add($dependencyPackageName);
                }
            }
        });

        return $promise;
    }

    private function isSystemPackage($package): bool
    {
        // If the package name don't contain a "/" we will skip it here.
        // In a composer.json in the require / require-dev part you normally add packages
        // you depend on. A package name follows the format "vendor/package".
        // E.g. symfony/console
        // You can put other dependencies in here as well like `php` or `ext-zip`.
        // Those dependencies will be skipped (because they don`t have a vendor ;)).
        // The reason is simple: If you try to request the package "php" at packagist
        // you won`t get a JSON response with information we expect.
        // You will get valid HTML of the packagist search.
        // To avoid those errors and to save API calls we skip dependencies without a vendor.
        //
        // This follows the documentation as well:
        //
        // 	The package name consists of a vendor name and the project's name.
        // 	Often these will be identical - the vendor name just exists to prevent naming clashes.
        //	Source: https://getcomposer.org/doc/01-basic-usage.md
        return (strstr($package, '/')) ? false : true;
    }

    private function rename($package)
    {
        static $packages = array(
            'facebook/php-webdriver' => 'instaclick/php-webdriver',
            'metadata/metadata' => 'jms/metadata',
            'symfony/doctrine-bundle' => 'doctrine/doctrine-bundle',
            'symfony/translator' => 'symfony/translation',
            'willdurand/expose-translation-bundle' => 'willdurand/js-translation-bundle',

            // obsolete
            'zendframework/zend-registry' => null,

            // some older phpdocumentor version require these
            'zendframework/zend-translator' => null,
            'zendframework/zend-locale' => null,
            'phpdocumentor/template-installer' => null,
            'pear-symfony/eventdispatcher' => null
        );

        if (array_key_exists($package, $packages)) {
            return $packages[$package];
        }

        return $package;
    }
}
