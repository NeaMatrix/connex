<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Testing;

use PHPUnit\Framework\Assert;

/**
 * Call recording and assertions for {@see FakeConnexClient}.
 */
trait RecordsCalls
{
    /** @var list<array{method: string, arguments: array<string, mixed>}> */
    private array $calls = [];

    /**
     * @param  callable(array<string, mixed>): bool|null  $filter  receives the call's arguments
     */
    public function assertCalled(string $method, ?callable $filter = null, int $times = 1): static
    {
        $count = count($this->calls($method, $filter));
        Assert::assertSame($times, $count, "Expected Connex [{$method}] to be called {$times} time(s), it was called {$count}.");

        return $this;
    }

    public function assertNotCalled(string $method): static
    {
        Assert::assertSame([], $this->calls($method), "Connex [{$method}] was called unexpectedly.");

        return $this;
    }

    public function assertNothingCalled(): static
    {
        Assert::assertSame([], $this->calls, 'Connex was called unexpectedly.');

        return $this;
    }

    public function assertOtpRequested(string $msisdn): static
    {
        $number = $this->number($msisdn);

        return $this->assertCalled('login', fn (array $arguments): bool => $arguments['number'] === $number);
    }

    /**
     * @param  callable(array<string, mixed>): bool|null  $filter
     * @return list<array<string, mixed>>
     */
    public function calls(string $method, ?callable $filter = null): array
    {
        $arguments = array_column(array_filter($this->calls, fn (array $call): bool => $call['method'] === $method), 'arguments');

        return array_values($filter === null ? $arguments : array_filter($arguments, $filter));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function record(string $method, array $arguments): void
    {
        $this->calls[] = ['method' => $method, 'arguments' => $arguments];
        $this->beforeCall($method);
    }

    abstract protected function beforeCall(string $method): void;
}
