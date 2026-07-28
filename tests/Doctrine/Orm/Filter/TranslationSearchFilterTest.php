<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationOrderFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationSearchFilter;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;

final class TranslationSearchFilterTest extends TranslationFilterTestCase
{
    public function testFiltersByTheCurrentLocaleTranslationOnly(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);
        $this->seedArticle(['en' => 'A dog EN', 'de' => 'A dog DE']);

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['title' => 'exact'])
            ->apply($queryBuilder, new QueryNameGenerator(), IntegrationArticle::class, null, ['filters' => ['title' => 'A cat DE']]);

        self::assertStringContainsString('LEFT JOIN o.translations translations_a1 WITH translations_a1.locale = :locale_p1', $queryBuilder->getDQL());
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
        $this->seedArticle(['en' => 'Catalog EN', 'de' => 'Catalog DE']);

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
    public static function provideStrategies(): iterable
    {
        yield 'partial' => ['partial', 'cat', ['A cat DE', 'Catalog DE']];
        yield 'start' => ['start', 'Cat', ['Catalog DE']];
        yield 'end' => ['end', 'dog DE', ['A dog DE']];
        yield 'word_start' => ['word_start', 'cat', ['A cat DE', 'Catalog DE']];
        yield 'iexact' => ['iexact', 'a CAT de', ['A cat DE']];
        yield 'ipartial' => ['ipartial', 'CAT', ['A cat DE', 'Catalog DE']];
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

    public function testSharesTheLocaleJoinWithTheOrderFilter(): void
    {
        $this->seedArticle(['en' => 'A cat EN', 'de' => 'A cat DE']);

        $queryBuilder = $this->createQueryBuilder();
        $queryNameGenerator = new QueryNameGenerator();

        $this->createFilter(['title' => 'partial'])
            ->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['title' => 'cat']]);

        $orderFilter = new TranslationOrderFilter($this->createTranslator(), $this->createManagerRegistry(), null, ['title' => null]);
        $orderFilter->apply($queryBuilder, $queryNameGenerator, IntegrationArticle::class, null, ['filters' => ['order' => ['title' => 'asc']]]);

        self::assertSame(1, substr_count($queryBuilder->getDQL(), 'JOIN o.translations'));
        self::assertSame(['A cat DE'], $this->titlesIn($queryBuilder->getQuery()->getResult(), 'de'));
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
