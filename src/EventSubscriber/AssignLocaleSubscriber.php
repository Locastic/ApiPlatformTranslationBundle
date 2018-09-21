<?php

namespace Locastic\ApiPlatformTranslationBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Service\Translator;

/**
 * Class AssignLocaleSubscriber
 *
 * @package Locastic\ApiPlatformTranslationBundle\EventSubscriber
 */
class AssignLocaleSubscriber implements EventSubscriber
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * AssignLocaleSubscriber constructor.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad,
            Events::prePersist,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $this->assignLocale($args);
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->assignLocale($args);
    }

    private function assignLocale(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $localeCode = $this->translator->loadCurrentLocale();

        $entity->setCurrentLocale($localeCode);
        $entity->setFallbackLocale($localeCode);
    }
}
