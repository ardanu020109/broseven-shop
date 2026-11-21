<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanySettingRequest;
use App\Http\Requests\Admin\WebsiteSettingsRequest;
use App\Models\CompanySetting;
use App\Models\WebsiteSetting;
use App\Services\AuditLogger;
use App\Support\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettingsController extends Controller
{
    private const COLOR_MAP = [
        'primary' => 'primary_color',
        'secondary' => 'secondary_color',
        'danger' => 'danger_color',
        'background' => 'background_color',
        'dark' => 'dark_color',
    ];

    public function __construct(private AuditLogger $audit)
    {
    }

    public function company(): JsonResponse
    {
        return response()->json(['data' => $this->companyData(CompanySetting::current())]);
    }

    public function updateCompany(CompanySettingRequest $request): JsonResponse
    {
        $company = CompanySetting::current();
        $data = $request->safe()->only(['store_name', 'address', 'phone', 'email', 'description', 'footer_text']);
        $old = $company->only(array_keys($data));
        $disk = Storage::disk('public');
        $newFiles = [];
        $obsolete = [];

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('company', 'public');
                $newFiles[] = $path;
                $data[$column] = $path;

                if ($company->{$column}) {
                    $obsolete[] = $company->{$column};
                }
            } elseif ($request->boolean("remove_{$field}") && $company->{$column}) {
                $data[$column] = null;
                $obsolete[] = $company->{$column};
            }
        }

        try {
            $company->update($data);
        } catch (Throwable $e) {
            foreach ($newFiles as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        foreach ($obsolete as $path) {
            $disk->delete($path);
        }

        $this->audit->log(
            'settings.company_updated',
            $company,
            'Profil perusahaan diperbarui.',
            $old,
            collect($data)->except(['logo_path', 'favicon_path'])->all(),
        );

        return response()->json([
            'message' => 'Profil perusahaan berhasil disimpan.',
            'data' => $this->companyData($company->refresh()),
        ]);
    }

    public function colors(): JsonResponse
    {
        return response()->json($this->colorPayload(WebsiteSetting::current()));
    }

    public function updateColors(WebsiteSettingsRequest $request): JsonResponse
    {
        $input = $request->validated();
        $payload = [];

        foreach (self::COLOR_MAP as $key => $column) {
            $payload[$column] = strtoupper($input[$key]);
        }

        if (Color::contrast($payload['dark_color'], '#FFFFFF') < 3) {
            throw ValidationException::withMessages([
                'dark' => ['Warna Dark terlalu terang sehingga teks tidak terbaca di atas kartu putih.'],
            ]);
        }

        if (Color::contrast($payload['dark_color'], $payload['background_color']) < 3) {
            throw ValidationException::withMessages([
                'dark' => ['Kontras warna Dark terhadap Background terlalu rendah, teks tidak terbaca.'],
            ]);
        }

        $settings = WebsiteSetting::current();
        $old = $settings->only(array_values(self::COLOR_MAP));
        $settings->update($payload);

        $this->audit->log('settings.colors_updated', $settings, 'Warna website diperbarui.', $old, $payload);

        return response()->json(array_merge(
            ['message' => 'Warna website berhasil disimpan.'],
            $this->colorPayload($settings->refresh()),
        ));
    }

    public function resetColors(): JsonResponse
    {
        $settings = WebsiteSetting::current();
        $old = $settings->only(array_values(self::COLOR_MAP));
        $settings->resetToDefaults();

        $this->audit->log('settings.colors_reset', $settings, 'Warna website dikembalikan ke default.', $old, WebsiteSetting::DEFAULTS);

        return response()->json(array_merge(
            ['message' => 'Warna website dikembalikan ke default.'],
            $this->colorPayload($settings->refresh()),
        ));
    }

    private function companyData(CompanySetting $company): array
    {
        return [
            'store_name' => $company->store_name,
            'logo_url' => $company->logo_path ? '/storage/'.$company->logo_path : null,
            'favicon_url' => $company->favicon_path ? '/storage/'.$company->favicon_path : null,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'description' => $company->description,
            'footer_text' => $company->footer_text,
        ];
    }

    private function colorPayload(WebsiteSetting $settings): array
    {
        $data = [];
        $defaults = [];

        foreach (self::COLOR_MAP as $key => $column) {
            $data[$key] = $settings->{$column};
            $defaults[$key] = WebsiteSetting::DEFAULTS[$column];
        }

        return ['data' => $data, 'defaults' => $defaults];
    }
}
