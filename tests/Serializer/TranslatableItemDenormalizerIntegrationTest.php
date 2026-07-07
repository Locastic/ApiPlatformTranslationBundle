<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Serializer;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Locastic\ApiPlatformTranslationBundle\Serializer\TranslatableItemDenormalizer;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticle;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm\IntegrationArticleTranslation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Exercises the denormalizer against a real Doctrine EntityManager (in-memory sqlite)
 * and the real Symfony Serializer, so the assertions run against hydrated
 * PersistentCollections and actual database round-trips.
 *
 * This is what unit tests with a mocked inner denormalizer cannot prove: in-place
 * updates keyed by locale on a NON locale-indexed collection, orphan removal on PUT,
 * and that the map key (not the request body) decides the locale.
 */
final class TranslatableItemDenormalizerIntegrationTest extends TestCase
{
    private EntityManagerInterface $em;
    private Serializer $serializer;

    protected function setUp(): void
    {
        // Use the long-standing `...Configuration` name: the shorter `...Config`
        // alias was only added mid-3.x, and the ORM floor (installed on the
        // lowest-dependencies CI leg) does not have it.
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../Fixtures/Orm'], true);
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

        // Real serializer chain: the SUT (priority first), then the generic object
        // denormalizer that builds/populates the translatable and each translation.
        // The Serializer wires itself into the DenormalizerAware SUT automatically.
        $this->serializer = new Serializer([
            new TranslatableItemDenormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(),
        ]);
    }

    public function testPatchMergesInPlaceKeepingIdsAndAbsentLocales(): void
    {
        $id = $this->seedArticle(['en' => 'EN', 'de' => 'DE']);
        [$enId, $deId] = [$this->translationId($id, 'en'), $this->translationId($id, 'de')];

        $article = $this->em->find(IntegrationArticle::class, $id);
        $this->serializer->denormalize(
            ['translations' => ['en' => ['title' => 'EN-edit']]],
            IntegrationArticle::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $article,
                'operation' => new Patch(),
            ],
        );
        $this->em->flush();
        $this->em->clear();

        $rows = $this->translations($id);
        self::assertSame(['de', 'en'], array_keys($rows), 'both locales survive a PATCH');
        self::assertSame('EN-edit', $rows['en']->getTitle(), 'submitted locale is updated');
        self::assertSame('DE', $rows['de']->getTitle(), 'absent locale is left untouched');
        self::assertSame($enId, $rows['en']->getId(), 'en row updated in place, id stable');
        self::assertSame($deId, $rows['de']->getId(), 'de row untouched, id stable');
    }

    public function testPatchCreatesNewLocaleLeavingExistingAlone(): void
    {
        $id = $this->seedArticle(['en' => 'EN']);
        $enId = $this->translationId($id, 'en');

        $article = $this->em->find(IntegrationArticle::class, $id);
        $this->serializer->denormalize(
            ['translations' => ['de' => ['title' => 'DE-new']]],
            IntegrationArticle::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $article,
                'operation' => new Patch(),
            ],
        );
        $this->em->flush();
        $this->em->clear();

        $rows = $this->translations($id);
        self::assertSame(['de', 'en'], array_keys($rows));
        self::assertSame('DE-new', $rows['de']->getTitle());
        self::assertSame($enId, $rows['en']->getId(), 'existing en row is not recreated');
    }

    public function testPutRemovesAbsentLocalesFromDatabase(): void
    {
        $id = $this->seedArticle(['en' => 'EN', 'de' => 'DE']);
        $enId = $this->translationId($id, 'en');

        $article = $this->em->find(IntegrationArticle::class, $id);
        $this->serializer->denormalize(
            ['translations' => ['en' => ['title' => 'EN-put']]],
            IntegrationArticle::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $article,
                'operation' => new Put(),
            ],
        );
        $this->em->flush();
        $this->em->clear();

        $rows = $this->translations($id);
        self::assertSame(['en'], array_keys($rows), 'PUT drops locales absent from the payload');
        self::assertSame('EN-put', $rows['en']->getTitle());
        self::assertSame($enId, $rows['en']->getId(), 'kept locale is still updated in place');
        self::assertSame(1, $this->countTranslationRows(), 'de row is deleted via orphanRemoval');
    }

    public function testMapKeyWinsOverBodyLocale(): void
    {
        $id = $this->seedArticle(['en' => 'EN', 'de' => 'DE']);
        $enId = $this->translationId($id, 'en');

        // A conflicting `locale` in the body must not relabel the row addressed by the map key.
        $article = $this->em->find(IntegrationArticle::class, $id);
        $this->serializer->denormalize(
            ['translations' => ['en' => ['locale' => 'de', 'title' => 'EN-edit']]],
            IntegrationArticle::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $article,
                'operation' => new Patch(),
            ],
        );
        $this->em->flush();
        $this->em->clear();

        $rows = $this->translations($id);
        self::assertSame(['de', 'en'], array_keys($rows), 'no locale was relabeled or duplicated');
        self::assertSame($enId, $rows['en']->getId(), 'the en row is edited in place');
        self::assertSame('EN-edit', $rows['en']->getTitle());
        self::assertSame('en', $rows['en']->getLocale(), 'body locale is ignored; map key is authoritative');
        self::assertSame('DE', $rows['de']->getTitle(), 'de row is untouched');
    }

    /**
     * @param array<string, string> $localeToTitle
     *
     * @return int the persisted article id
     */
    private function seedArticle(array $localeToTitle): int
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

        return $article->getId();
    }

    /**
     * @return array<string, IntegrationArticleTranslation> translations keyed and sorted by locale
     */
    private function translations(int $articleId): array
    {
        $article = $this->em->find(IntegrationArticle::class, $articleId);
        $rows = [];
        foreach ($article->getTranslations() as $translation) {
            $rows[(string) $translation->getLocale()] = $translation;
        }
        ksort($rows);

        return $rows;
    }

    private function translationId(int $articleId, string $locale): int
    {
        return (int) $this->translations($articleId)[$locale]->getId();
    }

    private function countTranslationRows(): int
    {
        return (int) $this->em->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM '.$this->translationTable())
            ->fetchOne();
    }

    private function translationTable(): string
    {
        return $this->em->getClassMetadata(IntegrationArticleTranslation::class)->getTableName();
    }
}
