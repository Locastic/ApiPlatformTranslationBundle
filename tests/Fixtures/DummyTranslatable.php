<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;

class DummyTranslatable extends AbstractTranslatable
{
    protected function createTranslation(): TranslationInterface
    {
        return new DummyTranslation();
    }
}
