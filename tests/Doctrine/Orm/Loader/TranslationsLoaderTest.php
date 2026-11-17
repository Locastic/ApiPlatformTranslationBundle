<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Loader;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoader;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticleTranslation;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationPage;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationPageTranslation;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\QueryLogger;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the loader against a real Doctrine EntityManager (in-memory
 * sqlite). The loader relies on one ORM behavior: hydrating a fetch join onto
 * entities that are already managed initializes their uninitialized
 * collections, keyed by the `indexBy` of the mapping. CI runs these tests on
 * the lowest supported ORM version too.
 */
final class TranslationsLoaderTest extends TestCase
{
    private EntityManagerInterface $em;

    private QueryLogger $queryLogger;

    protected function setUp(): void
    {
        $this->queryLogger = new QueryLogger();

        // Use the long-standing `...Configuration` name: the shorter `...Config`
        // alias was only added mid-3.x, and the ORM floor (installed on the
        // lowest-dependencies CI leg) does not have it.
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../../../Fixtures/Orm'], true);
        // On PHP 8.4+ (where Symfony 8 / var-exporter 8 dropped the lazy-ghost trait)
        // ORM needs native lazy objects; on older PHP the lazy-ghost trait handles it
        // but still requires a configured proxy directory, which this config helper
        // does not set on its own.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir());
            $config->setProxyNamespace('LocasticApiPlatformTranslationTestProxies');
        }
        $config->setMiddlewares([new Middleware($this->queryLogger)]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testInitializesTheCollectionsOfManagedEntitiesInOneQuery(): void
    {
        $this->seedPage(['en' => 'EN one', 'de' => 'DE one']);
        $this->seedPage(['en' => 'EN two']);
        $this->seedPage(['de' => 'DE three', 'hr' => 'HR three']);

        $pages = $this->findAll(IntegrationPage::class);
        foreach ($pages as $page) {
            self::assertFalse($this->collectionOf($page)->isInitialized());
        }

        $this->queryLogger->reset();
        $this->createLoader()->load($pages);

        self::assertCount(1, $this->queryLogger->queries);
        foreach ($pages as $page) {
            self::assertTrue($this->collectionOf($page)->isInitialized());
            self::assertFalse($this->collectionOf($page)->isDirty());
        }

        // every locale is loaded, keyed by the indexBy of the mapping
        self::assertSame(['en', 'de'], $pages[0]->getTranslations()->getKeys());
        self::assertSame(['en'], $pages[1]->getTranslations()->getKeys());
        self::assertSame(['de', 'hr'], $pages[2]->getTranslations()->getKeys());

        // reads are served from memory now, and nothing is scheduled for a write
        self::assertSame('DE one', $pages[0]->getTranslation('de')->getTitle());
        self::assertSame('HR three', $pages[2]->getTranslation('hr')->getTitle());
        $this->em->flush();
        self::assertCount(1, $this->queryLogger->queries);
    }

    public function testInitializesTheEmptyCollectionOfAnEntityWithoutTranslations(): void
    {
        $this->seedPage(['en' => 'EN one']);
        $this->seedPage([]);

        $pages = $this->findAll(IntegrationPage::class);
        $this->queryLogger->reset();
        $this->createLoader()->load($pages);

        self::assertTrue($this->collectionOf($pages[1])->isInitialized());
        self::assertCount(0, $pages[1]->getTranslations());
        self::assertCount(1, $this->queryLogger->queries);
    }

    public function testLoadsAMappingWithoutIndexBy(): void
    {
        $this->seedArticle(['en' => 'EN one', 'de' => 'DE one']);

        $articles = $this->findAll(IntegrationArticle::class);
        $this->queryLogger->reset();
        $this->createLoader()->load($articles);

        self::assertTrue($this->collectionOf($articles[0])->isInitialized());
        self::assertSame([0, 1], $articles[0]->getTranslations()->getKeys());
        self::assertSame('DE one', $articles[0]->getTranslation('de')->getTitle());
        self::assertCount(1, $this->queryLogger->queries);
    }

    public function testLoadsEachEntityClassWithItsOwnQuery(): void
    {
        $this->seedPage(['en' => 'Page EN']);
        $this->seedArticle(['en' => 'Article EN']);

        $entities = [...$this->findAll(IntegrationPage::class), ...$this->findAll(IntegrationArticle::class)];
        $this->queryLogger->reset();
        $this->createLoader()->load($entities);

        self::assertCount(2, $this->queryLogger->queries);
        self::assertTrue($this->collectionOf($entities[0])->isInitialized());
        self::assertTrue($this->collectionOf($entities[1])->isInitialized());
    }

    public function testSkipsWhatNeedsNoLoading(): void
    {
        $this->seedPage(['en' => 'EN one']);

        $pages = $this->findAll(IntegrationPage::class);
        $loader = $this->createLoader();
        $loader->load($pages);
        $this->queryLogger->reset();

        // already initialized, not managed, not translatable, not an entity
        $loader->load([$pages[0], new IntegrationPage(), new DummyTranslatable(), new DummyNotTranslatable(), ['id' => 1], 'scalar']);
        $loader->load([]);

        self::assertSame([], $this->queryLogger->queries);
    }

    public function testLoadsLargeSetsInBatches(): void
    {
        for ($i = 0; $i < 501; ++$i) {
            $page = new IntegrationPage();
            $translation = new IntegrationPageTranslation();
            $translation->setLocale('en');
            $translation->setTitle('EN '.$i);
            $page->addTranslation($translation);
            $this->em->persist($page);
        }
        $this->em->flush();
        $this->em->clear();

        $pages = $this->findAll(IntegrationPage::class);
        $this->queryLogger->reset();
        $this->createLoader()->load($pages);

        self::assertCount(2, $this->queryLogger->queries);
        self::assertTrue($this->collectionOf($pages[0])->isInitialized());
        self::assertTrue($this->collectionOf($pages[500])->isInitialized());
    }

    public function testAcceptsATraversable(): void
    {
        $this->seedPage(['en' => 'EN one']);

        $pages = $this->findAll(IntegrationPage::class);
        $this->createLoader()->load(new \ArrayIterator($pages));

        self::assertTrue($this->collectionOf($pages[0])->isInitialized());
    }

    public function testSupportsMappedTranslatablesOnly(): void
    {
        $loader = $this->createLoader();

        self::assertTrue($loader->supports(IntegrationPage::class));
        self::assertTrue($loader->supports(IntegrationArticle::class));
        self::assertFalse($loader->supports(DummyNotTranslatable::class));
        self::assertFalse($loader->supports(IntegrationPageTranslation::class));
    }

    public function testDoesNotSupportAClassWithoutAnEntityManager(): void
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn(null);

        self::assertFalse((new TranslationsLoader($managerRegistry))->supports(DummyTranslatable::class));
    }

    private function createLoader(): TranslationsLoader
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($this->em);

        return new TranslationsLoader($managerRegistry);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return list<T>
     */
    private function findAll(string $entityClass): array
    {
        /** @var list<T> $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('o')
            ->from($entityClass, 'o')
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $entities;
    }

    /**
     * @param AbstractTranslatable<covariant \Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface> $translatable
     *
     * @return PersistentCollection<array-key, object>
     */
    private function collectionOf(AbstractTranslatable $translatable): PersistentCollection
    {
        $translations = $translatable->getTranslations();
        self::assertInstanceOf(PersistentCollection::class, $translations);

        return $translations;
    }

    /**
     * @param array<string, string> $localeToTitle
     */
    private function seedPage(array $localeToTitle): void
    {
        $page = new IntegrationPage();
        foreach ($localeToTitle as $locale => $title) {
            $translation = new IntegrationPageTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($title);
            $page->addTranslation($translation);
        }

        $this->em->persist($page);
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param array<string, string> $localeToTitle
     */
    private function seedArticle(array $localeToTitle): void
    {
        $article = new IntegrationArticle();
        foreach ($localeToTitle as $locale => $title) {
            $translation = new IntegrationArticleTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($title);
            $article->addTranslation($translation);
        }

        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();
    }
}
