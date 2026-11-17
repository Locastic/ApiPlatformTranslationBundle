<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * Collects the SQL statements Doctrine DBAL's logging middleware reports, so
 * tests can count and inspect the queries a piece of code issues.
 */
final class QueryLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $queries = [];

    /**
     * The message is left untyped: psr/log 1 declares no type for it.
     *
     * @param string|\Stringable $message
     * @param array<mixed>       $context
     */
    public function log($level, $message, array $context = []): void
    {
        if (\is_string($context['sql'] ?? null)) {
            $this->queries[] = $context['sql'];
        }
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
