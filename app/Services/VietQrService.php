<?php

namespace App\Services;

use InvalidArgumentException;
use LengthException;
use RuntimeException;

/**
 * Lớp VietQrService - đọc cấu hình tài khoản nhận tiền và sinh payload VietQR (chuẩn EMVCo).
 */
class VietQrService
{
    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? APP_ROOT . '/config/payment.local.php';
    }

    /**
     * Cấu hình tài khoản VietQR, hoặc null nếu chưa cấu hình.
     *
     * @return array{bank_name:string, bank_bin:string, account_number:string, account_holder:string}|null
     */
    public function getConfig(): ?array
    {
        if (!is_file($this->configPath)) {
            return null;
        }

        $config = require $this->configPath;
        if (!is_array($config)) {
            throw new RuntimeException('Cấu hình VietQR không hợp lệ.');
        }

        $bankName = trim((string) ($config['bank_name'] ?? ''));
        $bankBin = trim((string) ($config['bank_bin'] ?? ''));
        $accountNumber = trim((string) ($config['account_number'] ?? ''));
        $accountHolder = trim((string) ($config['account_holder'] ?? ''));

        if ($bankName === '' && $bankBin === '' && $accountNumber === '' && $accountHolder === '') {
            return null;
        }

        if (
            $bankName === ''
            || !preg_match('/^\d{6}$/', $bankBin)
            || !preg_match('/^\d{6,19}$/', $accountNumber)
            || $accountHolder === ''
        ) {
            throw new RuntimeException('Vui lòng cấu hình đầy đủ thông tin tài khoản VietQR.');
        }

        return [
            'bank_name' => $bankName,
            'bank_bin' => $bankBin,
            'account_number' => $accountNumber,
            'account_holder' => $accountHolder,
        ];
    }

    /**
     * Tạo một lượt thanh toán VietQR mới (mã tham chiếu, payload QR, token, hạn 60 giây).
     */
    public function createAttempt(int $orderId, float $amount, array $config): array
    {
        if ($orderId < 1 || $amount <= 0) {
            throw new InvalidArgumentException('Thông tin đơn hàng thanh toán không hợp lệ.');
        }

        $code = (string) random_int(10000000, 99999999);
        $reference = 'EC' . $orderId . $code;
        $acquirer = $this->field('00', $config['bank_bin'])
            . $this->field('01', $config['account_number']);
        $merchantAccount = $this->field('00', 'A000000727')
            . $this->field('01', $acquirer)
            . $this->field('02', 'QRIBFTTA');
        $additionalData = $this->field('08', $reference);

        $payload = $this->field('00', '01')
            . $this->field('01', '12')
            . $this->field('38', $merchantAccount)
            . $this->field('53', '704')
            . $this->field('54', (string) (int) round($amount))
            . $this->field('58', 'VN')
            . $this->field('62', $additionalData);
        $payloadWithCrcTag = $payload . '6304';

        return [
            'token' => bin2hex(random_bytes(32)),
            'reference' => $reference,
            'payload' => $payloadWithCrcTag . strtoupper(str_pad(dechex($this->crc16($payloadWithCrcTag)), 4, '0', STR_PAD_LEFT)),
            'expires_at' => time() + 60,
        ];
    }

    /** Mã hóa một trường TLV (tag + độ dài 2 chữ số + giá trị). */
    public function field(string $tag, string $value): string
    {
        $length = strlen($value);
        if ($length > 99) {
            throw new LengthException('Trường dữ liệu VietQR vượt quá độ dài cho phép.');
        }

        return $tag . str_pad((string) $length, 2, '0', STR_PAD_LEFT) . $value;
    }

    /** CRC16-CCITT (0xFFFF) theo chuẩn EMVCo. */
    public function crc16(string $value): int
    {
        $crc = 0xFFFF;
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $crc ^= ord($value[$index]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return $crc;
    }
}
