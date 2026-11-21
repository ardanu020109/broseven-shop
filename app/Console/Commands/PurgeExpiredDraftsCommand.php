<?php

namespace App\Console\Commands;

use App\Services\DraftCleanupService;
use Illuminate\Console\Command;

class PurgeExpiredDraftsCommand extends Command
{
    protected $signature = 'orders:purge-drafts
        {--hours= : Timpa batas jam dari pengaturan perusahaan}
        {--dry-run : Hanya tampilkan pesanan yang akan dihapus}';

    protected $description = 'Menghapus pesanan berstatus DRAFT yang melewati batas waktu pada pengaturan perusahaan.';

    public function handle(DraftCleanupService $service): int
    {
        $option = $this->option('hours');

        if ($option !== null && ! ctype_digit((string) $option)) {
            $this->error('Opsi --hours harus berupa bilangan bulat (jam).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $service->purge($option !== null ? (int) $option : null, $dryRun);

        if ($result['hours'] === 0) {
            $this->info('Penghapusan otomatis nonaktif (batas 0 jam).');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'akan dihapus' : 'dihapus';
        $count = $dryRun ? $result['found'] - $result['skipped'] : $result['deleted'];

        $this->info("Batas {$result['hours']} jam: {$count} pesanan draft {$verb}, {$result['skipped']} dilewati.");

        foreach ($result['orders'] as $number) {
            $this->line("  - {$number}");
        }

        return self::SUCCESS;
    }
}