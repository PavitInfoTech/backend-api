<?php

namespace App\Services;

use Illuminate\Support\Str;

class SandboxPaymentGateway
{
    /**
     * Sandbox gateway - validates card format but always succeeds.
     * Use specific card numbers to trigger failure scenarios for testing.
     */
    public function processPayment(array $data): array
    {
        $cardNumber = preg_replace('/\s+/', '', $data['card_number']);

        // Validate card number format (Luhn algorithm optional for sandbox)
        if (!$this->isValidCardFormat($cardNumber)) {
            return [
                'success' => false,
                'message' => 'Invalid card number format',
                'error_code' => 'INVALID_CARD_FORMAT',
            ];
        }

        // Test card numbers for different scenarios
        if (Str::startsWith($cardNumber, '4000000000000002')) {
            return [
                'success' => false,
                'message' => 'Card declined',
                'error_code' => 'CARD_DECLINED',
            ];
        }

        if (Str::startsWith($cardNumber, '4000000000000069')) {
            return [
                'success' => false,
                'message' => 'Card expired',
                'error_code' => 'CARD_EXPIRED',
            ];
        }

        if (Str::startsWith($cardNumber, '4000000000000127')) {
            return [
                'success' => false,
                'message' => 'Incorrect CVV',
                'error_code' => 'INCORRECT_CVV',
            ];
        }

        if (Str::startsWith($cardNumber, '4000000000000119')) {
            return [
                'success' => false,
                'message' => 'Processing error',
                'error_code' => 'PROCESSING_ERROR',
            ];
        }

        // Validate expiry date
        $expiryMonth = (int) $data['expiry_month'];
        $expiryYear = (int) ('20' . $data['expiry_year']);

        if ($expiryMonth < 1 || $expiryMonth > 12) {
            return [
                'success' => false,
                'message' => 'Invalid expiry month',
                'error_code' => 'INVALID_EXPIRY',
            ];
        }

        $now = now();
        if ($expiryYear < $now->year || ($expiryYear === $now->year && $expiryMonth < $now->month)) {
            return [
                'success' => false,
                'message' => 'Card has expired',
                'error_code' => 'CARD_EXPIRED',
            ];
        }

        // CVV validation
        if (strlen($data['cvv']) < 3 || strlen($data['cvv']) > 4) {
            return [
                'success' => false,
                'message' => 'Invalid CVV',
                'error_code' => 'INVALID_CVV',
            ];
        }

        // All validations passed - simulate successful payment
        $transactionId = 'TXN_' . strtoupper(bin2hex(random_bytes(12)));
        $cardBrand = $this->detectCardBrand($cardNumber);

        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'transaction_id' => $transactionId,
            'card_last_four' => substr($cardNumber, -4),
            'card_brand' => $cardBrand,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'processed_at' => now()->toISOString(),
            'sandbox' => true,
        ];
    }

    /**
     * Verify a transaction exists and is valid.
     */
    public function verifyTransaction(string $transactionId): array
    {
        // In sandbox mode, all transactions that start with TXN_ are valid
        if (Str::startsWith($transactionId, 'TXN_')) {
            return [
                'valid' => true,
                'status' => 'completed',
                'sandbox' => true,
            ];
        }

        return [
            'valid' => false,
            'status' => 'not_found',
            'sandbox' => true,
        ];
    }

    /**
     * Process a refund.
     */
    public function processRefund(string $transactionId, float $amount, ?string $reason = null): array
    {
        // In sandbox, refunds always succeed for valid transaction IDs
        if (!Str::startsWith($transactionId, 'TXN_')) {
            return [
                'success' => false,
                'message' => 'Original transaction not found',
                'error_code' => 'TRANSACTION_NOT_FOUND',
            ];
        }

        return [
            'success' => true,
            'message' => 'Refund processed successfully',
            'refund_transaction_id' => 'REF_' . strtoupper(bin2hex(random_bytes(12))),
            'original_transaction_id' => $transactionId,
            'amount' => $amount,
            'reason' => $reason,
            'processed_at' => now()->toISOString(),
            'sandbox' => true,
        ];
    }

    /**
     * Verify webhook signature.
     * In sandbox mode, all signatures are valid.
     */
    public function verifyWebhookSignature(?string $signature, string $payload): bool
    {
        // In sandbox mode, accept any signature or no signature
        return true;
    }

    /**
     * Validate card number format.
     */
    protected function isValidCardFormat(string $cardNumber): bool
    {
        // Remove any spaces or dashes
        $cardNumber = preg_replace('/[\s\-]/', '', $cardNumber);

        // Check length (13-19 digits is standard)
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }

        // Check if all characters are digits
        if (!ctype_digit($cardNumber)) {
            return false;
        }

        return true;
    }

    /**
     * Detect card brand from number.
     */
    protected function detectCardBrand(string $cardNumber): string
    {
        $patterns = [
            'visa' => '/^4/',
            'mastercard' => '/^(5[1-5]|2[2-7])/',
            'amex' => '/^3[47]/',
            'discover' => '/^(6011|65|64[4-9])/',
            'diners' => '/^(36|38|30[0-5])/',
            'jcb' => '/^35/',
        ];

        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return $brand;
            }
        }

        return 'unknown';
    }

    /**
     * Get test card numbers for documentation.
     */
    public static function getTestCards(): array
    {
        return [
            'success' => [
                'visa' => '4242424242424242',
                'mastercard' => '5555555555554444',
                'amex' => '378282246310005',
            ],
            'decline' => [
                'generic_decline' => '4000000000000002',
                'expired_card' => '4000000000000069',
                'incorrect_cvv' => '4000000000000127',
                'processing_error' => '4000000000000119',
            ],
        ];
    }
}
