<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\EventListener;

use Doctrine\Common\EventArgs;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;

/**
 * Class AssignLocaleListener
 *
 * @package Locastic\ApiPlatformTranslationBundle\EventListener
 */
class AssignLocaleListener
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var string
     */
    private $defaultLocale;

    public function __construct(Translator $translator, string $defaultLocale = 'en')
    {
        $this->translator = $translator;
        $this->defaultLocale = $defaultLocale;
    }

    public function postLoad(EventArgs $args): void
    {
        $this->assignLocale($args);
    }

    public function prePersist(EventArgs $args): void
    {
        $this->assignLocale($args);
    }

    private function assignLocale(EventArgs $args): void
    {
        $object = $args->getObject();

        if (!$object instanceof TranslatableInterface) {
            return;
        }

        $localeCode = $this->translator->loadCurrentLocale();

        $object->setCurrentLocale($localeCode);
        $object->setFallbackLocale($this->defaultLocale);
    }
}
