<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Fixture;

/**
 * Minimal fluent collection double: records every call and iterates over fixed rows.
 */
class FakeCollection implements \IteratorAggregate
{
    /** @var array<int, array{0: string, 1: array}> */
    public array $calls = [];

    private array $rows;

    private ?\Throwable $iterateError;

    public function __construct(array $rows = [], ?\Throwable $iterateError = null)
    {
        $this->rows = $rows;
        $this->iterateError = $iterateError;
    }

    public function __call(string $name, array $args)
    {
        $this->calls[] = [$name, $args];
        return $this;
    }

    public function getIterator(): \Iterator
    {
        if ($this->iterateError !== null) {
            throw $this->iterateError;
        }
        return new \ArrayIterator($this->rows);
    }

    /**
     * All argument lists passed to the given method, in call order.
     */
    public function argsFor(string $method): array
    {
        $out = [];
        foreach ($this->calls as [$name, $args]) {
            if ($name === $method) {
                $out[] = $args;
            }
        }
        return $out;
    }
}
