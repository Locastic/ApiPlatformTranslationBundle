<?php

declare(strict_types=1);

namespace Locastic\ApiTranslationBundle\Tests;

use Locastic\ApiPlatformTranslationBundle\ApiPlatformTranslationBundle;
use PHPUnit\Framework\TestCase;

/**
 * Class ApiPlatformTranslationBundleTest
 *
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
