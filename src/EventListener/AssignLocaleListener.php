<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\EventListener;

use Doctrine\Common\EventArgs;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;

/**
 * @package Locastic\ApiPlatformTranslationBundle\EventListener
 */
class AssignLocaleListener
{
    public function __construct(
        private Translator $translator,
        private string $defaultLocale = 'en'
    ) {
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
