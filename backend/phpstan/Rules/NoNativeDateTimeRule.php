<?php

declare(strict_types=1);

namespace Eventor\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Chapter 08/10: "now" comes from `Psr\Clock\ClockInterface` only (MockClock in tests), never from ambient time.
 * Forbidden in `src/`: `new \DateTime()`/`new \DateTimeImmutable()` without arguments or with a relative string
 * ("now", "+1 day", ...), and the functions time(), date(), gmdate(), strtotime(), mktime(), date_create().
 * Parsing a concrete value (`new \DateTimeImmutable($row['created_at'])`) is fine.
 *
 * @implements Rule<Node>
 */
final class NoNativeDateTimeRule implements Rule
{
    private const array FUNCTIONS = ['time', 'date', 'gmdate', 'strtotime', 'mktime', 'gmmktime', 'date_create', 'date_create_immutable'];
    private const array CLASSES = ['datetime', 'datetimeimmutable'];
    private const string RELATIVE = '/^\s*(now|today|tomorrow|yesterday|midnight|noon|[+-]\s*\d|next\s|last\s|first\s|this\s)/i';

    /**
     * @param string $sourcePathFragment only files whose path contains this fragment are checked (production code)
     */
    public function __construct(private readonly string $sourcePathFragment = '/src/') {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());
        if (!str_contains($file, $this->sourcePathFragment) || str_contains($file, '/Shared/Infrastructure/Clock/')) {
            return [];
        }

        if ($node instanceof FuncCall && $node->name instanceof Name && \in_array(strtolower($node->name->toString()), self::FUNCTIONS, true)) {
            return [$this->error(\sprintf('Function %s() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', $node->name->toString()), $node)];
        }

        if ($node instanceof New_ && $node->class instanceof Name && \in_array(strtolower(ltrim($node->class->toString(), '\\')), self::CLASSES, true)) {
            $first = $node->args[0] ?? null;
            $value = $first instanceof Node\Arg ? $first->value : null;
            if (null === $value || ($value instanceof String_ && 1 === preg_match(self::RELATIVE, $value->value))) {
                return [$this->error(\sprintf('new %s() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', $node->class->toString()), $node)];
            }
        }

        return [];
    }

    private function error(string $message, Node $node): IdentifierRuleError
    {
        /** @var IdentifierRuleError&\PHPStan\Rules\LineRuleError $error */
        $error = RuleErrorBuilder::message($message)->identifier('eventor.ambientTime')->line($node->getStartLine())->build();

        return $error;
    }
}
