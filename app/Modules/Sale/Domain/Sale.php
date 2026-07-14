<?php

namespace App\Modules\Sale\Domain;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Sale\Domain\ValueObjects\BuyerData;
use App\Modules\Sale\Domain\ValueObjects\LineItem;
use App\Modules\Sale\Domain\ValueObjects\Payment;
use App\Shared\Domain\BaseEntity;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends BaseEntity
{
    use HasFactory, GettersAndSetters;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const INVOICE_TYPE_NORMAL = 0;
    public const INVOICE_TYPE_PROFORMA = 1;
    public const INVOICE_TYPE_COPY = 2;
    public const INVOICE_TYPE_TRAINING = 3;
    public const INVOICE_TYPE_ADVANCE = 4;

    public const TRANSACTION_TYPE_SALE = 0;
    public const TRANSACTION_TYPE_REFUND = 1;

    public const VALID_INVOICE_TYPES = [
        self::INVOICE_TYPE_NORMAL,
        self::INVOICE_TYPE_PROFORMA,
        self::INVOICE_TYPE_COPY,
        self::INVOICE_TYPE_TRAINING,
        self::INVOICE_TYPE_ADVANCE,
    ];

    public const VALID_TRANSACTION_TYPES = [
        self::TRANSACTION_TYPE_SALE,
        self::TRANSACTION_TYPE_REFUND,
    ];

    public const ERROR_ITEMS_EMPTY = 'A sale must contain at least one line item.';
    public const ERROR_PAYMENTS_EMPTY = 'A sale must contain at least one payment.';
    public const ERROR_INVOICE_TYPE_INVALID = 'The invoice type must be one of: 0, 1, 2, 3, 4.';
    public const ERROR_TRANSACTION_TYPE_INVALID = 'The transaction type must be one of: 0 (Sale), 1 (Refund).';

    protected $table = 'sales';

    protected $fillable = [
        'organization_id',
        'connector_id',
        'status',
        'payload',
        'xero_invoice_id',
        'xero_result',
        'fiscal_number',
        'fiscal_result',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'xero_result'  => 'array',
        'fiscal_result' => 'array',
        'processed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function connector()
    {
        return $this->belongsTo(Connector::class);
    }

    /**
     * Build a fully-validated Sale aggregate from a raw payload.
     *
     * Delegates intra-aggregate validation to the corresponding Value Objects and
     * applies Sale-level structural rules. Cross-aggregate invariants (e.g. the
     * total of items matching the total of payments) are intentionally NOT
     * enforced here yet — they belong to a later iteration of the validation
     * plan and are guarded by the HTTP layer (FormRequest) for the time being.
     *
     * @param  int                   $organizationId
     * @param  int                   $connectorId
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainValidationException when any Sale-level structural rule fails
     *                                   or any contained Value Object is invalid.
     */
    public static function fromPayload(int $organizationId, int $connectorId, array $payload): self
    {
        $errors = [];

        $invoiceType = isset($payload['invoiceType']) ? (int) $payload['invoiceType'] : -1;
        $transactionType = isset($payload['transactionType']) ? (int) $payload['transactionType'] : -1;

        if (! in_array($invoiceType, self::VALID_INVOICE_TYPES, true)) {
            $errors['invoiceType'][] = self::ERROR_INVOICE_TYPE_INVALID;
        }

        if (! in_array($transactionType, self::VALID_TRANSACTION_TYPES, true)) {
            $errors['transactionType'][] = self::ERROR_TRANSACTION_TYPE_INVALID;
        }

        $rawItems = $payload['items'] ?? null;
        $rawPayments = $payload['payment'] ?? null;

        if (! is_array($rawItems) || $rawItems === []) {
            $errors['items'][] = self::ERROR_ITEMS_EMPTY;
        }

        if (! is_array($rawPayments) || $rawPayments === []) {
            $errors['payment'][] = self::ERROR_PAYMENTS_EMPTY;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid sale payload.', $errors);
        }

        // Build Value Objects — each one validates its own slice and may throw.
        try {
            $buyer = BuyerData::fromArray($payload['buyer'] ?? []);
        } catch (DomainValidationException $e) {
            foreach ($e->getErrors() as $field => $messages) {
                $errors["buyer.{$field}"] = $messages;
            }
            $buyer = null;
        }

        $items = [];
        foreach ($rawItems as $index => $rawItem) {
            try {
                $items[] = LineItem::fromArray(is_array($rawItem) ? $rawItem : []);
            } catch (DomainValidationException $e) {
                foreach ($e->getErrors() as $field => $messages) {
                    $errors["items.{$index}.{$field}"] = $messages;
                }
            }
        }

        $payments = [];
        foreach ($rawPayments as $index => $rawPayment) {
            try {
                $payments[] = Payment::fromArray(is_array($rawPayment) ? $rawPayment : []);
            } catch (DomainValidationException $e) {
                foreach ($e->getErrors() as $field => $messages) {
                    $errors["payment.{$index}.{$field}"] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid sale payload.', $errors);
        }

        if ($buyer === null) {
            // Unreachable under the validation above, but keeps the type system happy.
            throw new DomainValidationException('Invalid sale payload.', ['buyer' => ['Invalid buyer.']]);
        }

        $sale = new self;
        $sale->setOrganizationId($organizationId);
        $sale->setConnectorId($connectorId);
        $sale->setStatus(self::STATUS_PENDING);
        $sale->setPayload($payload);
        $sale->setAttempts(0);

        return $sale;
    }
}
