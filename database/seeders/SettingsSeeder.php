<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanySetting::current();

        if (blank($company->description)) {
            $company->update([
                'description' => 'Toko modern untuk kebutuhan Anda. Pesan dari rumah, ambil dan bayar langsung di kasir.',
                'footer_text' => '© BroSeven! Shop. Seluruh hak cipta dilindungi.',
            ]);
        }

        WebsiteSetting::current();
    }
}
