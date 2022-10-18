<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Locastic\ApiPlatformTranslationBundle\EventListener\AssignLocaleListener;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyNotTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslatable;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\DummyTranslation;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use PHPUnit\Framework\TestCase;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Tests\EventListener
 */
class AssignLocaleListenerTest extends TestCase
{
    private Translator|\PHPUnit\Framework\MockObject\MockObject $translator;
    private string $defaultLocale;

    /**
     * @test postLoad
     * @dataProvider provideTranslatableObjects
     */
    public function testPostLoad($object): void
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getObjectInfo($args, $object);
        $this->loadCurrentLocale();

        $assignLocaleSubscriber = new AssignLocaleListener($this->translator);
        $assignLocaleSubscriber->postLoad($args);
    }

    /**
     * @test postLoad
     * @dataProvider provideNonTranslatableObjects
     */
    public function testPostLoadNonTranslatableObjects($object): void
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getObjectInfo($args, $object);

        $assignLocaleSubscriber = new AssignLocaleListener($this->translator);
        $assignLocaleSubscriber->postLoad($args);
    }

    /**
     * @test postLoad
     * @dataProvider provideTranslatableObjects
     */
    public function testPrePersist($object): void
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getObjectInfo($args, $object);
        $this->loadCurrentLocale();

        $assignLocaleSubscriber = new AssignLocaleListener($this->translator);
        $assignLocaleSubscriber->prePersist($args);
    }

    /**
     * @test postLoad
     * @dataProvider provideNonTranslatableObjects
     */
    public function testPrePersistNonTranslatableObjects($object): void
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $this->getObjectInfo($args, $object);

        $assignLocaleSubscriber = new AssignLocaleListener($this->translator);
        $assignLocaleSubscriber->postLoad($args);
    }

    /**
     * @param $args
     * @param $object
     */
    private function getObjectInfo($args, $object): void
    {
        $args
            ->expects($this->once())
            ->method('getObject')
            ->willReturn($object);
    }

    private function loadCurrentLocale(): void
    {
        $this->translator
            ->expects($this->once())
            ->method('loadCurrentLocale')
            ->willReturn($this->defaultLocale);
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->translator = $this->createMock(Translator::class);
        $this->defaultLocale = 'en';
    }

    public function provideTranslatableObjects(): \Generator
    {
        yield [new DummyTranslatable()];
    }

    public function provideNonTranslatableObjects(): \Generator
    {
        yield [new DummyNotTranslatable()];
        yield [new DummyTranslation()];
    }
}
