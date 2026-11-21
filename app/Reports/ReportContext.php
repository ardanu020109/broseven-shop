<?php

namespace App\Reports;

use App\Models\CompanySetting;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\Storage;

final class ReportContext
{
    public static function for(User $user): array
    {
        $company = CompanySetting::current();

        return [
            'company' => $company,
            'brand' => self::brand(WebsiteSetting::current()),
            'logo' => self::logo($company),
            'generatedBy' => $user->name.' ('.$user->role->label().')',
            'generatedAt' => now()->format('d/m/Y H:i'),
        ];
    }

    private static function brand(WebsiteSetting $settings): array
    {
        $pick = fn (?string $value, string $fallback) => is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)
            ? strtoupper($value)
            : $fallback;

        return [
            'dark' => $pick($settings->dark_color, '#26547C'),
            'primary' => $pick($settings->primary_color, '#06D6A0'),
        ];
    }

    private static function logo(CompanySetting $company): ?string
    {
        if (! $company->logo_path || ! extension_loaded('gd')) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($company->logo_path) || $disk->size($company->logo_path) > 1_500_000) {
            return null;
        }

        $binary = $disk->get($company->logo_path);
        $info = @getimagesizefromstring($binary);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode($binary);
    }
}
