<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension\TranslationsEagerLoadingExtension;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationOrderFilter;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticleTranslation;

final class TranslationOrderFilterTest extends TranslationFilterTestCase
{
    /**
     * The German alphabetical order is the reverse of the English one, so an
     * ordering matching the German titles proves the current locale won.
     */
    public function testOrdersByTheCurrentLocaleTranslationValue(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Zulu DE']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Yankee DE']);
        $this->seedArticle(['en' => 'Charlie EN', 'de' => 'Xray DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);

        // a join on the translation entity, not on the to-many association
        self::assertSame(
            \sprintf(
                'SELECT o FROM %s o LEFT JOIN %s translations_a1 WITH IDENTITY(translations_a1.translatable) = o.id AND translations_a1.locale = :locale_p1 ORDER BY translations_a1.title ASC',
                IntegrationArticle::class,
                IntegrationArticleTranslation::class,
            ),
            $queryBuilder->getDQL(),
        );
        self::assertSame(
            ['Xray DE', 'Yankee DE', 'Zulu DE'],
            $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'),
        );
    }

    public function testOrdersDescending(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Xray DE']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Zulu DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'DESC']]]);

        self::assertSame(
            ['Zulu DE', 'Xray DE'],
            $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'),
        );
    }

    public function testKeepsResourcesWithoutATranslationInTheCurrentLocale(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Zulu DE']);
        $this->seedArticle(['en' => 'Untranslated EN']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);

        /** @var list<IntegrationArticle> $articles */
        $articles = $queryBuilder->getQuery()->getResult();

        self::assertCount(2, $articles);
        // sqlite sorts nulls first: the left join must keep the untranslated article.
        self::assertFalse($articles[0]->getTranslations()->exists(
            static fn (mixed $key, TranslationInterface $translation): bool => 'de' === $translation->getLocale(),
        ));
    }

    public function testReusesTheJoinOfAnEarlierOrdering(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Zulu DE']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Xray DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);

        self::assertSame(1, substr_count($queryBuilder->getDQL(), 'LEFT JOIN'));
        self::assertSame(['Xray DE', 'Zulu DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
    }

    public function testJoinsAgainForAnotherLocale(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();
        $this->createFilter(['title' => null], 'de')
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);
        $this->createFilter(['title' => null], 'en')
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);

        self::assertSame(2, substr_count($queryBuilder->getDQL(), 'LEFT JOIN'));
    }

    /**
     * The acceptance case of the join on the translation entity: API Platform
     * does not see a to-many join, so it paginates with a plain LIMIT query
     * and a plain count. With the association join, Doctrine's Paginator
     * wrapped the query and the database sorted every joined row first.
     *
     * @dataProvider provideDirections
     *
     * @param list<string|null> $expectedTitles
     */
    public function testPaginatesWithoutTheFetchJoinPaginator(string $direction, array $expectedTitles): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Zulu DE']);
        $this->seedArticle(['en' => 'Untranslated EN']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Yankee DE']);
        $this->seedArticle(['en' => 'Charlie EN', 'de' => 'Xray DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['order' => ['title' => $direction]]]);

        $this->queryLogger->reset();
        $paginator = $this->paginate($queryBuilder, 3);

        $articles = iterator_to_array($paginator, false);
        // the resource without a German translation is counted and listed
        self::assertSame(4.0, $paginator->getTotalItems());
        // one page query and one count query
        self::assertCount(2, $this->queryLogger->queries);
        $sql = implode("\n", $this->queryLogger->queries);

        $titles = array_map(
            static fn (IntegrationArticle $article): ?string => $article->getTranslations()
                ->findFirst(static fn (mixed $key, TranslationInterface $translation): bool => 'de' === $translation->getLocale())
                ?->getTitle(),
            $articles,
        );
        self::assertSame($expectedTitles, $titles);
        self::assertStringNotContainsString('dctrn_result', $sql);
        self::assertStringNotContainsString('SELECT DISTINCT', $sql);
        self::assertMatchesRegularExpression('/ORDER BY \w+\.title (ASC|DESC) LIMIT 3$/m', $sql);
    }

    /**
     * sqlite sorts nulls first when ascending, last when descending.
     *
     * @return iterable<string, array{string, list<string|null>}>
     */
    public static function provideDirections(): iterable
    {
        yield 'ascending' => ['asc', [null, 'Xray DE', 'Yankee DE']];
        yield 'descending' => ['desc', ['Zulu DE', 'Yankee DE', 'Xray DE']];
    }

    /**
     * A provider that does not load translations in a second step still gets
     * the fetch join of the eager-loading extension; ordering must work with
     * it, through the identifier query of the Paginator.
     */
    public function testOrdersNextToTheFetchJoinOfTheEagerLoadingExtension(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Zulu DE']);
        $this->seedArticle(['en' => 'Untranslated EN']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Yankee DE']);
        $this->seedArticle(['en' => 'Charlie EN', 'de' => 'Xray DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();
        $this->createFilter(['title' => null])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'desc']]]);
        (new TranslationsEagerLoadingExtension())->applyToCollection($queryBuilder, $queryNameGenerator, IntegrationArticle::class);

        $paginator = $this->paginate($queryBuilder, 3);
        /** @var list<IntegrationArticle> $articles */
        $articles = iterator_to_array($paginator, false);

        self::assertSame(['Zulu DE', 'Yankee DE', 'Xray DE'], $this->titlesIn($articles, 'de'));
        self::assertSame(4.0, $paginator->getTotalItems());
        // complete collections, not only the locale ordered by
        self::assertCount(2, $articles[0]->getTranslations());
    }

    public function testAppliesTheConfiguredDefaultDirection(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Xray DE']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Zulu DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => 'DESC'])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['order' => ['title' => '']]]);

        self::assertSame(
            ['Zulu DE', 'Xray DE'],
            $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'),
        );
    }

    /**
     * @dataProvider provideIgnoredOrderings
     *
     * @param array<string, mixed> $filters
     */
    public function testIgnoresOrderingsItCannotApply(array $filters): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $dql = $queryBuilder->getDQL();

        $this->createFilter(['title' => null])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => $filters]);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideIgnoredOrderings(): iterable
    {
        yield 'invalid direction' => [['order' => ['title' => 'sideways']]];
        yield 'empty direction without a default' => [['order' => ['title' => '']]];
        yield 'order parameter is not an array' => [['order' => 'title']];
        yield 'missing order parameter' => [['title' => 'asc']];
        yield 'not a translated field' => [['order' => ['id' => 'asc', 'unknown' => 'asc', 'locale' => 'asc']]];
    }

    public function testIgnoresNonTranslatableResources(): void
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->select('o')
            ->from(DummyNotTranslatable::class, 'o');
        $dql = $queryBuilder->getDQL();

        $this->createFilter(null)
            ->apply($queryBuilder, new QueryNameGenerator(), DummyNotTranslatable::class, null, ['filters' => ['order' => ['name' => 'asc']]]);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function testSupportsACustomOrderParameterName(): void
    {
        $this->seedArticle(['en' => 'Alpha EN', 'de' => 'Xray DE']);
        $this->seedArticle(['en' => 'Bravo EN', 'de' => 'Zulu DE']);

        $filter = new TranslationOrderFilter($this->createTranslator(), $this->createManagerRegistry(), null, ['title' => null], null, 'sort');

        $queryBuilder = $this->createQueryBuilder();
        $filter->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['sort' => ['title' => 'desc']]]);

        self::assertSame(
            ['Zulu DE', 'Xray DE'],
            $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'),
        );
    }

    public function testDescriptionListsConfiguredPropertiesWithTheirDefaultDirection(): void
    {
        $description = $this->createFilter(['title' => 'ASC'])->getDescription(IntegrationArticle::class);

        self::assertSame([
            'order[title]' => [
                'property' => 'title',
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'default' => 'asc',
                    'enum' => ['asc', 'desc'],
                ],
            ],
        ], $description);
    }

    public function testDescriptionDefaultsToEveryTranslatedFieldExceptLocaleAndId(): void
    {
        $description = $this->createFilter(null)->getDescription(IntegrationArticle::class);

        self::assertSame(['order[title]'], array_keys($description));
        self::assertArrayNotHasKey('default', $description['order[title]']['schema']);
    }

    public function testDescriptionIsEmptyForNonTranslatableResources(): void
    {
        self::assertSame([], $this->createFilter(null)->getDescription(DummyNotTranslatable::class));
    }

    /**
     * @param array<string, string|null>|null $properties
     */
    private function createFilter(?array $properties, string $locale = 'de'): TranslationOrderFilter
    {
        return new TranslationOrderFilter($this->createTranslator($locale), $this->createManagerRegistry(), null, $properties);
    }
}
