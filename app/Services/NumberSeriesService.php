<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingNumberSeriesException;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BC equivalent: Codeunit 396 "NoSeriesManagement"
 * Handles document numbering with series, gaps, and date ranges
 */
class NumberSeriesService
{
    private const CACHE_TTL = 3600; // 1 hour

    private bool $manualNumberingAllowed = false;

    private bool $warningNoGaps = false;

    /**
     * Get next number from series (BC: GetNextNo)
     *
     * @param  string  $seriesCode  e.g., 'S-SHIP', 'S-ORD', 'S-INV'
     * @param  bool  $modifySeries  Whether to actually increment the series
     *
     * @throws MissingNumberSeriesException
     */
    public function getNextNo(
        string $seriesCode,
        ?DateTimeInterface $postingDate = null,
        bool $modifySeries = true
    ): string {
        $postingDate = $postingDate ?? now();

        return DB::transaction(function () use ($seriesCode, $postingDate, $modifySeries) {

            $series = NumberSeries::where('code', $seriesCode)
                ->lockForUpdate()
                ->first();

            if (! $series) {
                throw new MissingNumberSeriesException("Number Series {$seriesCode} does not exist", [$seriesCode]);
            }

            if (! $series->is_active) {
                throw new MissingNumberSeriesException("Number Series {$seriesCode} is inactive", [$seriesCode]);
            }

            // Check date validity (BC: CheckValidDate)
            $this->validateDateInRange($series, $postingDate);

            // Get appropriate line based on date
            $line = $this->getSeriesLine($series, $postingDate);

            if (! $line) {
                throw new MissingNumberSeriesException("No open Number Series Line exists for date {$postingDate->format('Y-m-d')}", [$seriesCode]);
            }

            $this->validateLineHasNextNumber($seriesCode, $line);

            // Generate next number
            $nextNo = $this->formatNumber($line);

            if ($modifySeries) {
                $this->incrementSeries($line);
            }

            Log::debug('Number Series generated', [
                'series' => $seriesCode,
                'number' => $nextNo,
                'date' => $postingDate,
            ]);

            return $nextNo;
        });
    }

