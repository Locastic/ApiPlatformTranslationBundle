<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\Collection;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Model
 * @template T of TranslationInterface
 */
interface TranslatableInterface
{
    /**
     * @return Collection<string, TranslationInterface>
     * @psalm-return Collection<string, T>
     */
    public function getTranslations(): Collection;

    /**
     * @param string|null $locale
     * @return TranslationInterface
     * @psalm-return T
     */
    public function getTranslation(?string $locale = null): TranslationInterface;

    /**
     * @param TranslationInterface $translation
     * @psalm-param T $translation
     * @return bool
     */
    public function hasTranslation(TranslationInterface $translation): bool;

    /**
     * @param TranslationInterface $translation
     * @psalm-param T $translation
     */
    public function addTranslation(TranslationInterface $translation): void;

    /**
     * @param TranslationInterface $translation
     * @psalm-param T $translation
     */
    public function removeTranslation(TranslationInterface $translation): void;

    public function setCurrentLocale(?string $locale): void;
    public function setFallbackLocale(?string $locale): void;
}
