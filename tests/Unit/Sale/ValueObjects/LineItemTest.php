<?php

namespace Tests\Unit\Sale\ValueObjects;

use App\Modules\Sale\Domain\ValueObjects\LineItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(LineItem::class)]
class LineItemTest extends ValueObjectTestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code'        => 'PROD-001',
            'name'        => 'Test Product',
            'quantity'    => 2,
            'unitPrice'   => 50.00,
            'totalAmount' => 100.00,
            'labels'      => ['A'],
            'accountCode' => '200',
        ], $overrides);
    }

    #[Test]
    public function it_builds_a_valid_line_item(): void
    {
        $item = LineItem::fromArray($this->validPayload());

        $this->assertSame('PROD-001', $item->code);
        $this->assertSame('Test Product', $item->name);
        $this->assertSame(2.0, $item->quantity);
        $this->assertSame(50.0, $item->unitPrice);
        $this->assertSame(100.0, $item->totalAmount);
        $this->assertSame(['A'], $item->labels);
        $this->assertSame('200', $item->accountCode);
        $this->assertNull($item->gtin);
    }

    #[Test]
    public function it_casts_int_quantity_and_amounts_to_float(): void
    {
        $item = LineItem::fromArray($this->validPayload([
            'quantity'    => 2,
            'unitPrice'   => 50,
            'totalAmount' => 100,
        ]));

        $this->assertSame(2.0, $item->quantity);
        $this->assertSame(50.0, $item->unitPrice);
        $this->assertSame(100.0, $item->totalAmount);
    }

    #[Test]
    public function it_accepts_optional_gtin_when_8_to_14_digits(): void
    {
        $item = LineItem::fromArray($this->validPayload(['gtin' => '12345678']));
        $this->assertSame('12345678', $item->gtin);
    }

    #[Test]
    public function it_normalises_empty_gtin_to_null(): void
    {
        $item = LineItem::fromArray($this->validPayload(['gtin' => '']));
        $this->assertNull($item->gtin);
    }

    #[Test]
    public function it_accepts_quantity_times_unit_price_within_tolerance(): void
    {
        $item = LineItem::fromArray($this->validPayload([
            'quantity'    => 2,
            'unitPrice'   => 50.00,
            'totalAmount' => 100.00,
        ]));

        $this->assertSame(2.0, $item->quantity);
        $this->assertSame(50.0, $item->unitPrice);
        $this->assertSame(100.0, $item->totalAmount);
    }

    #[Test]
    #[DataProvider('rejectionProvider')]
    public function it_rejects_invalid_data(array $overrides, string $field, string $error): void
    {
        $this->assertDomainError(
            fn () => LineItem::fromArray($this->validPayload($overrides)),
            $field,
            $error,
        );
    }

    public static function rejectionProvider(): array
    {
        return [
            'empty code'              => [['code' => ''], 'code', LineItem::ERROR_CODE_EMPTY],
            'code too long'           => [['code' => str_repeat('a', 31)], 'code', LineItem::ERROR_CODE_TOO_LONG],
            'empty name'              => [['name' => ''], 'name', LineItem::ERROR_NAME_EMPTY],
            'name too long'           => [['name' => str_repeat('a', 2049)], 'name', LineItem::ERROR_NAME_TOO_LONG],
            'quantity zero'           => [['quantity' => 0], 'quantity', LineItem::ERROR_QUANTITY_INVALID],
            'quantity negative'       => [['quantity' => -1], 'quantity', LineItem::ERROR_QUANTITY_INVALID],
            'quantity above max'      => [['quantity' => 1_000_000], 'quantity', LineItem::ERROR_QUANTITY_TOO_LARGE],
            'unit price negative'     => [['unitPrice' => -1], 'unitPrice', LineItem::ERROR_UNIT_PRICE_NEGATIVE],
            'unit price above max'    => [['unitPrice' => 1_000_000_000], 'unitPrice', LineItem::ERROR_UNIT_PRICE_TOO_LARGE],
            'total amount negative'   => [['totalAmount' => -10], 'totalAmount', LineItem::ERROR_TOTAL_AMOUNT_NEGATIVE],
            'total amount above max'  => [['totalAmount' => 1_000_000_000], 'totalAmount', LineItem::ERROR_TOTAL_AMOUNT_TOO_LARGE],
            'total amount mismatch'   => [['totalAmount' => 999.00], 'totalAmount', LineItem::ERROR_TOTAL_MISMATCH],
            'empty labels'            => [['labels' => []], 'labels', LineItem::ERROR_LABELS_EMPTY],
            'label not in whitelist'  => [['labels' => ['Z']], 'labels', LineItem::ERROR_LABEL_INVALID],
            'label is not a string'   => [['labels' => [1]], 'labels', LineItem::ERROR_LABEL_INVALID],
            'empty account code'      => [['accountCode' => ''], 'accountCode', LineItem::ERROR_ACCOUNT_CODE_EMPTY],
            'account code too long'   => [['accountCode' => str_repeat('1', 11)], 'accountCode', LineItem::ERROR_ACCOUNT_CODE_TOO_LONG],
            'invalid gtin (letters)'  => [['gtin' => 'abc12345'], 'gtin', LineItem::ERROR_GTIN_INVALID],
            'invalid gtin (too short)'=> [['gtin' => '1234567'], 'gtin', LineItem::ERROR_GTIN_INVALID],
            'invalid gtin (too long)' => [['gtin' => str_repeat('1', 15)], 'gtin', LineItem::ERROR_GTIN_INVALID],
        ];
    }

    #[Test]
    public function it_accepts_all_whitelisted_labels(): void
    {
        foreach (LineItem::VALID_LABELS as $label) {
            $item = LineItem::fromArray($this->validPayload(['labels' => [$label]]));
            $this->assertSame([$label], $item->labels);
        }
    }

    #[Test]
    public function it_collects_multiple_errors_at_once(): void
    {
        $this->assertDomainHasErrors(
            fn () => LineItem::fromArray([
                'code'        => '',
                'name'        => '',
                'quantity'    => 0,
                'unitPrice'   => -1,
                'totalAmount' => -1,
                'labels'      => [],
                'accountCode' => '',
            ]),
            ['code', 'name', 'quantity', 'unitPrice', 'totalAmount', 'labels', 'accountCode'],
        );
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $item = LineItem::fromArray($this->validPayload());

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('readonly');

        /** @phpstan-ignore-next-line — intentionally violating immutability */
        $item->code = 'HACKED';
    }
}
