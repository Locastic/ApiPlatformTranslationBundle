<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Model
 * @template T of TranslationInterface
 * @implements TranslatableInterface<T>
 */
abstract class AbstractTranslatable implements TranslatableInterface
{
    /**
     * @use TranslatableTrait<T>
     */
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
