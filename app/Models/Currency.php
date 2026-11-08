<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'rate' => 'decimal:6',
        'buffer_percent' => 'decimal:2',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'rate_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Currency $c) {
            if ($c->isDirty(['rate', 'buffer_percent'])) {
                $c->rate_updated_at = now();
            }
        });
        static::saved(fn () => Cache::forget('currencies:active'));
        static::deleted(fn () => Cache::forget('currencies:active'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    /** Active currencies keyed by code, cached until one is edited. */
    public static function activeMap(): array
    {
        return Cache::rememberForever('currencies:active', fn () => static::active()->get()->keyBy('code')->all());
    }

    public static function base(): string
    {
        return static::query()->where('is_base', true)->value('code') ?? 'MYR';
    }

    public static function forCode(string $code): ?self
    {
        return static::activeMap()[strtoupper($code)] ?? null;
    }

    /** The multiplier applied to a base-currency price: rate x (1 + buffer). */
    public function effectiveRate(): float
    {
        return (float) $this->rate * (1 + (float) $this->buffer_percent / 100);
    }

    /** Converts a base-currency (MYR) amount into this currency, rounded to 2 decimals. */
    public function fromBase(float $amount): float
    {
        return round($amount * $this->effectiveRate(), 2);
    }

    public function format(float $amount): string
    {
        return $this->symbol.($this->symbol === 'RM' ? ' ' : '').number_format($amount, 2);
    }
}
