<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Interface TranslatableInterface
 *
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
interface TranslatableInterface
{
    /**
     * @return ArrayCollection|TranslationInterface[]
     */
    public function getTranslations();

    /**
     * @param null|string $locale
     *
     * @return TranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface;

    /**
     * @param TranslationInterface $translation
     *
     * @return bool
     */
    public function hasTranslation(TranslationInterface $translation): bool;

    /**
     * @param TranslationInterface $translation
     */
    public function addTranslation(TranslationInterface $translation): void;

    /**
     * @param TranslationInterface $translation
     */
    public function removeTranslation(TranslationInterface $translation): void;

    /**
     * @param null|string $locale
     */
    public function setCurrentLocale(?string $locale): void;

    /**
     * @param null|string $locale
     */
    public function setFallbackLocale(?string $locale): void;
}
