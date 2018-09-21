<?php

namespace Locastic\ApiPlatformTranslationBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class Translator
 *
 * @package Locastic\ApiPlatformTranslationBundle\Service
 */
class Translator
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
    public function __construct(TranslatorInterface $translator, RequestStack $requestStack, $defaultLocale)
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * @param $string
     * @param array $parameters
     * @param null $domain
     * @param null $locale
     * @return string
     */
    public function translate($string, array $parameters = [], $domain = null, $locale = null)
    {
        if (!$locale) {
            $locale = $this->loadCurrentLocale();
        }

        return $this->translator->trans($string, $parameters, $domain, $locale);
    }

    /**
     * @return mixed|string
     */
    public function loadCurrentLocale()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $this->defaultLocale;
        }

        $localeCode = $request->query->get('locale');

        if (!$localeCode) {
            return $this->defaultLocale;
        }

        return $localeCode;
    }

    /**
     * @param $localeCode
     * @codeCoverageIgnore
     */
    public function setLocale(?string $localeCode = 'en')
    {
        $this->translator->setLocale($localeCode);
    }
}
