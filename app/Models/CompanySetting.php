<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    protected $fillable = [
        'store_name',
        'logo_path',
        'favicon_path',
        'address',
        'phone',
        'email',
        'description',
        'footer_text',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null
        );
    }

    protected function faviconUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null
        );
    }
}
