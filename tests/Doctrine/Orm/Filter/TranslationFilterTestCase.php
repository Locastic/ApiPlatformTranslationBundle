<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Extension\PaginationExtension;
use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\LocaleAwareTranslatorInterface;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticleTranslation;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\QueryLogger;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Harness for the translation filters: a real Doctrine EntityManager
 * (in-memory sqlite) to assert both the produced DQL and actual results, and
 * a Translator resolving the locale from a real request.
 */
abstract class TranslationFilterTestCase extends TestCase
{
    protected EntityManagerInterface $em;

    protected QueryLogger $queryLogger;

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

    protected function createQueryBuilder(string $resourceClass = IntegrationArticle::class): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('o')
            ->from($resourceClass, 'o');
    }

    /**
     * A Translator resolving the given locale from a `?locale=` request.
     */
    protected function createTranslator(string $locale = 'de'): Translator
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create(\sprintf('/?locale=%s', $locale)));

        return new Translator($this->createStub(LocaleAwareTranslatorInterface::class), $requestStack, 'en');
    }

    protected function createManagerRegistry(): ManagerRegistry
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($this->em);

        return $managerRegistry;
    }

    /**
     * Paginates the query the way API Platform does for a collection
     * operation: its PaginationExtension inspects the query and decides
     * between a plain LIMIT query and Doctrine's fetch-join Paginator.
     *
     * @return Paginator<IntegrationArticle>
     */
    protected function paginate(QueryBuilder $queryBuilder, int $itemsPerPage = 30): Paginator
    {
        $operation = new GetCollection(class: IntegrationArticle::class, paginationItemsPerPage: $itemsPerPage);
        $extension = new PaginationExtension($this->createManagerRegistry(), new Pagination());
        $extension->applyToCollection($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, $operation);

        $paginator = $extension->getResult($queryBuilder, IntegrationArticle::class, $operation);
        self::assertInstanceOf(Paginator::class, $paginator);

        return $paginator;
    }

    /**
     * @param array<string, string> $localeToTitle
     */
    protected function seedArticle(array $localeToTitle): void
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

    /**
     * @param list<IntegrationArticle> $articles
     *
     * @return list<string|null>
     */
    protected function titlesIn(array $articles, string $locale): array
    {
        return array_map(
            static fn (IntegrationArticle $article): ?string => $article->getTranslation($locale)->getTitle(),
            $articles,
        );
    }
}
