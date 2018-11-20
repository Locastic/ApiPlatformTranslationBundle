<?php

declare(strict_types=1);

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
     * @var null|string
     */
    private $translation;

    /**
     * Set translation
     *
     * @param string $translation
     *
     * @return DummyTranslation
     */
    public function setTranslation(string $translation): DummyTranslation
    {
        $this->translation = $translation;

        return $this;
    }

    /**
     * Get translation
     *
     * @return null|string
     */
    public function getTranslation(): ?string
    {
        return $this->translation;
    }
}