    /**
     * Try to get next number, return null if series invalid (BC: TryGetNextNo)
     */
    public function tryGetNextNo(
        string $seriesCode,
        ?DateTimeInterface $postingDate = null
    ): ?string {
        try {
            return $this->getNextNo($seriesCode, $postingDate);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $seriesCodes
     */
    public function getNextNoFromSeries(array $seriesCodes, ?DateTimeInterface $postingDate = null, ?string $description = null): string
    {
        $errors = [];

        foreach ($seriesCodes as $seriesCode) {
            try {
                return $this->getNextNo($seriesCode, $postingDate);
            } catch (MissingNumberSeriesException $exception) {
                $errors[$seriesCode] = $exception->getMessage();
            }
        }

        $label = $description ? "{$description} " : '';

        throw new MissingNumberSeriesException(
            "Missing Number Series: {$label}".implode(', ', $seriesCodes).'. '.implode(' ', $errors),
            $seriesCodes,
        );
    }

    /**
     * Peek next number without consuming it (BC: PreviewNextNo)
     */
    public function previewNextNo(string $seriesCode): ?string
    {
        return $this->getNextNo($seriesCode, null, false);
    }

    /**
     * Set manual numbering mode (BC: SetManualNumberingAllowed)
     */
    public function setManualNumberingAllowed(bool $allowed): void
    {
        $this->manualNumberingAllowed = $allowed;
    }

    /**
     * Validate if number exists in series (BC: TestManual)
     */
    public function validateManualNumber(string $seriesCode, string $manualNo): bool
    {
        if (! $this->manualNumberingAllowed) {
            throw new \RuntimeException("Manual numbering not allowed for series {$seriesCode}");
        }

        $series = NumberSeries::where('code', $seriesCode)->first();
        if (! $series) {
            return false;
        }

        // Check if number matches pattern
        if (! $this->matchesSeriesPattern($manualNo, $series)) {
            throw new \RuntimeException("Number {$manualNo} does not match series pattern");
        }

        // Check uniqueness
        if ($this->numberExistsInSeries($series, $manualNo)) {
            throw new \RuntimeException("Number {$manualNo} already exists");
        }

        return true;
    }

    /**
     * Get last used number for series
     */
    public function getLastNoUsed(string $seriesCode): ?string
    {
        $line = NumberSeriesLine::whereHas('series', fn ($q) => $q->where('code', $seriesCode))
            ->where('starting_date', '<=', now())
            ->orderBy('starting_date', 'desc')
            ->first();

        return $line?->last_no_used;
    }

    /**
     * Simulate number usage (BC: SimulateGetNextNo)
     */
    public function simulateGetNextNo(string $seriesCode, int $count = 1): array
    {
        $numbers = [];
        $currentLine = $this->getSeriesLine(
            NumberSeries::where('code', $seriesCode)->first(),
            now()
        );

        $lastNo = $currentLine?->last_no_used;

        for ($i = 0; $i < $count; $i++) {
            $lastNo = $this->incrementNumber($lastNo, $currentLine);
            $numbers[] = $this->formatNumberFromString($lastNo, $currentLine);
        }

        return $numbers;
    }

    private function getSeriesLine(NumberSeries $series, DateTimeInterface $date): ?NumberSeriesLine
    {
        return NumberSeriesLine::where('number_series_id', $series->id)
            ->where('starting_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('ending_date')
                    ->orWhere('ending_date', '>=', $date);
            })
            ->where('blocked', false)
            ->orderBy('starting_date', 'desc')
            ->lockForUpdate()
            ->first();
    }

    private function formatNumber(NumberSeriesLine $line): string
    {
        $prefix = $line->prefix ?? '';
        $suffix = $line->suffix ?? '';
        $lastNo = $this->getCurrentNumberBeforeNext($line);
        $increment = $line->increment_by ?? 1;

        $nextNumber = $lastNo + $increment;

        // Pad with zeros
        $numberStr = str_pad((string) $nextNumber, $line->no_of_digits ?? 5, '0', STR_PAD_LEFT);

        return $prefix.$numberStr.$suffix;
    }

    private function formatNumberFromString(?string $lastNo, NumberSeriesLine $line): string
    {
        $prefix = $line->prefix ?? '';
        $suffix = $line->suffix ?? '';
        $number = $lastNo ? (int) $lastNo : $this->getCurrentNumberBeforeNext($line);

        $numberStr = str_pad((string) $number, $line->no_of_digits ?? 5, '0', STR_PAD_LEFT);

        return $prefix.$numberStr.$suffix;
    }

    private function incrementSeries(NumberSeriesLine $line): void
    {
        $current = $this->getCurrentNumberBeforeNext($line);
        $line->update(['last_no_used' => $current + ($line->increment_by ?? 1)]);
    }

    private function incrementNumber(?string $lastNo, ?NumberSeriesLine $line): string
    {
        $current = $lastNo ? (int) $lastNo : ($line ? $this->getCurrentNumberBeforeNext($line) : 0);

        return (string) ($current + ($line->increment_by ?? 1));
    }

    private function getCurrentNumberBeforeNext(NumberSeriesLine $line): int
    {
        if ($line->last_no_used !== null) {
            return (int) $line->last_no_used;
        }

        if ($line->starting_no !== null) {
            return (int) $line->starting_no - (int) ($line->increment_by ?? 1);
        }

        return 0;
    }

    private function validateDateInRange(NumberSeries $series, DateTimeInterface $date): void
    {
        if ($series->starting_date && $date < $series->starting_date) {
            throw new \RuntimeException('Posting date before series starting date');
        }
        if ($series->ending_date && $date > $series->ending_date) {
            throw new \RuntimeException('Posting date after series ending date');
        }
    }

    private function validateLineHasNextNumber(string $seriesCode, NumberSeriesLine $line): void
    {
        $current = $this->getCurrentNumberBeforeNext($line);
        $next = $current + ($line->increment_by ?? 1);

        if ($line->ending_no !== null && (int) $line->ending_no > 0 && $next > (int) $line->ending_no) {
            throw new \RuntimeException("Number Series {$seriesCode} is exhausted");
        }
    }

    private function matchesSeriesPattern(string $number, NumberSeries $series): bool
    {
        // Implement pattern matching based on series setup
        $pattern = '/^'.preg_quote($series->default_prefix ?? '', '/').'\d+'.preg_quote($series->default_suffix ?? '', '/').'$/';

        return preg_match($pattern, $number);
    }

    private function numberExistsInSeries(NumberSeries $series, string $number): bool
    {
        // Check against posted documents
        // This would check SalesShipmentHeader, SalesInvoice, etc.
        return false; // Implement based on your document tables
    }
}
