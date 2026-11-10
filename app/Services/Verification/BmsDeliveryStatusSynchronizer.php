<?php

namespace App\Services\Verification;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BmsDeliveryStatusSynchronizer
{
    private const TERMINAL_STATUSES = [
        'DELIVERED',
        'UNDELIVERED',
        'FAILED',
        'REJECTED',
    ];

    /**
     * @return array{checked: int, resolved: int, pending: int, failed: int, expired: int}
     */
    public function syncPending(?int $limit = null): array
    {
        $apiKey = (string) config('medipath.phone_verification.bms.api_key');
        $endpoint = rtrim((string) config('medipath.phone_verification.bms.report_endpoint'), '/');

        if ($apiKey === '' || $endpoint === '') {
            return ['checked' => 0, 'resolved' => 0, 'pending' => 0, 'failed' => 1, 'expired' => 0];
        }

        $trackingHours = max(1, min(
            (int) config('medipath.phone_verification.bms.delivery_tracking_hours', 48),
            168,
        ));
        $cutoff = now()->subHours($trackingHours);
        $expired = DB::table('verification_delivery_events')
            ->where('provider', 'bms')
            ->where('outcome', 'ACCEPTED')
            ->whereNull('delivery_resolved_at')
            ->where('created_at', '<', $cutoff)
            ->update([
                'delivery_status' => 'UNKNOWN',
                'delivery_resolved_at' => now(),
                'delivery_error_code' => 'BMS_REPORT_WINDOW_EXPIRED',
            ]);

        $batchSize = max(1, min(
            $limit ?? (int) config('medipath.phone_verification.bms.delivery_batch_size', 50),
            200,
        ));

        /** @var array<int, string> $campaigns */
        $campaigns = DB::table('verification_delivery_events')
            ->where('provider', 'bms')
            ->where('outcome', 'ACCEPTED')
            ->whereNotNull('provider_reference')
            ->whereNull('delivery_resolved_at')
            ->where('created_at', '>=', $cutoff)
            ->orderBy('created_at')
            ->limit($batchSize)
            ->pluck('provider_reference', 'id')
            ->mapWithKeys(fn (mixed $reference, mixed $id): array => [(int) $id => (string) $reference])
            ->all();

        $summary = ['checked' => 0, 'resolved' => 0, 'pending' => 0, 'failed' => 0, 'expired' => $expired];

        foreach ($campaigns as $eventId => $campaignId) {
            $summary['checked']++;
            $result = $this->fetchStatus($endpoint, $apiKey, $campaignId);

            if ($result['status'] === null) {
                $summary['failed']++;
                $this->recordCheck($eventId, null, $result['error_code']);

                continue;
            }

            $isResolved = in_array($result['status'], self::TERMINAL_STATUSES, true);
            $summary[$isResolved ? 'resolved' : 'pending']++;
            $this->recordCheck($eventId, $result['status'], null, $isResolved);
        }

        return $summary;
    }

    /**
     * @return array{status: string|null, error_code: string|null}
     */
    private function fetchStatus(string $endpoint, string $apiKey, string $campaignId): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $campaignId) !== 1) {
            return ['status' => null, 'error_code' => 'BMS_INVALID_CAMPAIGN_REFERENCE'];
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('medipath.phone_verification.bms.connect_timeout', 5))
                ->timeout((int) config('medipath.phone_verification.bms.timeout', 10))
                ->withQueryParameters(['key' => $apiKey])
                ->get($endpoint.'/'.rawurlencode($campaignId));
        } catch (ConnectionException $exception) {
            Log::warning('BMS delivery report connection failed.', [
                'exception' => $exception::class,
            ]);

            return ['status' => null, 'error_code' => 'BMS_REPORT_CONNECTION_FAILED'];
        }

        if (! $response->successful()) {
            return ['status' => null, 'error_code' => 'BMS_REPORT_HTTP_'.$response->status()];
        }

        if ($response->json('status') !== 'success') {
            return ['status' => null, 'error_code' => 'BMS_REPORT_REJECTED'];
        }

        $status = $this->statusFrom($response);

        return $status === null
            ? ['status' => null, 'error_code' => 'BMS_REPORT_NOT_READY']
            : ['status' => $status, 'error_code' => null];
    }

    private function statusFrom(Response $response): ?string
    {
        $report = $response->json('report');

        if (! is_array($report)) {
            return null;
        }

        $statuses = [];

        foreach ($report as $row) {
            if (! is_array($row) || ! is_string($row['status'] ?? null)) {
                continue;
            }

            $status = strtoupper(trim($row['status']));

            if (in_array($status, [...self::TERMINAL_STATUSES, 'SUBMITTED'], true)) {
                $statuses[] = $status;
            }
        }

        foreach (['REJECTED', 'FAILED', 'UNDELIVERED'] as $failureStatus) {
            if (in_array($failureStatus, $statuses, true)) {
                return $failureStatus;
            }
        }

        if ($statuses !== [] && count(array_unique($statuses)) === 1) {
            return $statuses[0];
        }

        return in_array('SUBMITTED', $statuses, true) ? 'SUBMITTED' : null;
    }

    private function recordCheck(
        int $eventId,
        ?string $status,
        ?string $errorCode,
        bool $resolved = false,
    ): void {
        DB::table('verification_delivery_events')
            ->where('id', $eventId)
            ->whereNull('delivery_resolved_at')
            ->update([
                'delivery_status' => $status ?? DB::raw('delivery_status'),
                'delivery_check_count' => DB::raw('delivery_check_count + 1'),
                'delivery_status_checked_at' => now(),
                'delivery_resolved_at' => $resolved ? now() : null,
                'delivery_error_code' => $errorCode,
            ]);
    }
}
