<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension\TranslationsEagerLoadingExtension;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticleTranslation;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the extension against a real Doctrine EntityManager (in-memory
 * sqlite): the DQL it produces, and that hydrating the fetch join leaves the
 * translations collections initialized, which is what makes later
 * getTranslation() calls filter in memory instead of querying per locale.
 */
final class TranslationsEagerLoadingExtensionTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
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
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testCollectionQueryFetchJoinsTranslations(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        (new TranslationsEagerLoadingExtension())
            ->applyToCollection($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class);

        self::assertSame(
            \sprintf('SELECT o, translations_a1 FROM %s o LEFT JOIN o.translations translations_a1', IntegrationArticle::class),
            $queryBuilder->getDQL(),
        );
    }

    public function testItemQueryFetchJoinsTranslations(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        (new TranslationsEagerLoadingExtension())
            ->applyToItem($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, ['id' => 1]);

        self::assertStringContainsString('LEFT JOIN o.translations translations_a1', $queryBuilder->getDQL());
    }

    public function testHydratedCollectionsAreInitialized(): void
    {
        $this->seedArticle(['en' => 'EN one', 'de' => 'DE one']);
        $this->seedArticle(['en' => 'EN two', 'de' => 'DE two']);

        $queryBuilder = $this->createQueryBuilder();
        (new TranslationsEagerLoadingExtension())
            ->applyToCollection($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class);

        /** @var list<IntegrationArticle> $articles */
        $articles = $queryBuilder->getQuery()->getResult();

        self::assertCount(2, $articles);
        foreach ($articles as $article) {
            $translations = $article->getTranslations();
            self::assertInstanceOf(PersistentCollection::class, $translations);
            // An initialized PersistentCollection resolves matching() in memory,
            // so every later getTranslation() is query-free.
            self::assertTrue($translations->isInitialized());
            self::assertCount(2, $translations);
        }
        self::assertSame('DE one', $articles[0]->getTranslation('de')->getTitle());
    }

    public function testSkipsNonTranslatableResources(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $dql = $queryBuilder->getDQL();

        (new TranslationsEagerLoadingExtension())
            ->applyToCollection($queryBuilder, new QueryNameGenerator(), DummyNotTranslatable::class);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $dql = $queryBuilder->getDQL();

        (new TranslationsEagerLoadingExtension(false))
            ->applyToCollection($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function testDoesNotDuplicateAnExistingFetchJoin(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();
        $extension = new TranslationsEagerLoadingExtension();

        $extension->applyToCollection($queryBuilder, $queryNameGenerator, IntegrationArticle::class);
        $dql = $queryBuilder->getDQL();
        $extension->applyToCollection($queryBuilder, $queryNameGenerator, IntegrationArticle::class);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function testAddsItsOwnJoinNextToALocaleConstrainedFilterJoin(): void
    {
        $queryBuilder = $this->createQueryBuilder()
            ->leftJoin('o.translations', 't', Join::WITH, "t.locale = 'en'");

        (new TranslationsEagerLoadingExtension())
            ->applyToCollection($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class);

        // Selecting the constrained alias would hydrate a partial collection, so
        // the filter join must be left alone and an unconstrained one added.
        self::assertSame(
            \sprintf("SELECT o, translations_a1 FROM %s o LEFT JOIN o.translations t WITH t.locale = 'en' LEFT JOIN o.translations translations_a1", IntegrationArticle::class),
            $queryBuilder->getDQL(),
        );
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('o')
            ->from(IntegrationArticle::class, 'o');
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
