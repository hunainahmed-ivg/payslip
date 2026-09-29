<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;

class VirtuoHRWebhookVerifier
{
    public const COMPANY_HEADER = 'X-Payslip-Company-Id';

    /**
     * @return array{valid:bool,status:int,message:string,timestamp:int|null,company_id:int|null}
     */
    public function verify(Request $request): array
    {
        $company = $this->resolveCompany($request);
        $secret = $company?->webhook_secret;

        if ($secret === null || $secret === '') {
            $secret = (string) config('services.virtuohr.webhook_secret', '');
        }

        if ($secret === '') {
            return $this->fail(503, 'Webhook secret is not configured for this company.', $company?->id);
        }

        $sigHeader = (string) config('services.virtuohr.webhook_signature_header', 'X-VirtuoHR-Signature');
        $tsHeader = (string) config('services.virtuohr.webhook_timestamp_header', 'X-VirtuoHR-Timestamp');

        $rawBody = $request->getContent();
        $provided = trim((string) $request->header($sigHeader, ''));

        if ($provided === '') {
            return $this->fail(401, 'Missing signature header: '.$sigHeader, $company?->id);
        }

        if (stripos($provided, 'sha256=') === 0) {
            $provided = substr($provided, 7);
        }
        $provided = strtolower($provided);

        $timestamp = null;
        $tsRaw = $request->header($tsHeader);

        if ($tsRaw !== null && $tsRaw !== '') {
            if (! is_numeric($tsRaw) || (int) $tsRaw <= 0) {
                return $this->fail(400, 'Invalid timestamp header: '.$tsHeader, $company?->id);
            }

            $timestamp = (int) $tsRaw;
            $tolerance = (int) config('services.virtuohr.webhook_tolerance', 300);

            if (abs(time() - $timestamp) > $tolerance) {
                return $this->fail(408, 'Signature timestamp outside allowed window (replay protection).', $company?->id);
            }
        }

        $signedPayload = $timestamp !== null ? $timestamp.'.'.$rawBody : $rawBody;
        $expected = strtolower(hash_hmac('sha256', $signedPayload, $secret));

        if (! hash_equals($expected, $provided)) {
            return $this->fail(401, 'Signature mismatch.', $company?->id);
        }

        return [
            'valid' => true,
            'status' => 200,
            'message' => 'ok',
            'timestamp' => $timestamp,
            'company_id' => $company?->id,
        ];
    }

    private function resolveCompany(Request $request): ?CompanyProfile
    {
        $raw = trim((string) $request->header(self::COMPANY_HEADER, ''));

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return CompanyProfile::query()
            ->where('id', (int) $raw)
            ->where('is_active', true)
            ->first();
    }

    private function fail(int $status, string $message, ?int $companyId): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'message' => $message,
            'timestamp' => null,
            'company_id' => $companyId,
        ];
    }
}
