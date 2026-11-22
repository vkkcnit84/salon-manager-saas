<?php

namespace App\Shops\Actions;

class ShopSettings
{
    public static function defaults(): array
    {
        return [
            'opening_hours' => ['open' => '10:00', 'close' => '21:00'],
            'weekly_off' => [],
            'rounding' => 'nearest_rupee', // none | nearest_rupee
            'bill_footer' => 'Thank you, visit again!',
            'commission_basis' => ['after_discount' => true, 'before_gst' => true],
            'low_credit_alert' => 100,
        ];
    }

    public static function rules(): array
    {
        return [
            'settings.opening_hours.open' => ['sometimes', 'date_format:H:i'],
            'settings.opening_hours.close' => ['sometimes', 'date_format:H:i'],
            'settings.weekly_off' => ['sometimes', 'array'],
            'settings.weekly_off.*' => ['integer', 'between:0,6'],
            'settings.rounding' => ['sometimes', 'in:none,nearest_rupee'],
            'settings.bill_footer' => ['sometimes', 'nullable', 'string', 'max:300'],
            'settings.commission_basis.after_discount' => ['sometimes', 'boolean'],
            'settings.commission_basis.before_gst' => ['sometimes', 'boolean'],
            'settings.low_credit_alert' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
