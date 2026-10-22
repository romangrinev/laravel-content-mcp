<?php

namespace GrinevStudio\LaravelContentMcp;

use Illuminate\Validation\Rules\In;

/** Only public, declarative rules. SQL, callbacks, model names and credentials never cross this boundary. */
final class ValidationMetadata
{
    /** @param array<string, mixed> $rules
     * @param  array<mixed>  $allowed
     * @return array<string, array<string, mixed>>
     */
    public static function describe(array $rules, array $allowed): array
    {
        $result = [];
        foreach (array_slice($rules, 0, 100, true) as $field => $fieldRules) {
            if (! self::allowed($field, $allowed)) {
                continue;
            }
            $parts = is_string($fieldRules) ? explode('|', $fieldRules) : (is_array($fieldRules) ? $fieldRules : []);
            $parts = array_values(array_filter(array_map(fn ($rule) => $rule instanceof In ? (string) $rule : (is_string($rule) ? $rule : null), $parts), fn ($rule) => $rule !== null));
            $type = match (true) {
                in_array('string', $parts, true) => 'string',
                in_array('integer', $parts, true) => 'integer',
                in_array('numeric', $parts, true) => 'number',
                in_array('boolean', $parts, true) => 'boolean',
                in_array('array', $parts, true) => 'array',
                default => null,
            };
            $description = ['required' => in_array('required', $parts, true), 'nullable' => in_array('nullable', $parts, true)];
            if ($type !== null) {
                $description['type'] = $type;
            }
            foreach ($parts as $part) {
                [$name, $argument] = array_pad(explode(':', $part, 2), 2, '');
                if ($name === 'in') {
                    $values = str_getcsv($argument, ',', '"', '\\');
                    if (count($values) <= 50 && count(array_filter($values, fn ($v) => is_string($v) && strlen($v) <= 100 && preg_match('/^[\pL\pN _.,:\/-]*$/uD', $v) && ! preg_match('/password|secret|token|credential|bearer|private.key/i', $v))) === count($values)) {
                        $description['enum'] = $values;
                    }
                }
                if (in_array($name, ['min', 'max', 'between'], true) && $type !== null) {
                    $bounds = $name === 'between' ? explode(',', $argument) : [$argument];
                    foreach ($bounds as $i => $bound) {
                        if (! is_numeric($bound) || ! is_finite((float) $bound) || abs((float) $bound) > 1e12) {
                            continue;
                        }
                        $minimum = $name === 'min' || ($name === 'between' && $i === 0);
                        $key = match ($type) {
                            'string' => $minimum ? 'minLength' : 'maxLength',
                            'array' => $minimum ? 'minItems' : 'maxItems',
                            'integer', 'number' => $minimum ? 'minimum' : 'maximum',
                            default => null,
                        };
                        if ($key !== null) {
                            $description[$key] = (float) $bound;
                        }
                    }
                }
                $format = match ($name) {
                    'email' => 'email', 'url' => 'uri', 'uuid' => 'uuid',
                    'date_format' => $argument === 'Y-m-d' ? 'date' : null,
                    default => null,
                };
                if ($format !== null) {
                    $description['format'] = $format;
                }
            }
            $result[$field] = $description;
        }

        return $result;
    }

    /** @param array<string, array<string, mixed>> $failed
     * @param  array<mixed>  $allowed
     * @return array<string, list<array<string, mixed>>>
     */
    public static function errors(array $failed, array $allowed): array
    {
        $fields = [];
        foreach (array_slice($failed, 0, 50, true) as $field => $rules) {
            if (! self::allowed($field, $allowed)) {
                continue;
            }
            foreach (array_slice($rules, 0, 5, true) as $rule => $parameters) {
                $code = match (strtolower($rule)) {
                    'in' => 'enum',
                    'required', 'string', 'integer', 'numeric', 'boolean', 'array', 'min', 'max', 'distinct', 'url' => strtolower($rule),
                    default => 'invalid',
                };
                $fields[$field][] = ['code' => $code, 'message' => match ($code) {
                    'enum' => 'Choose one of the allowed values.',
                    'required' => 'This field is required.',
                    'max' => 'This field exceeds the allowed maximum.',
                    'min' => 'This field is below the allowed minimum.',
                    default => 'This field does not satisfy its validation rules.',
                }];
            }
        }

        return $fields;
    }

    /** @param array<mixed> $allowed */
    private static function allowed(string $field, array $allowed): bool
    {
        return strlen($field) <= 100 && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(?:\.(?:[a-zA-Z0-9_]+|\*))*$/D', $field)
            && ! preg_match('/password|secret|token|credential|authorization|oauth|private_key/i', $field)
            && in_array(explode('.', $field)[0], $allowed, true);
    }
}
