<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Model;

use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslation;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractTranslationTest
 * @package Tests\IntegrationTests\TranslationBundle\Model
 */
class AbstractTranslationTest extends TestCase
{
    /**
     * @test setTranslatable
     */
    public function testSetTranslatable(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $dummyTranslationEspanol = $this->setTranslation('es', 'espanol', $dummyTranslatable);
        $dummyTranslationEnglish = $this->setTranslation('en', 'english', $dummyTranslatable);

        $this->assertSame($dummyTranslationEspanol, $dummyTranslatable->getTranslation('es'));
        $this->assertSame($dummyTranslationEnglish, $dummyTranslatable->getTranslation('en'));
    }

    /**
     * @test setTranslatable
     */
    public function testGetCurrentTranslationWithoutLocaleParameter(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $dummyTranslationEspanol = $this->setTranslation('es', 'espanol', $dummyTranslatable);
        $this->setTranslation('en', 'english', $dummyTranslatable);

        $this->assertSame($dummyTranslationEspanol, $dummyTranslatable->getTranslation());
    }

    /**
     * @test setTranslatable
     */
    public function testGetFallbackTranslationWithoutLocaleParameter(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');

        $dummyTranslationEnglish = $this->setTranslation('en', 'english', $dummyTranslatable);

        $this->assertSame($dummyTranslationEnglish, $dummyTranslatable->getTranslation());
    }

    /**
     * @test setTranslatable
     */
    public function testChangeTranslation(): void
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');

        $dummyTranslationEnglish = $this->setTranslation('en', 'english', $dummyTranslatable);
        $this->assertSame($dummyTranslationEnglish, $dummyTranslatable->getTranslation('en'));

        $dummyTranslationEnglish->setTranslation('english2');
        $this->assertSame('english2', $dummyTranslatable->getTranslation('en')->getTranslation());
    }

    private function setTranslation(
        string $locale,
        string $translation,
        TranslatableInterface $translatable
    ): DummyTranslation {
        $dummyTranslation = new DummyTranslation('en', null);
        $dummyTranslation->setLocale($locale);
        $dummyTranslation->setTranslation($translation);
        $dummyTranslation->setTranslatable($translatable);

        return $dummyTranslation;
    }

    private function setTranslatable(string $currentLocale, string $fallbackLocale): DummyTranslatable
    {
        $dummyTranslatable = new DummyTranslatable();
        $dummyTranslatable->setCurrentLocale($currentLocale);
        $dummyTranslatable->setFallbackLocale($fallbackLocale);

        return $dummyTranslatable;
    }
}
