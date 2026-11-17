<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationOrderFilter;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;

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

        self::assertStringContainsString('ORDER BY translations_a1.title ASC', $queryBuilder->getDQL());
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
