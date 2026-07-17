<?php

namespace Tests\Unit\Sale\ValueObjects;

use App\Shared\Exceptions\DomainValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests of immutable Value Objects that build themselves from
 * raw arrays and throw {@see DomainValidationException} on rule violations.
 *
 * Provides shared assertion helpers so each concrete test case stays focused
 * on the behaviour of the Value Object under test, not on the boilerplate of
 * catching and inspecting domain exceptions.
 */
abstract class ValueObjectTestCase extends TestCase
{
    /**
     * Assert that invoking $action throws a DomainValidationException whose
     * $errors array contains $field with $message.
     */
    protected function assertDomainError(callable $action, string $field, string $message): void
    {
        try {
            $action();
            $this->fail("Expected DomainValidationException for field '{$field}' was not thrown.");
        } catch (DomainValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey($field, $errors, "Field '{$field}' not present in errors.");
            $this->assertContains($message, $errors[$field]);
        }
    }

    /**
     * Assert that invoking $action throws a DomainValidationException whose
     * $errors array contains ALL the given $expectedFields (no assumptions
     * about the messages, only the field paths).
     */
    protected function assertDomainHasErrors(callable $action, array $expectedFields): void
    {
        try {
            $action();
            $this->fail('Expected DomainValidationException was not thrown.');
        } catch (DomainValidationException $e) {
            $errors = $e->getErrors();
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $errors, "Field '{$field}' not present in errors.");
            }
        }
    }
}
