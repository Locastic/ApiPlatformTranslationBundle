<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Model;

use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslation;
use PHPUnit\Framework\TestCase;

/**
 * Class TranslatableTraitTest
 * @package Tests\IntegrationTests\TranslationBundle\Model
 */
class TranslatableTraitTest extends TestCase
{
    /**
     * @test getTranslationLocales
     */
    public function testGetTranslationLocales(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $this->setTranslation('es', 'espanol', $dummyTranslatable);
        $this->setTranslation('en', 'english', $dummyTranslatable);

        $this->assertEquals(['es', 'en'], $dummyTranslatable->getTranslationLocales());
    }

    /**
     * @test getTranslation
     */
    public function testGetTranslationFromTranslationsCache(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $dummyTranslatable->addTranslation($this->setTranslation('en', 'english', $dummyTranslatable));
        $this->assertEquals('english', $dummyTranslatable->getTranslation('en')->getTranslation());
    }

    /**
     * @test getTranslation
     */
    public function testGetTranslationWithoutFallbackLocale(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $this->assertEquals(null, $dummyTranslatable->getTranslation('it')->getTranslation());
    }

    /**
     * @test getTranslation
     */
    public function testGetTranslationWithFallbackTranslation(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $dummyTranslatable->addTranslation($this->setTranslation('en', 'english', $dummyTranslatable));
        $this->assertEquals('english', $dummyTranslatable->getTranslation('it')->getTranslation());
    }

    /**
     * @test getTranslation
     */
    public function testRemoveTranslation(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $dummyTranslationEnglish = $this->setTranslation('en', 'english', $dummyTranslatable);

        $dummyTranslatable->removeTranslation($dummyTranslationEnglish);
        $this->assertEquals(null, $dummyTranslatable->getTranslation('en')->getTranslation());
    }

    /**
     * @test getTranslation
     */
    public function testGetTranslationWithoutLocales(): void
    {
        $dummyTranslatable = $this->setTranslatable(null, null);

        $this->expectException(\RuntimeException::class);
        $dummyTranslatable->getTranslation();
    }

    private function setTranslation(string $locale, string $translation, TranslatableInterface $translatable): DummyTranslation
    {
        $dummyTranslation = new DummyTranslation();
        $dummyTranslation->setLocale($locale);
        $dummyTranslation->setTranslation($translation);
        $dummyTranslation->setTranslatable($translatable);

        return $dummyTranslation;
    }

    /**
     * @param $currentLocale
     * @param $fallbackLocale
     * @return DummyTranslatable
     */
    private function setTranslatable($currentLocale, $fallbackLocale): DummyTranslatable
    {
        $dummyTranslatable = new DummyTranslatable();
        $dummyTranslatable->setCurrentLocale($currentLocale);
        $dummyTranslatable->setFallbackLocale($fallbackLocale);

        return $dummyTranslatable;
    }
}
