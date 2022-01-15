<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
abstract class AbstractTranslatable implements TranslatableInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
    }

    /**
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }
}
