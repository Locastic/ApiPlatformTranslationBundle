<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * @author Gonzalo Vilaseca <gvilaseca@reiss.co.uk>
 */
interface TranslationInterface
{
    /**
     * @return TranslatableInterface
     */
    public function getTranslatable(): TranslatableInterface;

    /**
     * @param null|TranslatableInterface $translatable
     */
    public function setTranslatable(TranslatableInterface $translatable = null): void;

    /**
     * @return string
     */
    public function getLocale(): ?string;

    /**
     * @param string $locale
     */
    public function setLocale($locale): void;
}
