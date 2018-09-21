<?php

namespace Locastic\ApiPlatformTranslationBundle\Tests\EventSubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Locastic\ApiPlatformTranslationBundle\EventSubscriber\AssignLocaleSubscriber;
use Locastic\ApiPlatformTranslationBundle\Service\Translator;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslation;
use PHPUnit\Framework\TestCase;

/**
 * Class AssignLocaleSubscriberTest
 *
 * @package Locastic\ApiPlatformTranslationBundle\Tests\EventSubscriber
 */
class AssignLocaleSubscriberTest extends TestCase
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @test postLoad
     * @dataProvider provideTranslatableEntities
     */
    public function testPostLoad($entity)
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getEntityInfo($args, $entity);
        $this->loadCurrentLocale();

        $assignLocaleSubscriber = new AssignLocaleSubscriber($this->translator);
        $assignLocaleSubscriber->postLoad($args);
    }

    /**
     * @test postLoad
     * @dataProvider provideNonTranslatableEntities
     */
    public function testPostLoadNonTranslatableEntities($entity)
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getEntityInfo($args, $entity);

        $assignLocaleSubscriber = new AssignLocaleSubscriber($this->translator);
        $assignLocaleSubscriber->postLoad($args);
    }

    /**
     * @test postLoad
     * @dataProvider provideTranslatableEntities
     */
    public function testPrePersist($entity)
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getEntityInfo($args, $entity);
        $this->loadCurrentLocale();

        $assignLocaleSubscriber = new AssignLocaleSubscriber($this->translator);
        $assignLocaleSubscriber->prePersist($args);
    }

    /**
     * @test postLoad
     * @dataProvider provideNonTranslatableEntities
     */
    public function testPrePersistNonTranslatableEntities($entity)
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getEntityInfo($args, $entity);

        $assignLocaleSubscriber = new AssignLocaleSubscriber($this->translator);
        $assignLocaleSubscriber->postLoad($args);
    }

    /**
     * @param $args
     * @param $entity
     */
    private function getEntityInfo($args, $entity)
    {
        $args
            ->expects($this->once())
            ->method('getEntity')
            ->willReturn($entity);
    }

    private function loadCurrentLocale()
    {
        $this->translator
            ->expects($this->once())
            ->method('loadCurrentLocale')
            ->willReturn($this->defaultLocale);
    }

    /**
     * setUp
     */
    protected function setUp()
    {
        $this->translator = $this->createMock(Translator::class);
        $this->defaultLocale = 'en';
    }

    /**
     * @return \Generator
     */
    public function provideTranslatableEntities()
    {
        yield[new DummyTranslatable()];
    }

    /**
     * @return \Generator
     */
    public function provideNonTranslatableEntities()
    {
        yield[new DummyNotTranslatable()];
        yield[new DummyTranslation()];
    }
}
