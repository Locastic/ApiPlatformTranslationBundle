<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;

/**
 * Class DummyTranslatable
 *
 * @package Locastic\ApiPlatformTranslationBundle\Tests\Fixtures
 */
class DummyTranslatable extends AbstractTranslatable
{
    /**
     * {@inheritdoc}
     */
    protected function createTranslation(): TranslationInterface
    {
        return new DummyTranslation();
    }
}
