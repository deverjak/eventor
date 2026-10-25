<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;
use Eventor\Catalog\Domain\Exception\InvalidFormSchema;
use Eventor\Shared\Domain\Id\ActivityId;

/**
 * The registration form of an event as data (ADR 007): fields with type, scope, semantics, conditions and bindings.
 * A schema is immutable; `fromArray()` is the only way in from the outside and reports every problem at once with
 * its path (`fields[3].condition.field`), so the editor can mark all of them.
 */
final readonly class FormSchema
{
    public const int MAX_FIELDS = 100;

    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /**
     * @param list<FieldDefinition> $fields
     */
    private function __construct(public array $fields) {}

    /**
     * @param list<FieldDefinition> $fields already valid definitions (defaults, tests)
     */
    public static function of(array $fields): self
    {
        return new self($fields);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<array-key, mixed> $data `{"fields": [...]}`
     *
     * @throws InvalidFormSchema
     */
    public static function fromArray(array $data): self
    {
        $violations = [];
        $raw = $data['fields'] ?? null;
        if (!\is_array($raw) || !array_is_list($raw)) {
            throw new InvalidFormSchema([['path' => 'fields', 'message' => 'The schema needs a list of fields.', 'code' => 'invalid_value']]);
        }
        if (\count($raw) > self::MAX_FIELDS) {
            $violations[] = ['path' => 'fields', 'message' => \sprintf('A form has at most %d fields.', self::MAX_FIELDS), 'code' => 'too_many'];
        }

        /** @var array<string, FieldDefinition> $seen */
        $seen = [];
        /** @var array<string, true> $semantics */
        $semantics = [];
        foreach ($raw as $index => $item) {
            $path = \sprintf('fields[%d]', $index);
            if (!\is_array($item)) {
                $violations[] = ['path' => $path, 'message' => 'A field must be an object.', 'code' => 'invalid_value'];
                continue;
            }
            $field = self::parseField($item, $path, $seen, $violations);
            if (null === $field) {
                continue;
            }
            if (null !== $field->semantic) {
                $slot = $field->scope->value.'/'.$field->semantic->value;
                if (isset($semantics[$slot])) {
                    $violations[] = ['path' => $path.'.semantic', 'message' => \sprintf('The semantic field "%s" is already used in the scope "%s".', $field->semantic->value, $field->scope->value), 'code' => 'duplicate_semantic'];
                }
                $semantics[$slot] = true;
            }
            $seen[$field->key] = $field;
        }

        if ([] !== $violations) {
            throw new InvalidFormSchema($violations);
        }

        return new self(array_values($seen));
    }

    /**
     * @return array{fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['fields' => array_map(static fn(FieldDefinition $field): array => $field->toArray(), $this->fields)];
    }

    /** Key order and whitespace independent form of the schema: the input of the version hash. */
    public function canonicalJson(): string
    {
        return json_encode(self::canonicalize($this->toArray()), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->canonicalJson());
    }

    public function field(string $key): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<FieldDefinition>
     */
    public function fieldsFor(AnswerScope $scope): array
    {
        return array_values(array_filter($this->fields, static fn(FieldDefinition $field): bool => $field->scope === $scope));
    }

    /**
     * @return list<string> keys of the required fields that carry an answer
     */
    public function requiredKeys(): array
    {
        return array_values(array_map(
            static fn(FieldDefinition $field): string => $field->key,
            array_filter($this->fields, static fn(FieldDefinition $field): bool => $field->required && $field->type->hasAnswer()),
        ));
    }

    public function hasSemantic(SemanticField $semantic): bool
    {
        foreach ($this->fields as $field) {
            if ($field->semantic === $semantic) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed>       $item
     * @param array<string, FieldDefinition> $seen
     * @param list<array{path: string, message: string, code: string}> $violations
     */
    private static function parseField(array $item, string $path, array $seen, array &$violations): ?FieldDefinition
    {
        $before = \count($violations);
        $add = static function (string $sub, string $message, string $code) use (&$violations, $path): void {
            $violations[] = ['path' => '' === $sub ? $path : $path.'.'.$sub, 'message' => $message, 'code' => $code];
        };

        $key = $item['key'] ?? null;
        if (!\is_string($key) || 1 !== preg_match(self::KEY_PATTERN, $key)) {
            $add('key', 'The key is lower-case letters, digits and underscores, starting with a letter (max 64).', 'invalid_value');
            $key = '';
        } elseif (isset($seen[$key])) {
            $add('key', \sprintf('The key "%s" is used twice.', $key), 'duplicate_key');
        }

        $type = \is_string($item['type'] ?? null) ? FieldTypeName::tryFrom($item['type']) : null;
        if (null === $type) {
            $add('type', 'Unknown field type.', 'unknown_type');
        }

        $label = $item['label'] ?? null;
        if (!\is_string($label) || '' === trim($label) || mb_strlen($label) > 200) {
            $add('label', 'The label is required (max 200 characters).', 'invalid_value');
            $label = '';
        }

        $help = $item['help'] ?? null;
        if (null !== $help && (!\is_string($help) || mb_strlen($help) > 2000)) {
            $add('help', 'The help text is a string of at most 2000 characters.', 'invalid_value');
            $help = null;
        }

        $required = $item['required'] ?? false;
        if (!\is_bool($required)) {
            $add('required', 'Required is true or false.', 'invalid_value');
            $required = false;
        }
        if ($required && null !== $type && !$type->hasAnswer()) {
            $add('required', 'An information text cannot be required.', 'invalid_value');
        }

        $scope = \is_string($item['scope'] ?? null) ? AnswerScope::tryFrom($item['scope']) : null;
        if (null === $scope) {
            $add('scope', 'The scope is one of order, person, participation, group.', 'invalid_value');
        }

        $constraints = [];
        if (null !== $type) {
            $constraints = self::parseConstraints($item['constraints'] ?? [], $type, $add);
        }

        $semantic = null;
        $rawSemantic = $item['semantic'] ?? null;
        if (null !== $rawSemantic) {
            $semantic = \is_string($rawSemantic) ? SemanticField::tryFrom($rawSemantic) : null;
            if (null === $semantic) {
                $add('semantic', 'Unknown semantic field.', 'invalid_value');
            } elseif (null !== $type && !$type->carries($semantic)) {
                $add('semantic', \sprintf('A field of the type "%s" cannot carry "%s".', $type->value, $semantic->value), 'incompatible_semantic');
            }
        }

        $condition = null;
        if (null !== ($item['condition'] ?? null)) {
            $condition = self::parseCondition($item['condition'], $scope, $seen, $key, $add);
        }

        $activityIds = self::parseIds($item['activityIds'] ?? [], 'activityIds', $add);
        $variantIds = self::parseIds($item['variantIds'] ?? [], 'variantIds', $add);

        $default = $item['defaultValue'] ?? null;
        if (null !== $default && !\is_scalar($default) && !\is_array($default)) {
            $add('defaultValue', 'The default value must be a scalar or a list.', 'invalid_value');
            $default = null;
        }

        if (\count($violations) > $before || null === $type || null === $scope) {
            return null;
        }

        return new FieldDefinition($key, $type, $label, $help, $required, $scope, $constraints, $semantic, $condition, $activityIds, $variantIds, $default);
    }

    /**
     * @param callable(string, string, string): void $add
     *
     * @return array<string, mixed>
     */
    private static function parseConstraints(mixed $raw, FieldTypeName $type, callable $add): array
    {
        if ([] === $raw || null === $raw) {
            $raw = [];
        }
        if (!\is_array($raw) || ([] !== $raw && array_is_list($raw))) {
            $add('constraints', 'Constraints are an object.', 'invalid_value');

            return [];
        }

        $allowed = $type->allowedConstraints();
        $out = [];
        foreach ($raw as $name => $value) {
            $name = (string) $name;
            $sub = 'constraints.'.$name;
            if (!\in_array($name, $allowed, true)) {
                $add($sub, \sprintf('The type "%s" has no constraint "%s".', $type->value, $name), 'unknown_constraint');
                continue;
            }
            $ok = match ($name) {
                'minLength', 'maxLength' => \is_int($value) && $value >= 0 && $value <= 10_000,
                'min', 'max' => \is_int($value) || \is_float($value),
                'integer' => \is_bool($value),
                'minDate', 'maxDate', 'referenceDate' => \is_string($value) && self::isDate($value),
                'minAge', 'maxAge' => \is_int($value) && $value >= 0 && $value <= 120,
                'minChoices', 'maxChoices' => \is_int($value) && $value >= 0 && $value <= 200,
                'requirementId' => \is_string($value) && ActivityId::isValid($value),
                'choices' => self::validChoices($value),
                default => false,
            };
            if (!$ok) {
                $add($sub, \sprintf('The value of "%s" is not valid.', $name), 'invalid_value');
                continue;
            }
            $out[$name] = $value;
        }

        foreach ([['minLength', 'maxLength'], ['min', 'max'], ['minDate', 'maxDate'], ['minAge', 'maxAge'], ['minChoices', 'maxChoices']] as [$low, $high]) {
            if (isset($out[$low], $out[$high]) && $out[$low] > $out[$high]) {
                $add('constraints.'.$low, \sprintf('"%s" cannot be greater than "%s".', $low, $high), 'invalid_range');
            }
        }
        if ($type->isChoice() && !isset($out['choices']) && !\array_key_exists('choices', $raw)) {
            $add('constraints.choices', 'A choice field needs at least one choice.', 'invalid_value');
        }

        return $out;
    }

    private static function validChoices(mixed $value): bool
    {
        if (!\is_array($value) || [] === $value || !array_is_list($value) || \count($value) > 200) {
            return false;
        }
        $seen = [];
        foreach ($value as $choice) {
            if (!\is_array($choice) || !\is_string($choice['label'] ?? null) || '' === trim($choice['label']) || !(\is_string($choice['value'] ?? null) || \is_int($choice['value'] ?? null))) {
                return false;
            }
            $id = (string) $choice['value'];
            if ('' === $id || isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
        }

        return true;
    }

    /**
     * @param array<string, FieldDefinition> $seen
     * @param callable(string, string, string): void $add
     */
    private static function parseCondition(mixed $raw, ?AnswerScope $scope, array $seen, string $ownKey, callable $add): ?FieldCondition
    {
        if (!\is_array($raw)) {
            $add('condition', 'A condition is an object.', 'invalid_value');

            return null;
        }
        $type = \is_string($raw['type'] ?? null) ? ConditionType::tryFrom($raw['type']) : null;
        if (null === $type) {
            $add('condition.type', 'A condition looks at an activity, a variant or an answer.', 'invalid_value');

            return null;
        }

        if (ConditionType::Answer !== $type) {
            $ids = self::parseIds($raw['ids'] ?? [], 'condition.ids', $add);
            if ([] === $ids) {
                $add('condition.ids', 'Name at least one id.', 'invalid_value');

                return null;
            }

            return new FieldCondition($type, $ids);
        }

        $target = $raw['field'] ?? null;
        $operator = \is_string($raw['operator'] ?? null) ? ConditionOperator::tryFrom($raw['operator']) : null;
        $value = $raw['value'] ?? null;
        $valid = true;
        if (!\is_string($target) || $target === $ownKey || !isset($seen[$target])) {
            $add('condition.field', 'A condition may only look at a field that comes earlier in the form.', 'invalid_reference');
            $valid = false;
        } elseif (!$seen[$target]->type->hasAnswer() || (null !== $scope && $seen[$target]->scope !== $scope)) {
            $add('condition.field', 'The referenced field must carry an answer and have the same scope.', 'invalid_reference');
            $valid = false;
        }
        if (null === $operator) {
            $add('condition.operator', 'The operator is one of equals, not_equals, filled, empty.', 'invalid_value');
            $valid = false;
        } elseif ($operator->needsValue() && !\is_scalar($value)) {
            $add('condition.value', 'This operator needs a value to compare with.', 'invalid_value');
            $valid = false;
        }

        if (!$valid || !\is_string($target) || null === $operator) {
            return null;
        }

        return new FieldCondition(ConditionType::Answer, [], $target, $operator, $operator->needsValue() ? $value : null);
    }

    /**
     * @param callable(string, string, string): void $add
     *
     * @return list<string>
     */
    private static function parseIds(mixed $raw, string $sub, callable $add): array
    {
        if (!\is_array($raw) || !array_is_list($raw)) {
            $add($sub, 'A list of ids is expected.', 'invalid_value');

            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            if (!\is_string($id) || !ActivityId::isValid($id)) {
                $add($sub, 'Every id must be a UUID.', 'invalid_value');

                return [];
            }
            $ids[] = strtolower($id);
        }

        return array_values(array_unique($ids));
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        $mapped = array_map(self::canonicalize(...), $value);
        if (!array_is_list($mapped)) {
            ksort($mapped);
        }

        return $mapped;
    }
}
