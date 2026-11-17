<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\State;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoaderInterface;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\State\TranslationsCollectionProvider;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use PHPUnit\Framework\TestCase;

final class TranslationsCollectionProviderTest extends TestCase
{
    public function testLoadsTheTranslationsOfTheProvidedCollection(): void
    {
        $operation = new GetCollection(class: DummyTranslatable::class);
        $collection = new \ArrayIterator([new DummyTranslatable(), new DummyTranslatable()]);
        $decorated = $this->createDecorated($collection);
        $loader = $this->createLoader([DummyTranslatable::class]);

        $provider = new TranslationsCollectionProvider($decorated, $loader);
        $result = $provider->provide($operation, ['id' => 1], ['filters' => ['locale' => 'de']]);

        self::assertSame($collection, $result);
        self::assertSame([$collection], $loader->loaded);
        self::assertSame([$operation, ['id' => 1]], [$decorated->operation, $decorated->uriVariables]);
        // the eager-loading extension reads this flag and leaves the query alone
        self::assertSame(
            [TranslationsCollectionProvider::TRANSLATIONS_LOADED_AFTER_QUERY => true, 'filters' => ['locale' => 'de']],
            $decorated->context,
        );
    }

    public function testResolvesTheEntityClassFromTheStateOptions(): void
    {
        $operation = new GetCollection(class: DummyNotTranslatable::class, stateOptions: new Options(entityClass: DummyTranslatable::class));
        $decorated = $this->createDecorated([new DummyTranslatable()]);
        $loader = $this->createLoader([DummyTranslatable::class]);

        (new TranslationsCollectionProvider($decorated, $loader))->provide($operation);

        self::assertCount(1, $loader->loaded);
        self::assertArrayHasKey(TranslationsCollectionProvider::TRANSLATIONS_LOADED_AFTER_QUERY, $decorated->context);
    }

    public function testLeavesUnsupportedResourcesToTheDecoratedProvider(): void
    {
        $operation = new GetCollection(class: DummyNotTranslatable::class);
        $decorated = $this->createDecorated([new DummyNotTranslatable()]);
        $loader = $this->createLoader([]);

        $result = (new TranslationsCollectionProvider($decorated, $loader))->provide($operation, [], ['foo' => 'bar']);

        self::assertEquals([new DummyNotTranslatable()], $result);
        self::assertSame([], $loader->loaded);
        self::assertSame(['foo' => 'bar'], $decorated->context);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $operation = new GetCollection(class: DummyTranslatable::class);
        $decorated = $this->createDecorated([new DummyTranslatable()]);
        $loader = $this->createLoader([DummyTranslatable::class]);

        (new TranslationsCollectionProvider($decorated, $loader, false))->provide($operation, [], ['foo' => 'bar']);

        self::assertSame([], $loader->loaded);
        self::assertSame(['foo' => 'bar'], $decorated->context);
    }

    public function testPassesANullResultThrough(): void
    {
        $operation = new GetCollection(class: DummyTranslatable::class);
        $loader = $this->createLoader([DummyTranslatable::class]);

        $result = (new TranslationsCollectionProvider($this->createDecorated(null), $loader))->provide($operation);

        self::assertNull($result);
        self::assertSame([], $loader->loaded);
    }

    /**
     * @param iterable<mixed>|null $result
     *
     * @return ProviderInterface<object>&object{operation: ?Operation, uriVariables: array<mixed>, context: array<mixed>}
     */
    private function createDecorated(?iterable $result): ProviderInterface
    {
        return new class($result) implements ProviderInterface {
            public ?Operation $operation = null;

            /** @var array<mixed> */
            public array $uriVariables = [];

            /** @var array<mixed> */
            public array $context = [];

            /**
             * @param iterable<mixed>|null $result
             */
            public function __construct(private readonly ?iterable $result)
            {
            }

            public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
            {
                $this->operation = $operation;
                $this->uriVariables = $uriVariables;
                $this->context = $context;

                return $this->result;
            }
        };
    }

    /**
     * @param list<class-string> $supportedClasses
     *
     * @return TranslationsLoaderInterface&object{loaded: list<iterable<mixed>>}
     */
    private function createLoader(array $supportedClasses): TranslationsLoaderInterface
    {
        return new class($supportedClasses) implements TranslationsLoaderInterface {
            /** @var list<iterable<mixed>> */
            public array $loaded = [];

            /**
             * @param list<class-string> $supportedClasses
             */
            public function __construct(private readonly array $supportedClasses)
            {
            }

            public function supports(string $entityClass): bool
            {
                return \in_array($entityClass, $this->supportedClasses, true);
            }

            public function load(iterable $translatables): void
            {
                $this->loaded[] = $translatables;
            }
        };
    }
}
