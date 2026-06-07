<?php

namespace Arnavision\PaymentGateway\Drivers\Keepa;

class KeepaStatus
{
    public const VERIFIED = 1;
    public const PENDING = 2;
    public const FAILED = 3;
    public const UNKNOWN = 4;
    public const CONTRADICTION = 5;
    public const PREPARE_TO_REVERSE = 6;
    public const REVERSED = 7;
    public const WAITING_TO_VERIFY = 8;

    public static function isVerified(null|int|string $status): bool
    {
        return (int) $status === self::VERIFIED;
    }

    public static function isWaitingToVerify(null|int|string $status): bool
    {
        return (int) $status === self::WAITING_TO_VERIFY;
    }

    public static function title(null|int|string $status): string
    {
        return match ((int) $status) {
            self::VERIFIED => 'پرداخت موفق',
            self::PENDING => 'در انتظار پرداخت',
            self::FAILED => 'پرداخت ناموفق',
            self::UNKNOWN => 'وضعیت پرداخت نامشخص است',
            self::CONTRADICTION => 'مغایرت پرداخت نقدی',
            self::PREPARE_TO_REVERSE => 'آماده‌سازی جهت لغو',
            self::REVERSED => 'لغو شده',
            self::WAITING_TO_VERIFY => 'در انتظار تایید پذیرنده',
            default => 'وضعیت نامشخص',
        };
    }

    public static function errorMessage(null|int|string $code, ?string $fallback = null): string
    {
        $messages = [
            101 => 'شماره ترمینال پذیرنده نامعتبر است.',
            102 => 'شماره فاکتور نامعتبر است.',
            103 => 'مبلغ تراکنش نامعتبر است.',
            104 => 'آدرس بازگشت نامعتبر است.',
            105 => 'طول پارامتر اختیاری پذیرنده نامعتبر است.',
            106 => 'پذیرنده یافت نشد.',
            107 => 'پذیرنده فعال نیست.',
            108 => 'تراکنش قبلاً پرداخت شده است.',
            109 => 'تراکنش تایید نشده با این شماره فاکتور وجود دارد.',
            110 => 'وضعیت تراکنش نامعتبر می‌باشد.',
            111 => 'تراکنش یافت نشد.',
            114 => 'توکن پرداخت اجباریست.',
            115 => 'پرداختی توسط کاربر انجام نشده است.',
            116 => 'پرداختی مشتری مغایرت دارد.',
            117 => 'تراکنش آماده لغو شدن است.',
            118 => 'تراکنش لغو شده است.',
            119 => 'پرداخت انجام نشد.',
            120 => 'تراکنش اعتباری تایید نشده است.',
            123 => 'توکن پرداخت منقضی شده است.',
            127 => 'آدرس سرور پذیرنده نامعتبر است.',
            128 => 'مشکلی رخ داده است. لطفاً در زمانی دیگر تلاش کنید.',
            130 => 'شعبه پذیرنده یافت نشد.',
            177 => 'قرارداد بهره‌بردار یا پذیرنده فعال نیست.',
            190 => 'آی‌دی کلاینت یا کلید امنیتی کلاینت نامعتبر است.',
            191 => 'گرنت نامعتبر است.',
            192 => 'توکن احراز هویت نامعتبر است.',
        ];

        return $messages[(int) $code] ?? $fallback ?? 'خطای نامشخص کیپا.';
    }
}
