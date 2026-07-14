<?php

namespace Tests\Unit\Sale\Domain;

use App\Modules\Sale\Domain\Sale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\Sale\ValueObjects\ValueObjectTestCase;

#[CoversClass(Sale::class)]
class SaleFromPayloadTest extends ValueObjectTestCase
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

    #[Test]
    public function it_aggregates_buyer_and_item_errors_with_indexed_paths(): void
    {
        $this->assertDomainHasErrors(
            fn () => $this->build([
                'buyer' => ['name' => ''],
                'items' => [
                    [
                        'code'        => 'PROD-001',
                        'name'        => 'Test Product',
                        'quantity'    => 0,
                        'unitPrice'   => 50.00,
                        'totalAmount' => 50.00,
                        'labels'      => ['A'],
                        'accountCode' => '200',
                    ],
                ],
            ]),
            ['buyer.name', 'items.0.quantity'],
        );
    }

    #[Test]
    public function it_aggregates_payment_errors_with_indexed_paths(): void
    {
        $this->assertDomainHasErrors(
            fn () => $this->build([
                'payment' => [
                    ['amount' => -1, 'paymentType' => 99],
                ],
            ]),
            ['payment.0.amount', 'payment.0.paymentType'],
        );
    }

    #[Test]
    public function it_aggregates_errors_across_multiple_buyers_items_and_payments(): void
    {
        $this->assertDomainHasErrors(
            fn () => $this->build([
                'buyer'   => ['name' => ''],
                'items'   => [
                    [
                        'code'        => 'PROD-001',
                        'name'        => 'Test Product',
                        'quantity'    => 0,
                        'unitPrice'   => 50.00,
                        'totalAmount' => 50.00,
                        'labels'      => ['A'],
                        'accountCode' => '200',
                    ],
                ],
                'payment' => [
                    ['amount' => 50.00, 'paymentType' => 1],
                    ['amount' => -1, 'paymentType' => 99],
                ],
            ]),
            [
                'buyer.name',
                'items.0.quantity',
                'payment.1.amount',
                'payment.1.paymentType',
            ],
        );
    }
}
