<?php

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * Class AbstractTranslatable
 *
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
abstract class AbstractTranslatable implements TranslatableInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
    }

    /**
     * AbstractTranslatable constructor.
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

    abstract protected function createTranslation();
}
