<?php

namespace App\Support;

final class Color
{
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (string $part) => hexdec($part) / 255,
            str_split(ltrim($hex, '#'), 2),
        );

        $channel = fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }
}
