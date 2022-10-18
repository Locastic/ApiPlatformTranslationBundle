<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests;

use Locastic\ApiPlatformTranslationBundle\ApiPlatformTranslationBundle;
use PHPUnit\Framework\TestCase;

/**
 * @package UnitTests\TranslationBundle
 * @covers \Locastic\ApiPlatformTranslationBundle\ApiPlatformTranslationBundle
 */
class ApiPlatformTranslationBundleTest extends TestCase
{
    public function testClassExist(): void
    {
        $this->assertTrue(class_exists(ApiPlatformTranslationBundle::class));
    }

    public function testExtensionIsLoaded(): void
    {
        $bundle = new ApiPlatformTranslationBundle();
        $this->assertNotNull($bundle->getContainerExtension());
    }
}
