<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;

class AssignLocaleListener
{
    public function __construct(
        private Translator $translator,
        private string $defaultLocale = 'en',
    ) {
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $this->assignLocale($args);
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->assignLocale($args);
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    private function assignLocale(LifecycleEventArgs $args): void
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
