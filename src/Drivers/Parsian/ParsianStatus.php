<?php

namespace Arnavision\PaymentGateway\Drivers\Parsian;

class ParsianStatus
{
    public const SUCCESS = '0';

    private const MESSAGES = [
        '-138' => 'عملیات پرداخت توسط کاربر لغو شد یا ارتباط شبکه برقرار نمی‌باشد.',
        '-131' => 'Token نامعتبر می‌باشد.',
        '-130' => 'Token زمان منقضی شده است.',
        '-126' => 'کد شناسایی پذیرنده معتبر نمی‌باشد.',
        '-113' => 'پارامتر ورودی خالی می‌باشد.',
        '-112' => 'شماره سفارش تکراری است.',
        '-101' => 'پذیرنده احراز هویت نشد.',
        '-100' => 'پذیرنده غیرفعال می‌باشد.',
        '-1' => 'خطای سرور.',
        '0' => 'عملیات موفق می‌باشد.',
        '1' => 'صادرکننده کارت از انجام تراکنش صرف‌نظر کرد.',
        '5' => 'از انجام تراکنش صرف‌نظر شد.',
        '12' => 'تراکنش نامعتبر است.',
        '13' => 'مبلغ تراکنش نادرست است.',
        '14' => 'شماره کارت ارسالی نامعتبر است.',
        '33' => 'تاریخ انقضای کارت سپری شده است.',
        '51' => 'موجودی کافی نمی‌باشد.',
        '54' => 'تاریخ انقضای کارت سپری شده است.',
        '55' => 'رمز کارت نامعتبر است.',
        '61' => 'مبلغ تراکنش بیش از حد مجاز می‌باشد.',
        '96' => 'اشکال در عملکرد سیستم.',
    ];

    public static function isSuccessful(null|int|string $status): bool
    {
        return (string) $status === self::SUCCESS;
    }

    public static function message(null|int|string $status, ?string $fallbackMessage = null): string
    {
        $key = (string) $status;

        if ($status !== null && array_key_exists($key, self::MESSAGES)) {
            return self::MESSAGES[$key];
        }

        return $fallbackMessage ?: 'خطای ناشناخته پارسیان.';
    }
}
