<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * @author Gonzalo Vilaseca <gvilaseca@reiss.co.uk>
 */
interface TranslationInterface
{
    public function getTranslatable(): ?TranslatableInterface;
    public function setTranslatable(?TranslatableInterface $translatable): void;

    public function getLocale(): string;
    public function setLocale(string $locale): void;
}
