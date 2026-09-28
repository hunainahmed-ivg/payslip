<?php

namespace App\Services;

use Illuminate\Http\Request;

class VirtuoHRWebhookVerifier
{
    /**
     * Verify the HMAC signature (and optional signed timestamp) on an inbound webhook.
     *
     * Canonical signed string:
     *   - if timestamp header present: "{timestamp}.{rawBody}"
     *   - else: "{rawBody}"
     * Signature header value: "sha256=<hex>" (bare hex also accepted).
     *
     * @return array{valid:bool,status:int,message:string,timestamp:int|null}
     */
    public function verify(Request $request): array
    {
        $secret = (string) config('services.virtuohr.webhook_secret', '');

        if ($secret === '') {
            return $this->fail(503, 'Webhook secret is not configured (VIRTUOHR_WEBHOOK_SECRET).');
        }

        $sigHeader = (string) config('services.virtuohr.webhook_signature_header', 'X-VirtuoHR-Signature');
        $tsHeader = (string) config('services.virtuohr.webhook_timestamp_header', 'X-VirtuoHR-Timestamp');

        $rawBody = $request->getContent();
        $provided = trim((string) $request->header($sigHeader, ''));

        if ($provided === '') {
            return $this->fail(401, 'Missing signature header: '.$sigHeader);
        }

        // Normalise "sha256=abcd..." -> "abcd"
        if (stripos($provided, 'sha256=') === 0) {
            $provided = substr($provided, 7);
        }
        $provided = strtolower($provided);

        $timestamp = null;
        $tsRaw = $request->header($tsHeader);

        if ($tsRaw !== null && $tsRaw !== '') {
            if (! is_numeric($tsRaw) || (int) $tsRaw <= 0) {
                return $this->fail(400, 'Invalid timestamp header: '.$tsHeader);
            }

            $timestamp = (int) $tsRaw;
            $tolerance = (int) config('services.virtuohr.webhook_tolerance', 300);

            if (abs(time() - $timestamp) > $tolerance) {
                return $this->fail(408, 'Signature timestamp outside allowed window (replay protection).');
            }
        }

        $signedPayload = $timestamp !== null ? $timestamp.'.'.$rawBody : $rawBody;
        $expected = strtolower(hash_hmac('sha256', $signedPayload, $secret));

        if (! hash_equals($expected, $provided)) {
            return $this->fail(401, 'Signature mismatch.');
        }

        return [
            'valid' => true,
            'status' => 200,
            'message' => 'ok',
            'timestamp' => $timestamp,
        ];
    }

    private function fail(int $status, string $message): array
    {
        return ['valid' => false, 'status' => $status, 'message' => $message, 'timestamp' => null];
    }
}