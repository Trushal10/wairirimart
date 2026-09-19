<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Normalises and holds report filters. Every value is optional; every
 * builder method on ReportService accepts a ReportFilters instance and
 * applies only the filters it understands (e.g. sales ignores courier).
 */
class ReportFilters
{
    public ?Carbon $from = null;
    public ?Carbon $to = null;
    public ?string $status = null;              // orders.status
    public ?string $paymentStatus = null;       // payments.status
    public ?string $paymentType = null;         // payments.type == gateway code
    public ?int $courierId = null;              // delivery_partners.id
    public ?int $categoryId = null;             // categories.id
    public ?string $brand = null;               // products.brand (free-text)
    public ?int $productId = null;              // products.id
    public ?int $customerId = null;             // customers.id
    public ?string $search = null;
    public ?string $group = null;               // day|week|month
    public string $preset = 'last_30';

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'courier_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:128'],
            'product_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'in:day,week,month'],
            'preset' => ['nullable', 'in:today,yesterday,last_7,last_30,mtd,ytd,custom'],
        ]);

        $f = new self();
        $f->preset = $data['preset'] ?? 'last_30';

        [$from, $to] = self::resolvePreset($f->preset, $data['from'] ?? null, $data['to'] ?? null);
        $f->from = $from;
        $f->to = $to;

        $f->status = $data['status'] ?? null;
        $f->paymentStatus = $data['payment_status'] ?? null;
        $f->paymentType = $data['payment_type'] ?? null;
        $f->courierId = isset($data['courier_id']) ? (int) $data['courier_id'] : null;
        $f->categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $f->brand = $data['brand'] ?? null;
        $f->productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $f->customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        $f->search = $data['search'] ?? null;
        $f->group = $data['group'] ?? self::defaultGroup($f->from, $f->to);

        return $f;
    }

    public function toArray(): array
    {
        return [
            'from' => optional($this->from)->toDateString(),
            'to' => optional($this->to)->toDateString(),
            'status' => $this->status,
            'payment_status' => $this->paymentStatus,
            'payment_type' => $this->paymentType,
            'courier_id' => $this->courierId,
            'category_id' => $this->categoryId,
            'brand' => $this->brand,
            'product_id' => $this->productId,
            'customer_id' => $this->customerId,
            'search' => $this->search,
            'group' => $this->group,
            'preset' => $this->preset,
        ];
    }

    /**
     * Immutable clone shifted to the previous period of equal length. Used
     * for the "compare vs previous period" KPI deltas.
     */
    public function previousPeriod(): self
    {
        $clone = clone $this;
        if (! $this->from || ! $this->to) {
            return $clone;
        }
        $days = max(1, $this->from->diffInDays($this->to) + 1);
        $clone->to = $this->from->copy()->subDay()->endOfDay();
        $clone->from = $clone->to->copy()->subDays($days - 1)->startOfDay();
        return $clone;
    }

    private static function resolvePreset(string $preset, ?string $from, ?string $to): array
    {
        $now = Carbon::now();
        switch ($preset) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'yesterday':
                $y = $now->copy()->subDay();
                return [$y->copy()->startOfDay(), $y->copy()->endOfDay()];
            case 'last_7':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()];
            case 'last_30':
                return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()];
            case 'mtd':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];
            case 'ytd':
                return [$now->copy()->startOfYear(), $now->copy()->endOfDay()];
            case 'custom':
            default:
                $f = $from ? Carbon::parse($from)->startOfDay() : $now->copy()->subDays(29)->startOfDay();
                $t = $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay();
                return [$f, $t];
        }
    }

    private static function defaultGroup(?Carbon $from, ?Carbon $to): string
    {
        if (! $from || ! $to) return 'day';
        $days = $from->diffInDays($to) + 1;
        if ($days <= 31) return 'day';
        if ($days <= 180) return 'week';
        return 'month';
    }
}
