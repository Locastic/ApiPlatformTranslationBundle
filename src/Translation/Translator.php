<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Translation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Translation
 */
class Translator implements TranslatorInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private string $defaultLocale
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        if ($locale === null) {
            $locale = $this->loadCurrentLocale();
        }

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * @codeCoverageIgnore
     */
    public function setLocale($locale = 'en'): void
    {
        $this->translator->setLocale($locale);
    }

    public function loadCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $this->defaultLocale;
        }

        $localeCode = $request->query->get('locale');

        if ($localeCode) {
            return $localeCode;
        }

        $preferredLanguage = $request->getPreferredLanguage();

        return empty($preferredLanguage) ? $this->defaultLocale : $preferredLanguage;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
