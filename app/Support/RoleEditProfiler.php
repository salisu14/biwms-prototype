<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RoleEditProfiler
{
    /**
     * @var array<string, mixed>
     */
    private static array $metrics = [];

    private static bool $initialized = false;

    private static bool $touched = false;

    private static bool $queryListenerRegistered = false;

    public static function enabled(): bool
    {
        return (bool) config('app.role_edit_profile', false);
    }

    public static function reset(): void
    {
        self::$metrics = [];
        self::$initialized = false;
        self::$touched = false;
    }

    public static function startRequest(string $path, string $method): void
    {
        if (! self::enabled() || self::$initialized) {
            return;
        }

        self::$initialized = true;
        self::$metrics = [
            'path' => $path,
            'method' => $method,
            'request_started_at' => hrtime(true),
            'query_count' => 0,
            'query_time_ms' => 0.0,
            'duplicate_queries' => [],
            'phases' => [],
            'memory_start_bytes' => memory_get_usage(true),
        ];

        if (! self::$queryListenerRegistered) {
            DB::listen(function ($query): void {
                if (! self::$initialized) {
                    return;
                }

                self::$metrics['query_count']++;
                self::$metrics['query_time_ms'] += (float) $query->time;

                $signature = preg_replace('/\s+/', ' ', (string) $query->sql) ?: (string) $query->sql;
                self::$metrics['duplicate_queries'][$signature] = (self::$metrics['duplicate_queries'][$signature] ?? 0) + 1;
            });

            self::$queryListenerRegistered = true;
        }
    }

    public static function touch(): void
    {
        if (! self::enabled()) {
            return;
        }

        self::$touched = true;
    }

    public static function measure(string $phase, callable $callback): mixed
    {
        if (! self::enabled()) {
            return $callback();
        }

        self::touch();
        $startedAt = hrtime(true);
        $memoryBefore = memory_get_usage(true);

        try {
            return $callback();
        } finally {
            $elapsedMs = self::elapsedMs($startedAt);
            self::$metrics['phases'][$phase]['count'] = (self::$metrics['phases'][$phase]['count'] ?? 0) + 1;
            self::$metrics['phases'][$phase]['time_ms'] = round((self::$metrics['phases'][$phase]['time_ms'] ?? 0) + $elapsedMs, 3);
            self::$metrics['phases'][$phase]['memory_delta_bytes'] = (self::$metrics['phases'][$phase]['memory_delta_bytes'] ?? 0)
                + (memory_get_usage(true) - $memoryBefore);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function mark(string $key, array $metadata): void
    {
        if (! self::enabled()) {
            return;
        }

        self::touch();
        self::$metrics[$key] = $metadata;
    }

    public static function finish(int $statusCode, ?int $responseBytes = null): void
    {
        if (! self::enabled() || ! self::$initialized) {
            return;
        }

        if (! self::$touched && ! str_starts_with((string) (self::$metrics['path'] ?? ''), 'admin/roles')) {
            self::reset();

            return;
        }

        $duplicateQueries = collect(self::$metrics['duplicate_queries'] ?? [])
            ->filter(fn (int $count): bool => $count > 1)
            ->sortDesc()
            ->take(10)
            ->all();

        $payload = [
            'status' => $statusCode,
            'path' => self::$metrics['path'] ?? null,
            'method' => self::$metrics['method'] ?? null,
            'total_time_ms' => round(self::elapsedMs((int) self::$metrics['request_started_at']), 3),
            'query_count' => self::$metrics['query_count'] ?? 0,
            'query_time_ms' => round((float) (self::$metrics['query_time_ms'] ?? 0), 3),
            'duplicate_queries' => $duplicateQueries,
            'response_bytes' => $responseBytes,
            'memory_start_mb' => self::bytesToMb((int) (self::$metrics['memory_start_bytes'] ?? 0)),
            'memory_peak_mb' => self::bytesToMb(memory_get_peak_usage(true)),
            'memory_current_mb' => self::bytesToMb(memory_get_usage(true)),
            'phases' => self::$metrics['phases'] ?? [],
            'metadata' => collect(self::$metrics)
                ->except(['request_started_at', 'query_count', 'query_time_ms', 'duplicate_queries', 'phases', 'memory_start_bytes', 'path', 'method'])
                ->all(),
        ];

        try {
            Log::info('ROLE_EDIT_PROFILE', $payload);
        } finally {
            self::reset();
        }
    }

    public static function finishWithException(Throwable $exception): void
    {
        if (! self::enabled() || ! self::$initialized) {
            return;
        }

        self::mark('exception', [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        self::finish(500);
    }

    private static function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    private static function bytesToMb(int $bytes): float
    {
        return round($bytes / 1024 / 1024, 3);
    }
}
