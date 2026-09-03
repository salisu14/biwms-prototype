<?php

declare(strict_types=1);

namespace App\Services\Finance;

use InvalidArgumentException;

/**
 * Evaluates the deliberately small formula language used by account schedules.
 *
 * References are resolved by the caller so report services can detect cycles
 * while still allowing forward references. Numeric row codes take precedence
 * over numeric constants when a matching row exists.
 */
class AccountScheduleFormulaEvaluator
{
    /**
     * @param  callable(string): float  $resolveReference
     * @param  array<int, string>  $knownReferences
     */
    public function evaluate(string $formula, callable $resolveReference, array $knownReferences = []): float
    {
        $tokens = $this->tokenize($formula);
        $position = 0;
        $knownReferences = array_fill_keys($knownReferences, true);

        $parseExpression = function () use (&$parseExpression, &$position, $tokens, $resolveReference, $knownReferences): float {
            $value = $this->parseTerm($tokens, $position, $resolveReference, $knownReferences, $parseExpression);

            while (isset($tokens[$position]) && in_array($tokens[$position], ['+', '-'], true)) {
                $operator = $tokens[$position++];
                $right = $this->parseTerm($tokens, $position, $resolveReference, $knownReferences, $parseExpression);
                $value = $operator === '+' ? $value + $right : $value - $right;
            }

            return $value;
        };

        $value = $parseExpression();

        if ($position !== count($tokens)) {
            throw new InvalidArgumentException('Unexpected token in account schedule formula.');
        }

        return $value;
    }

    /**
     * Validate syntax and references without evaluating a report amount.
     *
     * @param  array<int, string>  $knownReferences
     */
    public function validate(string $formula, array $knownReferences = []): void
    {
        $tokens = $this->tokenize($formula);
        $knownReferences = array_fill_keys($knownReferences, true);

        foreach ($tokens as $token) {
            if ($this->isReference($token) && ! is_numeric($token) && ! isset($knownReferences[$token])) {
                throw new InvalidArgumentException("Unknown account schedule row reference [{$token}].");
            }

            if (is_numeric($token) && isset($knownReferences[$token])) {
                continue;
            }
        }

        $this->evaluate($formula, static fn (string $reference): float => 0.0, array_keys($knownReferences));
    }

    public function validateSyntax(string $formula): void
    {
        $tokens = $this->tokenize($formula);
        $references = collect($tokens)
            ->filter(fn (string $token): bool => $this->isReference($token) && ! is_numeric($token))
            ->unique()
            ->values()
            ->all();

        $this->evaluate($formula, static fn (string $reference): float => 0.0, $references);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $formula): array
    {
        if (trim($formula) === '') {
            throw new InvalidArgumentException('Account schedule formula cannot be empty.');
        }

        $tokens = [];
        $offset = 0;
        $length = strlen($formula);

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $formula, $matches, 0, $offset)) {
                $offset += strlen($matches[0]);

                continue;
            }

            if (preg_match('/\G(?:\d+(?:\.\d+)?|\.\d+)/A', $formula, $matches, 0, $offset)) {
                $tokens[] = $matches[0];
                $offset += strlen($matches[0]);

                continue;
            }

            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_.-]*/A', $formula, $matches, 0, $offset)) {
                $tokens[] = $matches[0];
                $offset += strlen($matches[0]);

                continue;
            }

            $character = $formula[$offset];
            if (in_array($character, ['+', '-', '*', '/', '(', ')'], true)) {
                $tokens[] = $character;
                $offset++;

                continue;
            }

            throw new InvalidArgumentException('Unsupported token in account schedule formula.');
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, bool>  $knownReferences
     * @param  callable(string): float  $resolveReference
     * @param  callable(): float  $parseExpression
     */
    private function parseTerm(
        array $tokens,
        int &$position,
        callable $resolveReference,
        array $knownReferences,
        callable $parseExpression,
    ): float {
        $value = $this->parseFactor($tokens, $position, $resolveReference, $knownReferences, $parseExpression);

        while (isset($tokens[$position]) && in_array($tokens[$position], ['*', '/'], true)) {
            $operator = $tokens[$position++];
            $right = $this->parseFactor($tokens, $position, $resolveReference, $knownReferences, $parseExpression);

            if ($operator === '/' && abs($right) < PHP_FLOAT_EPSILON) {
                throw new InvalidArgumentException('Division by zero in account schedule formula.');
            }

            $value = $operator === '*' ? $value * $right : $value / $right;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, bool>  $knownReferences
     * @param  callable(string): float  $resolveReference
     * @param  callable(): float  $parseExpression
     */
    private function parseFactor(
        array $tokens,
        int &$position,
        callable $resolveReference,
        array $knownReferences,
        callable $parseExpression,
    ): float {
        $token = $tokens[$position] ?? null;

        if ($token === null) {
            throw new InvalidArgumentException('Incomplete account schedule formula.');
        }

        if (in_array($token, ['+', '-'], true)) {
            $position++;
            $value = $this->parseFactor($tokens, $position, $resolveReference, $knownReferences, $parseExpression);

            return $token === '-' ? -$value : $value;
        }

        if ($token === '(') {
            $position++;
            $value = $parseExpression();

            if (($tokens[$position] ?? null) !== ')') {
                throw new InvalidArgumentException('Unmatched parenthesis in account schedule formula.');
            }

            $position++;

            return $value;
        }

        if ($token === ')' || in_array($token, ['*', '/'], true)) {
            throw new InvalidArgumentException('Unexpected operator in account schedule formula.');
        }

        $position++;

        if (is_numeric($token) && ! isset($knownReferences[$token])) {
            return (float) $token;
        }

        return (float) $resolveReference($token);
    }

    private function isReference(string $token): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $token) === 1 || is_numeric($token);
    }
}
