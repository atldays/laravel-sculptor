<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Support;

use Atldays\Sculptor\Attributes\CacheScope;
use Atldays\Sculptor\Contracts\CacheScopeKey;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/** @internal */
final readonly class CacheScopeResolver
{
    private ?object $query;

    /** @var class-string */
    private string $queryClass;

    /** @var array<string, ReflectionParameter> */
    private array $parameters;

    /** @param class-string|object $query */
    public static function make(string|object $query): self
    {
        return new self($query);
    }

    /** @param class-string|object $query */
    public function __construct(string|object $query)
    {
        $this->query = is_object($query) ? $query : null;
        $this->queryClass = is_object($query) ? $query::class : $query;
        $this->parameters = $this->readParameters();
    }

    public function tag(): ?string
    {
        if ($this->query === null) {
            throw new LogicException('Reading cache scope values requires a query instance. Use tagForArguments() with a class name.');
        }

        if ($this->parameters === []) {
            return null;
        }

        $values = [];

        foreach ($this->parameters as $name => $parameter) {
            $class = $parameter->getDeclaringClass();

            if ($class === null) {
                throw new LogicException('Cache scopes require constructor parameters.');
            }

            $property = $class->getProperty($name);

            if (!$property->isInitialized($this->query)) {
                throw new LogicException("Cache scope property [$name] is not initialized.");
            }

            $values[$name] = $this->normalize($parameter, $property->getValue($this->query));
        }

        return $this->buildTag($values);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function tagForArguments(array $arguments): string
    {
        if ($this->parameters === []) {
            throw new LogicException("Query [$this->queryClass] has no #[CacheScope] parameters.");
        }

        $names = array_keys($this->parameters);
        $bound = [];
        $position = 0;

        foreach ($arguments as $key => $value) {
            $name = is_int($key) ? ($names[$position++] ?? null) : $key;

            if ($name === null || !array_key_exists($name, $this->parameters)) {
                throw new InvalidArgumentException('Unknown cache scope argument [' . $key . '].');
            }

            if (array_key_exists($name, $bound)) {
                throw new InvalidArgumentException("Duplicate cache scope argument [$name].");
            }

            $bound[$name] = $value;
        }

        $values = [];

        foreach ($this->parameters as $name => $parameter) {
            if (!array_key_exists($name, $bound) && !$parameter->isDefaultValueAvailable()) {
                throw new InvalidArgumentException("Missing cache scope argument [$name].");
            }

            $value = array_key_exists($name, $bound) ? $bound[$name] : $parameter->getDefaultValue();
            $values[$name] = $this->normalize($parameter, $value);
        }

        return $this->buildTag($values);
    }

    /**
     * @return array<string, ReflectionParameter>
     */
    private function readParameters(): array
    {
        $constructor = (new ReflectionClass($this->queryClass))->getConstructor();
        $parameters = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if ($parameter->getAttributes(CacheScope::class) === []) {
                continue;
            }

            if (!$parameter->isPromoted()) {
                throw new LogicException('#[CacheScope] requires a promoted constructor parameter [' . $parameter->getName() . '].');
            }

            $parameters[$parameter->getName()] = $parameter;
        }

        return $parameters;
    }

    /** @return array{type: string, value: bool|string|null} */
    private function normalize(ReflectionParameter $parameter, mixed $value): array
    {
        $type = $parameter->getType();

        if (!$this->accepts($type, $value)) {
            throw new InvalidArgumentException('Invalid type for cache scope argument [' . $parameter->getName() . '].');
        }

        // PHP permits int-to-float widening even for strictly typed constructors.
        if (is_int($value) && !$this->accepts($type, $value, false)) {
            $value = (float)$value;
        }

        if (is_float($value) && $value === 0.0) {
            $value = 0.0;
        }

        return match (true) {
            $value === null => ['type' => 'null', 'value' => null],
            is_bool($value) => ['type' => 'bool', 'value' => $value],
            is_int($value) => ['type' => 'int', 'value' => (string)$value],
            is_string($value) => ['type' => 'string', 'value' => bin2hex($value)],
            is_float($value) && is_finite($value) => ['type' => 'float', 'value' => bin2hex(pack('E', $value))],
            $value instanceof CacheScopeKey => ['type' => 'custom', 'value' => bin2hex($value->cacheScopeKey())],
            $value instanceof Model => $this->modelIdentity($value),
            $value instanceof UnitEnum => ['type' => 'enum:' . $value::class, 'value' => $value->name],
            default => throw new InvalidArgumentException('Cache scope argument [' . $parameter->getName() . '] must be a finite scalar, null, enum, Eloquent model or CacheScopeKey.'),
        };
    }

    /** @return array{type: string, value: string} */
    private function modelIdentity(Model $model): array
    {
        $key = $model->getKey();

        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentException('Cache scope model [' . $model::class . '] requires an integer or string primary key.');
        }

        $connection = $model->getConnectionName()
            ?? $model::getConnectionResolver()?->getDefaultConnection();

        return [
            'type' => 'model',
            'value' => serialize([$model::class, $connection, $key]),
        ];
    }

    private function accepts(?ReflectionType $type, mixed $value, bool $widenFloat = true): bool
    {
        if ($type === null) {
            return true;
        }

        if ($value === null) {
            return $type->allowsNull();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($this->accepts($member, $value, $widenFloat)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) {
                if (!$this->accepts($member, $value, $widenFloat)) {
                    return false;
                }
            }

            return true;
        }

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return match ($type->getName()) {
            'mixed' => true,
            'int' => is_int($value),
            'float' => is_float($value) || ($widenFloat && is_int($value)),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'object' => is_object($value),
            'true' => $value === true,
            'false' => $value === false,
            default => !$type->isBuiltin() && is_object($value) && is_a($value, $type->getName()),
        };
    }

    /**
     * @param array<string, array{type: string, value: bool|string|null}> $values
     */
    private function buildTag(array $values): string
    {
        return 'sculptor:scope:' . hash('sha256', serialize([$this->queryClass, $values]));
    }
}
