<?php

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation;

/**
 * Class DummyTranslation
 *
 * @package Locastic\ApiPlatformTranslationBundle\Tests\Fixtures
 */
class DummyTranslation extends AbstractTranslation
{
    /**
     * @var string
     */
    private $translation;

    /**
     * Set translation
     *
     * @param string $translation
     *
     * @return DummyTranslation
     */
    public function setTranslation($translation): DummyTranslation
    {
        $this->translation = $translation;

        return $this;
    }

    /**
     * Get translation
     *
     * @return string
     */
    public function getTranslation():?string
    {
        return $this->translation;
    }
}
