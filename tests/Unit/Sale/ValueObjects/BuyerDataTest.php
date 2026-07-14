<?php

namespace Tests\Unit\Sale\ValueObjects;

use App\Modules\Sale\Domain\ValueObjects\BuyerData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(BuyerData::class)]
class BuyerDataTest extends ValueObjectTestCase
{
    #[Test]
    public function it_builds_a_valid_buyer_with_name_and_id(): void
    {
        $buyer = BuyerData::fromArray([
            'name' => 'Empresa ABC d.o.o.',
            'id'   => '101234567',
        ]);

        $this->assertSame('Empresa ABC d.o.o.', $buyer->name);
        $this->assertSame('101234567', $buyer->id);
    }

    #[Test]
    public function it_trims_surrounding_whitespace_from_name(): void
    {
        $buyer = BuyerData::fromArray([
            'name' => '   Empresa ABC   ',
            'id'   => null,
        ]);

        $this->assertSame('Empresa ABC', $buyer->name);
    }

    #[Test]
    public function it_normalises_empty_string_id_to_null(): void
    {
        $buyer = BuyerData::fromArray([
            'name' => 'Test Buyer',
            'id'   => '',
        ]);

        $this->assertNull($buyer->id);
    }

    #[Test]
    public function it_normalises_missing_id_to_null(): void
    {
        $buyer = BuyerData::fromArray([
            'name' => 'Test Buyer',
        ]);

        $this->assertNull($buyer->id);
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function it_rejects_invalid_names(string $name, string $expectedError): void
    {
        $this->assertDomainError(
            fn () => BuyerData::fromArray(['name' => $name]),
            'name',
            $expectedError,
        );
    }

    public static function invalidNameProvider(): array
    {
        return [
            'empty string'       => ['', BuyerData::ERROR_NAME_EMPTY],
            'only whitespace'    => ['     ', BuyerData::ERROR_NAME_EMPTY],
            'exceeds max length' => [str_repeat('a', 256), BuyerData::ERROR_NAME_TOO_LONG],
        ];
    }

    #[Test]
    public function it_rejects_missing_name(): void
    {
        $this->assertDomainError(
            fn () => BuyerData::fromArray([]),
            'name',
            BuyerData::ERROR_NAME_EMPTY,
        );
    }

    #[Test]
    #[DataProvider('invalidIdProvider')]
    public function it_rejects_invalid_ids(mixed $id, string $expectedError): void
    {
        $this->assertDomainError(
            fn () => BuyerData::fromArray(['name' => 'Test Buyer', 'id' => $id]),
            'id',
            $expectedError,
        );
    }

    public static function invalidIdProvider(): array
    {
        return [
            'contains letters'  => ['ABC123456', BuyerData::ERROR_ID_INVALID],
            'too short (7)'     => ['1234567', BuyerData::ERROR_ID_INVALID],
            'too long (21)'     => [str_repeat('1', 21), BuyerData::ERROR_ID_INVALID],
            'contains spaces'   => ['123 45678', BuyerData::ERROR_ID_INVALID],
        ];
    }

    #[Test]
    public function it_collects_multiple_errors_at_once(): void
    {
        $this->assertDomainHasErrors(
            fn () => BuyerData::fromArray(['name' => '', 'id' => 'invalid']),
            ['name', 'id'],
        );
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $buyer = BuyerData::fromArray([
            'name' => 'Test Buyer',
            'id'   => '12345678',
        ]);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('readonly');

        /** @phpstan-ignore-next-line — intentionally violating immutability */
        $buyer->name = 'Hacked';
    }
}
