<?php

namespace App\Messaging\Services;

use App\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audience filters shared by segments, campaigns and the bot's win-back draft.
 * Supported keys: inactive_days, min_spend_paise, max_spend_paise, min_visits, tag, birthday_month, has_due.
 */
class CustomerFilter
{
    private const LAST = '(select max(created_at) from invoices where invoices.customer_id = customers.id and invoices.status != \'void\')';

    private const SPEND = '(select coalesce(sum(total_paise),0) from invoices where invoices.customer_id = customers.id and invoices.status != \'void\')';

    public static function apply(Builder $q, array $f): Builder
    {
        if (! empty($f['inactive_days'])) {
            $cut = now()->subDays((int) $f['inactive_days']);
            $q->where(fn ($w) => $w->whereRaw(self::LAST.' < ?', [$cut])->orWhereRaw(self::LAST.' is null'));
        }
        if (isset($f['min_spend_paise'])) {
            $q->whereRaw(self::SPEND.' >= ?', [(int) $f['min_spend_paise']]);
        }
        if (isset($f['max_spend_paise'])) {
            $q->whereRaw(self::SPEND.' <= ?', [(int) $f['max_spend_paise']]);
        }
        if (! empty($f['min_visits'])) {
            $q->whereRaw('(select count(*) from invoices where invoices.customer_id = customers.id and invoices.status != \'void\') >= ?', [(int) $f['min_visits']]);
        }
        if (! empty($f['tag'])) {
            $q->where('tags', 'like', '%"'.str_replace(['%', '"'], '', $f['tag']).'"%');
        }
        if (! empty($f['birthday_month'])) {
            $q->whereMonth('birthday', (int) $f['birthday_month']);
        }
        if (! empty($f['has_due'])) {
            $q->whereRaw('(select coalesce(sum(due_paise),0) from invoices where invoices.customer_id = customers.id and invoices.status = \'due\') > 0');
        }

        return $q;
    }

    public static function audience(array $filters, string $channel): Builder
    {
        return self::apply(Customer::query()->contactable($channel), $filters);
    }
}
