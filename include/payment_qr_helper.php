<?php

function getVietQrPaymentConfig(): ?array
{
    $configPath = __DIR__ . '/../config/payment.local.php';
    if (!is_file($configPath)) {
        return null;
    }

    $config = require $configPath;
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
        $bankName === '' ||
        !preg_match('/^\d{6}$/', $bankBin) ||
        !preg_match('/^\d{6,19}$/', $accountNumber) ||
        $accountHolder === ''
    ) {
        throw new RuntimeException('Vui lòng cấu hình đầy đủ thông tin tài khoản VietQR.');
    }

    return [
        'bank_name' => $bankName,
        'bank_bin' => $bankBin,
        'account_number' => $accountNumber,
        'account_holder' => $accountHolder
    ];
}

function createVietQrPaymentAttempt(int $orderId, float $amount, array $config): array
{
    if ($orderId < 1 || $amount <= 0) {
        throw new InvalidArgumentException('Thông tin đơn hàng thanh toán không hợp lệ.');
    }

    $code = (string) random_int(10000000, 99999999);
    $reference = 'EC' . $orderId . $code;
    $acquirer = vietQrField('00', $config['bank_bin'])
        . vietQrField('01', $config['account_number']);
    $merchantAccount = vietQrField('00', 'A000000727')
        . vietQrField('01', $acquirer)
        . vietQrField('02', 'QRIBFTTA');
    $additionalData = vietQrField('08', $reference);

    $payload = vietQrField('00', '01')
        . vietQrField('01', '12')
        . vietQrField('38', $merchantAccount)
        . vietQrField('53', '704')
        . vietQrField('54', (string) (int) round($amount))
        . vietQrField('58', 'VN')
        . vietQrField('62', $additionalData);
    $payloadWithCrcTag = $payload . '6304';

    return [
        'token' => bin2hex(random_bytes(32)),
        'reference' => $reference,
        'payload' => $payloadWithCrcTag . strtoupper(str_pad(dechex(vietQrCrc16($payloadWithCrcTag)), 4, '0', STR_PAD_LEFT)),
        'expires_at' => time() + 60
    ];
}

function vietQrField(string $tag, string $value): string
{
    $length = strlen($value);
    if ($length > 99) {
        throw new LengthException('Trường dữ liệu VietQR vượt quá độ dài cho phép.');
    }
    return $tag . str_pad((string) $length, 2, '0', STR_PAD_LEFT) . $value;
}

function vietQrCrc16(string $value): int
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
