<?php

declare(strict_types=1);

namespace App\Accounting;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

final readonly class PostingIntent
{
    /**
     * @param  array<string, mixed>  $dimensions
     * @param  array<int, PostingIntentLine>  $lines
     */
    public function __construct(
        public ?int $businessId,
        public CarbonImmutable $postingDate,
        public CarbonImmutable $documentDate,
        public string $sourceModule,
        public string $sourceType,
        public ?int $sourceId,
        public string $sourceNumber,
        public string $documentType,
        public string $documentNumber,
        public string $transactionKey,
        public string $idempotencyKey,
        public string $description,
        public string $currencyCode,
        public string $exchangeRate,
        public array $dimensions,
        public array $lines,
        public ?int $actorId = null,
        public ?string $externalDocumentNumber = null,
        public ?string $journalBatchName = null,
        public ?string $registerNumber = null,
        public ?int $reversalOfTransactionId = null,
        public ?string $reason = null,
    ) {}

    /**
     * @param  array{
     *     business_id?: int|null,
     *     posting_date: DateTimeInterface|string,
     *     document_date?: DateTimeInterface|string|null,
     *     source_module: string,
     *     source_type: string,
     *     source_id?: int|null,
     *     source_number: string,
     *     document_type?: string,
     *     document_number?: string,
     *     transaction_key?: string,
     *     idempotency_key?: string,
     *     description?: string,
     *     currency_code?: string,
     *     exchange_rate?: string|int|float,
     *     dimensions?: array<string, mixed>,
     *     lines: array<int, PostingIntentLine|array<string, mixed>>,
     *     actor_id?: int|null,
     *     external_document_number?: string|null,
     *     journal_batch_name?: string|null,
     *     register_number?: string|null,
     *     reversal_of_transaction_id?: int|null,
     *     reason?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $documentNumber = (string) ($data['document_number'] ?? $data['source_number']);
        $transactionKey = (string) ($data['transaction_key'] ?? "{$data['source_module']}:{$data['source_type']}:{$documentNumber}");

        return new self(
            businessId: isset($data['business_id']) ? (int) $data['business_id'] : null,
            postingDate: CarbonImmutable::parse($data['posting_date']),
            documentDate: CarbonImmutable::parse($data['document_date'] ?? $data['posting_date']),
            sourceModule: (string) $data['source_module'],
            sourceType: (string) $data['source_type'],
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            sourceNumber: (string) $data['source_number'],
            documentType: (string) ($data['document_type'] ?? $data['source_type']),
            documentNumber: $documentNumber,
            transactionKey: $transactionKey,
            idempotencyKey: (string) ($data['idempotency_key'] ?? hash('sha256', $transactionKey)),
            description: (string) ($data['description'] ?? $documentNumber),
            currencyCode: (string) ($data['currency_code'] ?? 'NGN'),
            exchangeRate: (string) ($data['exchange_rate'] ?? '1'),
            dimensions: $data['dimensions'] ?? [],
            lines: Collection::make($data['lines'])
                ->map(fn (PostingIntentLine|array $line): PostingIntentLine => $line instanceof PostingIntentLine ? $line : PostingIntentLine::fromArray($line))
                ->all(),
            actorId: isset($data['actor_id']) ? (int) $data['actor_id'] : null,
            externalDocumentNumber: $data['external_document_number'] ?? null,
            journalBatchName: $data['journal_batch_name'] ?? null,
            registerNumber: $data['register_number'] ?? null,
            reversalOfTransactionId: isset($data['reversal_of_transaction_id']) ? (int) $data['reversal_of_transaction_id'] : null,
            reason: $data['reason'] ?? null,
        );
    }
}
