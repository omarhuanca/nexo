<?php

namespace Tests\Unit\Sale\Domain;

use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\DomainValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Sale::class)]
class SaleFromPayloadTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'invoiceType'     => 1,
            'transactionType' => 0,
            'cashier'         => 'Test Cashier',
            'buyer'           => [
                'name' => 'Test Buyer Co.',
                'id'   => '12345678',
            ],
            'items' => [
                [
                    'code'        => 'PROD-001',
                    'name'        => 'Test Product',
                    'quantity'    => 1,
                    'unitPrice'   => 50.00,
                    'totalAmount' => 50.00,
                    'labels'      => ['A'],
                    'accountCode' => '200',
                ],
            ],
            'payment' => [
                ['amount' => 50.00, 'paymentType' => 1],
            ],
        ], $overrides);
    }

    private function build(array $overrides = []): Sale
    {
        return Sale::fromPayload(1, 1, $this->validPayload($overrides));
    }

    private function assertDomainError(callable $action, string $field, string $message): void
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

    #[Test]
    public function it_builds_a_sale_from_a_valid_payload(): void
    {
        $sale = $this->build();

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertSame(1, $sale->getOrganizationId());
        $this->assertSame(1, $sale->getConnectorId());
        $this->assertSame(Sale::STATUS_PENDING, $sale->getStatus());
        $this->assertSame(0, $sale->getAttempts());
        $this->assertEquals($this->validPayload(), $sale->getPayload());
    }

    #[Test]
    public function it_rejects_empty_items(): void
    {
        $this->assertDomainError(
            fn () => $this->build(['items' => []]),
            'items',
            Sale::ERROR_ITEMS_EMPTY,
        );
    }

    #[Test]
    public function it_rejects_empty_payment(): void
    {
        $this->assertDomainError(
            fn () => $this->build(['payment' => []]),
            'payment',
            Sale::ERROR_PAYMENTS_EMPTY,
        );
    }

    #[Test]
    public function it_rejects_invalid_invoice_type(): void
    {
        $this->assertDomainError(
            fn () => $this->build(['invoiceType' => 99]),
            'invoiceType',
            Sale::ERROR_INVOICE_TYPE_INVALID,
        );
    }

    #[Test]
    public function it_rejects_invalid_transaction_type(): void
    {
        $this->assertDomainError(
            fn () => $this->build(['transactionType' => 99]),
            'transactionType',
            Sale::ERROR_TRANSACTION_TYPE_INVALID,
        );
    }
}
