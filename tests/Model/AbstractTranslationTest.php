<?php

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
    public function testSetTranslatable()
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
    public function testGetCurrentTranslationWithoutLocaleParameter()
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');
        $dummyTranslationEspanol = $this->setTranslation('es', 'espanol', $dummyTranslatable);
        $this->setTranslation('en', 'english', $dummyTranslatable);

        $this->assertSame($dummyTranslationEspanol, $dummyTranslatable->getTranslation());
    }

    /**
     * @test setTranslatable
     */
    public function testGetFallbackTranslationWithoutLocaleParameter()
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');

        $dummyTranslationEnglish = $this->setTranslation('en', 'english', $dummyTranslatable);

        $this->assertSame($dummyTranslationEnglish, $dummyTranslatable->getTranslation());
    }

    /**
     * @test setTranslatable
     */
    public function testChangeTranslation()
    {
        $dummyTranslatable = $this->setTranslatable('es', 'en');

        $dummyTranslationEnglish = $this->setTranslation('en', 'english', $dummyTranslatable);
        $this->assertSame($dummyTranslationEnglish, $dummyTranslatable->getTranslation('en'));

        $dummyTranslationEnglish->setTranslation('english2');
        $this->assertSame('english2', $dummyTranslatable->getTranslation('en')->getTranslation());
    }

    /**
     * @param $locale
     * @param $translation
     * @param TranslatableInterface $translatable
     * @return DummyTranslation
     */
    private function setTranslation($locale, $translation, TranslatableInterface $translatable)
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
    private function setTranslatable($currentLocale, $fallbackLocale)
    {
        $dummyTranslatable = new DummyTranslatable();
        $dummyTranslatable->setCurrentLocale($currentLocale);
        $dummyTranslatable->setFallbackLocale($fallbackLocale);

        return $dummyTranslatable;
    }
}
