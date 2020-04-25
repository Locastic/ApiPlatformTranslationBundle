<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Translation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class Translator
 *
 * @package Locastic\ApiPlatformTranslationBundle\Translation
 */
class Translator implements TranslatorInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * Translator constructor.
     *
     * @param TranslatorInterface $translator
     * @param RequestStack $requestStack
     * @param string $defaultLocale
     */
    public function __construct(TranslatorInterface $translator, RequestStack $requestStack, string $defaultLocale)
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), string $domain = null, string $locale = null): string
    {
        if (!$locale) {
            $locale = $this->loadCurrentLocale();
        }

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * @return string
     */
    public function loadCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $this->defaultLocale;
        }

        $localeCode = $request->query->get('locale');

        if (!$localeCode) {
            $preferredLanguage = $request->getPreferredLanguage();
            $preferredLanguage = '' === $preferredLanguage ? null : $preferredLanguage;

            return $preferredLanguage ?? $this->defaultLocale;
        }

        return $localeCode;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function setLocale($locale = 'en'): void
    {
        $this->translator->setLocale($locale);
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
