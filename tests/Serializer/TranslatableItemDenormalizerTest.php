<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Serializer;

use ApiPlatform\Metadata\Put;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Serializer\TranslatableItemDenormalizer;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class TranslatableItemDenormalizerTest extends TestCase
{
    private TranslatableItemDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new TranslatableItemDenormalizer();
    }

    public function testSupportsOnlyTranslatablePayloadsCarryingTranslations(): void
    {
        self::assertTrue($this->denormalizer->supportsDenormalization(['translations' => []], DummyTranslatable::class));
        self::assertFalse($this->denormalizer->supportsDenormalization(['translations' => []], DummyNotTranslatable::class));
        self::assertFalse($this->denormalizer->supportsDenormalization(['title' => 'x'], DummyTranslatable::class));
        // Re-entrant guard: the delegated parent call must not be intercepted again.
        self::assertFalse($this->denormalizer->supportsDenormalization(
            ['translations' => []],
            DummyTranslatable::class,
            null,
            ['LOCASTIC_TRANSLATABLE_DENORMALIZER_CALLED' => true],
        ));
    }

    public function testPatchPopulatesExistingTranslationInPlaceAndKeepsAbsentLocales(): void
    {
        $translatable = $this->translatableWith(['en' => 'EN', 'de' => 'DE']);
        $en = $translatable->getTranslations()->get('en');
        $de = $translatable->getTranslations()->get('de');
        $this->denormalizer->setDenormalizer($this->innerReturning($translatable));

        $result = $this->denormalizer->denormalize(
            ['translations' => ['en' => ['translation' => 'EN-edit']]],
            DummyTranslatable::class,
            null,
            ['operation' => $this->operation('PATCH')],
        );

        self::assertSame($translatable, $result);
        self::assertSame(['en', 'de'], array_keys($result->getTranslations()->toArray()));
        self::assertSame($en, $result->getTranslations()->get('en'), 'existing translation updated in place');
        self::assertSame('EN-edit', $en->getTranslation());
        self::assertSame($de, $result->getTranslations()->get('de'), 'absent locale left untouched');
    }

    public function testPatchCreatesTranslationForNewLocale(): void
    {
        $translatable = $this->translatableWith(['en' => 'EN']);
        $this->denormalizer->setDenormalizer($this->innerReturning($translatable));

        $result = $this->denormalizer->denormalize(
            ['translations' => ['de' => ['translation' => 'DE-new']]],
            DummyTranslatable::class,
            null,
            ['operation' => $this->operation('PATCH')],
        );

        self::assertEqualsCanonicalizing(['en', 'de'], array_keys($result->getTranslations()->toArray()));
        self::assertSame('de', $result->getTranslations()->get('de')->getLocale());
        self::assertSame('DE-new', $result->getTranslations()->get('de')->getTranslation());
    }

    public function testPutRemovesLocalesAbsentFromPayload(): void
    {
        $translatable = $this->translatableWith(['en' => 'EN', 'de' => 'DE']);
        $this->denormalizer->setDenormalizer($this->innerReturning($translatable));

        $result = $this->denormalizer->denormalize(
            ['translations' => ['en' => ['translation' => 'EN-put']]],
            DummyTranslatable::class,
            null,
            ['operation' => $this->operation('PUT')],
        );

        self::assertSame(['en'], array_keys($result->getTranslations()->toArray()));
        self::assertSame('EN-put', $result->getTranslations()->get('en')->getTranslation());
    }

    public function testPutReplacesCollectionToMatchPayload(): void
    {
        $translatable = $this->translatableWith(['en' => 'EN', 'de' => 'DE']);
        $this->denormalizer->setDenormalizer($this->innerReturning($translatable));

        $result = $this->denormalizer->denormalize(
            ['translations' => ['en' => ['translation' => 'EN'], 'hr' => ['translation' => 'HR']]],
            DummyTranslatable::class,
            null,
            ['operation' => $this->operation('PUT')],
        );

        self::assertEqualsCanonicalizing(['en', 'hr'], array_keys($result->getTranslations()->toArray()));
    }

    public function testStandardPutWithoutObjectToPopulateFailsLoudly(): void
    {
        $this->denormalizer->setDenormalizer($this->innerReturning($this->translatableWith([])));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('standard_put');

        // Real Put metadata without extraProperties: standard_put defaults to on, and
        // API Platform then deserializes into a fresh object (no OBJECT_TO_POPULATE).
        $this->denormalizer->denormalize(
            ['translations' => ['en' => ['translation' => 'EN']]],
            DummyTranslatable::class,
            null,
            ['operation' => new Put()],
        );
    }

    public function testPutWithStandardPutDisabledIsAccepted(): void
    {
        $translatable = $this->translatableWith(['en' => 'EN', 'de' => 'DE']);
        $this->denormalizer->setDenormalizer($this->innerReturning($translatable));

        $result = $this->denormalizer->denormalize(
            ['translations' => ['en' => ['translation' => 'EN-put']]],
            DummyTranslatable::class,
            null,
            ['operation' => new Put(extraProperties: ['standard_put' => false])],
        );

        self::assertSame(['en'], array_keys($result->getTranslations()->toArray()));
    }

    public function testResolvesLocaleFromItemWhenTranslationsAreAList(): void
    {
        $translatable = $this->translatableWith([]);
        $this->denormalizer->setDenormalizer($this->innerReturning($translatable));

        $result = $this->denormalizer->denormalize(
            ['translations' => [
                ['locale' => 'en', 'translation' => 'EN'],
                ['locale' => 'de', 'translation' => 'DE'],
            ]],
            DummyTranslatable::class,
        );

        self::assertEqualsCanonicalizing(['en', 'de'], array_keys($result->getTranslations()->toArray()));
    }

    /**
     * @param array<string, string> $localeToText
     */
    private function translatableWith(array $localeToText): DummyTranslatable
    {
        $translatable = new DummyTranslatable();
        foreach ($localeToText as $locale => $text) {
            $translation = new DummyTranslation();
            $translation->setLocale($locale);
            $translation->setTranslation($text);
            $translatable->addTranslation($translation);
        }

        return $translatable;
    }

    /**
     * Inner denormalizer double: returns the prepared translatable for the parent call,
     * and populates the object_to_populate for each nested translation call.
     */
    private function innerReturning(DummyTranslatable $translatable): DenormalizerInterface
    {
        $inner = $this->createMock(DenormalizerInterface::class);
        $inner->method('denormalize')->willReturnCallback(
            static function (mixed $data, string $type, ?string $format = null, array $context = []) use ($translatable): object {
                if (is_a($type, TranslatableInterface::class, true)) {
                    return $translatable;
                }

                $target = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
                if ($target instanceof DummyTranslation && \is_array($data) && isset($data['translation'])) {
                    $target->setTranslation($data['translation']);
                }

                return $target;
            }
        );

        return $inner;
    }

    private function operation(string $method): object
    {
        return new class($method) {
            public function __construct(private string $method)
            {
            }

            public function getMethod(): string
            {
                return $this->method;
            }
        };
    }
}
