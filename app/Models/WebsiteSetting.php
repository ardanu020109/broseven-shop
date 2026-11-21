<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    public const DEFAULTS = [
        'primary_color' => '#06D6A0',
        'secondary_color' => '#FFD166',
        'danger_color' => '#EF233C',
        'background_color' => '#F7FFF7',
        'dark_color' => '#26547C',
    ];

    protected $fillable = [
        'primary_color',
        'secondary_color',
        'danger_color',
        'background_color',
        'dark_color',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create(self::DEFAULTS);
    }

    public function resetToDefaults(): self
    {
        $this->update(self::DEFAULTS);

        return $this;
    }
}
