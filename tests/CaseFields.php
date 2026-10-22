<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Assert;

/**
 * A strict reader over one decoded JSON object of a case. Taking a key
 * removes it and fails when it is missing or of the wrong type, and done()
 * fails naming every key nobody took, so a case carrying a key the runner
 * does not know fails instead of being quietly ignored.
 */
final class CaseFields
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param string $caseId the id of the case this object belongs to, for messages
     * @param string $where  what this object is within the case (`case`, `input`, ...), for messages
     * @param mixed  $value  the decoded JSON value, which has to be an object
     */
    public function __construct(
        private readonly string $caseId,
        private readonly string $where,
        mixed $value,
    ) {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            Assert::fail($this->label() . ' is not a JSON object');
        }

        $data = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                Assert::fail($this->label() . ' has a key that is not a string');
            }
            $data[$key] = $item;
        }
        $this->data = $data;
    }

    /** Reads the root object of one raw case line. */
    public static function fromLine(string $line): self
    {
        $value = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $id = \is_array($value) && isset($value['id']) && \is_string($value['id']) ? $value['id'] : '(no id)';

        return new self($id, 'case', $value);
    }

    /** The id of the case this object belongs to. */
    public function caseId(): string
    {
        return $this->caseId;
    }

    /** Whether the key is present, which takes nothing. */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /** Takes a string. */
    public function string(string $key): string
    {
        $value = $this->take($key);
        if (!\is_string($value)) {
            $this->wrongType($key, 'a string');
        }

        return $value;
    }

    /** Takes a string or null. */
    public function nullableString(string $key): ?string
    {
        $value = $this->take($key);
        if ($value !== null && !\is_string($value)) {
            $this->wrongType($key, 'a string or null');
        }

        return $value;
    }

    /** Takes an integer. */
    public function int(string $key): int
    {
        $value = $this->take($key);
        if (!\is_int($value)) {
            $this->wrongType($key, 'an integer');
        }

        return $value;
    }

    /** Takes an integer or null. */
    public function nullableInt(string $key): ?int
    {
        $value = $this->take($key);
        if ($value !== null && !\is_int($value)) {
            $this->wrongType($key, 'an integer or null');
        }

        return $value;
    }

    /** Takes a boolean. */
    public function bool(string $key): bool
    {
        $value = $this->take($key);
        if (!\is_bool($value)) {
            $this->wrongType($key, 'a boolean');
        }

        return $value;
    }

    /**
     * Takes a list of integers.
     *
     * @return list<int>
     */
    public function intList(string $key): array
    {
        $value = $this->take($key);
        if (!\is_array($value) || !array_is_list($value)) {
            $this->wrongType($key, 'a list of integers');
        }

        $list = [];
        foreach ($value as $item) {
            if (!\is_int($item)) {
                $this->wrongType($key, 'a list of integers');
            }
            $list[] = $item;
        }

        return $list;
    }

    /**
     * Takes a list of strings.
     *
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $value = $this->take($key);
        if (!\is_array($value) || !array_is_list($value)) {
            $this->wrongType($key, 'a list of strings');
        }

        $list = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                $this->wrongType($key, 'a list of strings');
            }
            $list[] = $item;
        }

        return $list;
    }

    /** Takes a nested object, which has to be read to its own done(). */
    public function object(string $key): self
    {
        return new self($this->caseId, $this->where . '.' . $key, $this->take($key));
    }

    /** Fails naming every key that was not taken. */
    public function done(): void
    {
        if ($this->data !== []) {
            Assert::fail(
                $this->label() . ' has a key the runner does not know: ' . implode(', ', array_keys($this->data)),
            );
        }
    }

    private function take(string $key): mixed
    {
        if (!\array_key_exists($key, $this->data)) {
            Assert::fail($this->label() . ' has no ' . $key);
        }

        $value = $this->data[$key];
        unset($this->data[$key]);

        return $value;
    }

    private function wrongType(string $key, string $expected): never
    {
        Assert::fail($this->label() . ' has a ' . $key . ' that is not ' . $expected);
    }

    private function label(): string
    {
        return 'Case ' . $this->caseId . ': ' . $this->where;
    }
}
