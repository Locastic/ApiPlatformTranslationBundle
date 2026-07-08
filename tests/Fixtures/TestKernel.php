<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Locastic\ApiPlatformTranslationBundle\ApiPlatformTranslationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $translationConfig api_platform_translation extension config
     * @param array<string, mixed> $frameworkConfig   extra framework extension config
     */
    public function __construct(
        string $environment = 'test',
        bool $debug = true,
        private readonly array $translationConfig = [],
        private readonly array $frameworkConfig = [],
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new ApiPlatformBundle(),
            new ApiPlatformTranslationBundle(),
        ];
    }

    public function getConfigDir(): string
    {
        // Keep the app-level config dir out of the repository: the project dir
        // is the bundle root, and debug kernels dump reference files into it.
        return $this->getCacheDir().'/config';
    }

    public function getCacheDir(): string
    {
        // The container is cached per directory; configs passed in code are not
        // tracked as resources, so each config combination gets its own dir.
        $configHash = hash('xxh128', serialize([$this->translationConfig, $this->frameworkConfig]));

        return sys_get_temp_dir().'/aptb-tests/cache/'.$this->environment.'/'.$configHash;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/aptb-tests/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        // Keep the bundle services visible to the test; private unused
        // services are otherwise inlined or removed during compilation.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'locastic_api_platform_translation.')) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', array_merge([
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'validation' => ['enabled' => true],
        ], $this->frameworkConfig));

        if ($this->translationConfig) {
            $container->extension('api_platform_translation', $this->translationConfig);
        }

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
        ]);

        $container->extension('api_platform', [
            'title' => 'Test',
            'mapping' => ['paths' => []],
            'doctrine' => false,
        ]);
    }
}
