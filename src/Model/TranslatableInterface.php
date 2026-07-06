<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\Collection;

/**
 * @template T of TranslationInterface
 */
interface TranslatableInterface
{
    /**
     * @return Collection<string, TranslationInterface>
     *
     * @psalm-return Collection<string, T>
     */
    public function getTranslations(): Collection;

    /**
     * @psalm-return T
     */
    public function getTranslation(?string $locale = null): TranslationInterface;

    /**
     * @psalm-param T $translation
     */
    public function hasTranslation(TranslationInterface $translation): bool;

    /**
     * @psalm-param T $translation
     */
    public function addTranslation(TranslationInterface $translation): void;

    /**
     * @psalm-param T $translation
     */
    public function removeTranslation(TranslationInterface $translation): void;

    public function setCurrentLocale(?string $locale): void;

    public function setFallbackLocale(?string $locale): void;
}
