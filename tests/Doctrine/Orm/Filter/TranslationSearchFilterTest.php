<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Extension\FilterEagerLoadingExtension;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension\TranslationsEagerLoadingExtension;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationOrderFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationSearchFilter;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticleTranslation;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationTag;

final class TranslationSearchFilterTest extends TranslationFilterTestCase
{
    public function testFiltersByTheCurrentLocaleTranslationOnly(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => 'exact'])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => 'A cat DE']]);

        // a subquery on the current-locale translation, no to-many join
        self::assertSame(
            \sprintf(
                'SELECT o FROM %s o WHERE EXISTS (SELECT translations_a1 FROM %s translations_a1 WHERE IDENTITY(translations_a1.translatable) = o.id AND translations_a1.locale = :locale_p1 AND (translations_a1.title = :title_p2))',
                IntegrationArticle::class,
                IntegrationArticleTranslation::class,
            ),
            $queryBuilder->getDQL(),
        );
        self::assertSame(['A cat DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
    }

    public function testDoesNotMatchTranslationsOfOtherLocales(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => 'exact'])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => 'A cat EN']]);

        self::assertSame([], $queryBuilder->getQuery()->getResult());
    }

    /**
     * @dataProvider provideStrategies
     *
     * @param list<string> $expectedTitles
     */
    public function testMatchesAccordingToTheStrategy(string $strategy, string $value, array $expectedTitles): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);
        $this->seedArticle(['en' => 'catalog EN', 'de' => 'catalog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => $strategy])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => $value]]);

        $titles = $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de');
        sort($titles);
        self::assertSame($expectedTitles, $titles);
    }

    /**
     * The case-sensitive cases use values in the stored case, so the expected
     * results hold on every database (sqlite's LIKE ignores ASCII case, others
     * do not).
     *
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideStrategies(): iterable
    {
        yield 'partial' => ['partial', 'cat', ['A cat DE', 'catalog DE']];
        yield 'start' => ['start', 'cat', ['catalog DE']];
        yield 'end' => ['end', 'dog DE', ['A dog DE']];
        yield 'word_start' => ['word_start', 'cat', ['A cat DE', 'catalog DE']];
        yield 'iexact' => ['iexact', 'a CAT de', ['A cat DE']];
        yield 'ipartial' => ['ipartial', 'CAT', ['A cat DE', 'catalog DE']];
    }

    /**
     * @dataProvider provideWildcardValues
     *
     * @param list<string> $expectedTitles
     */
    public function testLikeWildcardsInTheValueAreMatchedLiterally(string $strategy, string $value, array $expectedTitles): void
    {
        $this->seedArticle(['de' => '50% off']);
        $this->seedArticle(['de' => '500 offers']);
        $this->seedArticle(['de' => 'snake_case']);
        $this->seedArticle(['de' => 'snakeXcase']);
        $this->seedArticle(['de' => 'wow! really']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => $strategy])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => $value]]);

        $titles = $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de');
        sort($titles);
        self::assertSame($expectedTitles, $titles);
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideWildcardValues(): iterable
    {
        yield 'percent' => ['partial', '50%', ['50% off']];
        yield 'percent at start' => ['start', '50% o', ['50% off']];
        yield 'underscore' => ['ipartial', 'snake_c', ['snake_case']];
        yield 'underscore at end' => ['end', '_case', ['snake_case']];
        yield 'escape character' => ['partial', 'wow!', ['wow! really']];
        yield 'word start' => ['word_start', 'r%', []];
    }

    public function testMultipleExactValuesBecomeAnInClause(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);
        $this->seedArticle(['en' => 'A fox EN', 'de' => 'A fox DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => 'exact'])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => ['A cat DE', 'A fox DE']]]);

        self::assertStringContainsString('IN(:title_p2)', $queryBuilder->getDQL());

        $titles = $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de');
        sort($titles);
        self::assertSame(['A cat DE', 'A fox DE'], $titles);
    }

    public function testEnablesEveryTranslatedFieldWhenNoPropertiesAreConfigured(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(null)
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => 'A cat DE']]);

        self::assertSame(['A cat DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
    }

    public function testIgnoresPropertiesThatAreNotTranslatedFields(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $dql = $queryBuilder->getDQL();

        $this->createFilter(null)->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, [
            'filters' => ['id' => '1', 'locale' => 'de', 'unknown' => 'x', 'page' => '1'],
        ]);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function testIgnoresNonTranslatableResources(): void
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->select('o')
            ->from(DummyNotTranslatable::class, 'o');
        $dql = $queryBuilder->getDQL();

        $this->createFilter(null)
            ->apply($queryBuilder, new QueryNameGenerator(), DummyNotTranslatable::class, null, ['filters' => ['name' => 'foo']]);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function testCombinesWithTheOrderFilter(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'Zulu cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);
        $this->seedArticle(['en' => 'catalog EN', 'de' => 'Alpha cat DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();

        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['title' => 'cat']]);

        $orderFilter = new TranslationOrderFilter($this->createTranslator(), $this->createManagerRegistry(), null, ['title' => null]);
        $orderFilter->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);

        self::assertStringNotContainsString('JOIN o.translations', $queryBuilder->getDQL());
        self::assertSame(['Alpha cat DE', 'Zulu cat DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
    }

    /**
     * API Platform moves the WHERE clause into an IN subquery with new
     * aliases as soon as the query has a join (FilterEagerLoadingExtension);
     * the EXISTS subquery must survive that rewrite.
     */
    public function testSurvivesTheWhereRewriteOfApiPlatform(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'Zulu cat DE']);
        $this->seedArticle(['en' => 'cat EN', 'de' => 'A dog DE']);
        $this->seedArticle(['en' => 'catalog EN', 'de' => 'Alpha cat DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();

        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['title' => 'cat']]);
        $orderFilter = new TranslationOrderFilter($this->createTranslator(), $this->createManagerRegistry(), null, ['title' => null]);
        $orderFilter->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'desc']]]);

        (new FilterEagerLoadingExtension())->applyToCollection($queryBuilder, $queryNameGenerator, IntegrationArticle::class);

        // the subquery is correlated with the re-aliased root of the IN subquery
        self::assertStringContainsString('WHERE o IN(SELECT o_a3 FROM', $queryBuilder->getDQL());
        self::assertStringContainsString('IDENTITY(translations_a1.translatable) = o_a3.id', $queryBuilder->getDQL());
        self::assertSame(['Zulu cat DE', 'Alpha cat DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
    }

    public function testMultiplePropertiesMustAllMatch(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A dog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();
        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['title' => 'cat']]);
        $this->createFilter(['title' => 'end'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['title' => 'DE']]);

        self::assertSame(['A cat DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
    }

    /**
     * The default setup: the eager-loading extension fetch-joins the
     * translations after the filter. The fetched collections are complete,
     * the search does not restrict them to the locale searched in.
     */
    public function testSearchesNextToTheFetchJoinOfTheEagerLoadingExtension(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);
        $this->seedArticle(['en' => 'catalog EN', 'de' => 'catalog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();
        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['title' => 'cat']]);
        // the order API Platform applies its extensions in
        (new FilterEagerLoadingExtension())->applyToCollection($queryBuilder, $queryNameGenerator, IntegrationArticle::class);
        (new TranslationsEagerLoadingExtension())->applyToCollection($queryBuilder, $queryNameGenerator, IntegrationArticle::class);

        $this->queryLogger->reset();
        $paginator = $this->paginate($queryBuilder);
        /** @var list<IntegrationArticle> $articles */
        $articles = iterator_to_array($paginator, false);

        self::assertSame(2.0, $paginator->getTotalItems());
        // identifiers of the page, rows of the page, count
        self::assertCount(3, $this->queryLogger->queries);
        self::assertCount(2, $articles[0]->getTranslations());
        self::assertSame(['A cat DE', 'catalog DE'], $this->titlesIn($articles, 'de'));
    }

    /**
     * A unidirectional `translations` association gives the translation no
     * field a subquery can be tied to the resource with: the filter keeps the
     * association join it used before.
     */
    public function testFallsBackToTheAssociationJoinWithoutAFieldPointingBack(): void
    {
        $this->seedTag(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedTag(['en' => 'A cat EN', 'de' => 'A dog DE']);
        $this->seedTag(['en' => 'A cat EN', 'de' => 'A cat XX']);
        $this->seedTag(['en' => 'A cat EN', 'de' => 'A bird DE']);

        $queryBuilder = $this->createQueryBuilder(IntegrationTag::class);
        $queryNameGenerator = new QueryNameGenerator();
        // (cat OR dog) AND ends with DE
        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationTag::class, null, ['filters' => ['title' => ['cat', 'dog']]]);
        $this->createFilter(['title' => 'end'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationTag::class, null, ['filters' => ['title' => 'DE']]);

        $dql = $queryBuilder->getDQL();
        self::assertStringNotContainsString('EXISTS', $dql);
        // both filters share the one association join
        self::assertSame(1, substr_count($dql, 'LEFT JOIN o.translations translations_a1 WITH translations_a1.locale = :locale_p1'));
        self::assertSame(1, substr_count($dql, 'JOIN'));
        /** @var list<IntegrationTag> $tags */
        $tags = $queryBuilder->getQuery()->getResult();
        self::assertSame(['A cat DE', 'A dog DE'], $this->titlesIn($tags, 'de'));
    }

    /**
     * The filter alone adds no join at all. Without the fetch join of the
     * eager-loading extension (`eager_load_translations: false`), API
     * Platform paginates with a plain LIMIT query and a plain count.
     */
    public function testPaginatesWithoutTheFetchJoinPaginator(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);
        $this->seedArticle(['en' => 'catalog EN', 'de' => 'catalog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => 'cat']]);

        $this->queryLogger->reset();
        $paginator = $this->paginate($queryBuilder);

        $articles = iterator_to_array($paginator, false);
        self::assertSame(2.0, $paginator->getTotalItems());
        // one page query and one count query
        self::assertCount(2, $this->queryLogger->queries);
        self::assertStringNotContainsString('DISTINCT', implode("\n", $this->queryLogger->queries));
        self::assertSame(['A cat DE', 'catalog DE'], $this->titlesIn($articles, 'de'));
    }

    public function testDescriptionListsConfiguredPropertiesWithTheirStrategy(): void
    {
        $description = $this->createFilter(['title' => 'ipartial'])->getDescription(IntegrationArticle::class);

        self::assertSame([
            'title' => [
                'property' => 'title',
                'type' => 'string',
                'required' => false,
                'strategy' => 'ipartial',
                'is_collection' => false,
            ],
            'title[]' => [
                'property' => 'title',
                'type' => 'string',
                'required' => false,
                'strategy' => 'ipartial',
                'is_collection' => true,
            ],
        ], $description);
    }

    public function testDescriptionDefaultsToEveryTranslatedFieldExceptLocaleAndId(): void
    {
        $description = $this->createFilter(null)->getDescription(IntegrationArticle::class);

        self::assertSame(['title', 'title[]'], array_keys($description));
        self::assertSame('exact', $description['title']['strategy']);
    }

    public function testDescriptionIsEmptyForNonTranslatableResources(): void
    {
        self::assertSame([], $this->createFilter(null)->getDescription(DummyNotTranslatable::class));
    }

    /**
     * @param array<string, string|null>|null $properties
     */
    private function createFilter(?array $properties, string $locale = 'de'): TranslationSearchFilter
    {
        return new TranslationSearchFilter($this->createTranslator($locale), $this->createManagerRegistry(), null, $properties);
    }
}
