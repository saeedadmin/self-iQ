<?php

declare(strict_types=1);

namespace Tak\Attributes;

use Attribute;
use BadMethodCallException;
use Error;
use Reflector;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use function array_column;
use function array_key_exists;
use function array_map;
use function array_search;
use function boolval;
use function call_user_func;
use function call_user_func_array;
use function is_null;
use function is_string;
use function method_exists;
use function property_exists;

// Fallback copy of Tak\\Attributes\\AttributesEngine to satisfy composer plugin execution during build.
trait AttributesEngine
{
    private static array $metadataCache = [];

    public function __call(string $name, array $args): mixed
    {
        return $this->invokeValidatedMethod(static::class, $name, $args);
    }

    public static function __callStatic(string $name, array $args): mixed
    {
        $staticClass = new ReflectionClass(static::class);
        $instance = $staticClass->newInstanceWithoutConstructor();
        return $instance->invokeValidatedMethod(static::class, $name, $args);
    }

    public function __set(string $name, mixed $value): void
    {
        if (!property_exists($this, $name)) {
            throw new Error('Property ' . $name . ' does not exist on ' . static::class);
        }

        $reflection = new ReflectionProperty($this, $name);
        $identifier = 'property::' . $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();

        if (!array_key_exists($identifier, self::$metadataCache)) {
            self::$metadataCache[$identifier] = $this->buildMetadata($reflection);
        }

        $metas = self::$metadataCache[$identifier];
        $allowsNull = boolval($metas['allowsNull'] && is_null($value));
        $isDefault = boolval($metas['hasDefault'] && call_user_func($metas['hasDefault']) === $value);

        if (!$allowsNull && !$isDefault) {
            foreach ($metas['setValidators'] as $validator) {
                $value = $validator->check($name, $value);
            }
        }

        if (empty($metas['setValidators']) && empty($metas['getValidators'])) {
            throw new Error('Cannot access property ' . static::class . '::$' . $name);
        }

        $reflection->setValue($this, $value);
    }

    public function __get(string $name): mixed
    {
        if (!property_exists($this, $name)) {
            throw new Error('Property ' . $name . ' does not exist on ' . static::class);
        }

        $reflection = new ReflectionProperty($this, $name);
        $identifier = 'property::' . $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();

        if (!array_key_exists($identifier, self::$metadataCache)) {
            self::$metadataCache[$identifier] = $this->buildMetadata($reflection);
        }

        $metas = self::$metadataCache[$identifier];
        $value = $reflection->getValue($this);

        foreach ($metas['getValidators'] as $validator) {
            $value = $validator->check($name, $value);
        }

        if (empty($metas['getValidators']) && empty($metas['setValidators'])) {
            throw new Error('Cannot access property ' . static::class . '::$' . $name);
        }

        return $value;
    }

    private function invokeValidatedMethod(string $className, string $methodName, array $arguments, bool $isAccessible = false): mixed
    {
        if (!method_exists($className, $methodName)) {
            throw new BadMethodCallException('Method ' . $className . '::' . $methodName . ' does not exist');
        }

        $reflection = new ReflectionMethod($className, $methodName);

        if (!$reflection->isProtected()) {
            throw new Error('Call to method ' . $className . '::' . $methodName . '() that is not protected');
        }

        $identifier = 'method::' . $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();

        if (!array_key_exists($identifier, self::$metadataCache)) {
            self::$metadataCache[$identifier] = $this->buildMetadata($reflection);
        }

        $metas = self::$metadataCache[$identifier];
        $parametersName = array_column($metas['parameters'], 'name');
        $args = [];

        foreach ($arguments as $key => $value) {
            $index = is_string($key) ? array_search($key, $parametersName, true) : $key;
            $pMeta = $metas['parameters'][$index];
            $allowsNull = boolval($pMeta['allowsNull'] && is_null($value));
            $isDefault = boolval($pMeta['hasDefault'] && call_user_func($pMeta['hasDefault']) === $value);

            if (!$allowsNull && !$isDefault) {
                foreach ($pMeta['validators'] as $validator) {
                    $value = $validator->validate($pMeta['name'], $value);
                }
            }

            $args[$pMeta['name']] = $value;
        }

        foreach ($metas['parameters'] as $pMeta) {
            $isAccessible |= !empty($pMeta['validators']);
        }

        $isAccessible |= !empty($metas['performers']);
        $isAccessible |= !empty($metas['return']);

        if (!boolval($isAccessible)) {
            throw new Error('Call to method ' . $className . '::' . $methodName . '() from global scope');
        }

        $run = static fn(mixed ...$params): mixed => $reflection->invokeArgs($reflection->isStatic() ? null : $this, $params);

        foreach ($metas['performers'] as $performer) {
            $run = static fn(mixed ...$params): mixed => $performer->invoke($run, $params);
        }

        $result = call_user_func_array($run, $args);

        foreach ($metas['return'] as $returnFilter) {
            $result = $returnFilter->filter($identifier, $result);
        }

        return $result;
    }

    private function buildMetadata(Reflector $reflection): array
    {
        if ($reflection instanceof ReflectionMethod) {
            $parameters = $reflection->getParameters();
            $args = [];

            foreach ($parameters as $parameter) {
                $args[] = [
                    'name' => $parameter->getName(),
                    'allowsNull' => $parameter->allowsNull(),
                    'hasDefault' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue(...) : false,
                    'validators' => $this->resolveAttributeInstances($parameter, ValidatorInterface::class),
                ];
            }

            $returnFilters = $this->resolveAttributeInstances($reflection, ReturnFilterInterface::class);
            $invokes = $this->resolveAttributeInstances($reflection, InvokeInterface::class);

            return [
                'parameters' => $args,
                'return' => $returnFilters,
                'performers' => $invokes,
            ];
        }

        if ($reflection instanceof ReflectionProperty) {
            $type = $reflection->getType();

            return [
                'name' => $reflection->getName(),
                'allowsNull' => boolval($type?->allowsNull() ?? true),
                'hasDefault' => $reflection->hasDefaultValue() ? $reflection->getDefaultValue(...) : false,
                'setValidators' => $this->resolveAttributeInstances($reflection, Property\Set::class),
                'getValidators' => $this->resolveAttributeInstances($reflection, Property\Get::class),
            ];
        }

        return [];
    }

    private function resolveAttributeInstances(Reflector $reflection, string $class): array
    {
        $attributes = $reflection->getAttributes($class, ReflectionAttribute::IS_INSTANCEOF);

        return array_map(static fn(ReflectionAttribute $attr): object => $attr->newInstance(), $attributes);
    }
}
