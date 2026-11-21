<?php

namespace App\Console\Commands;

use App\Exceptions\OrderWorkflowException;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Console\Command;
use Throwable;

class StressWorkerCommand extends Command
{
    protected $signature = 'inventory:stress-worker {cashier} {orders} {startAt} {seed}';

    protected $description = 'Worker uji konkurensi (dipanggil oleh inventory:stress-approve).';

    protected $hidden = true;

    public function handle(OrderWorkflowService $workflow): int
    {
        $cashier = User::query()->findOrFail((int) $this->argument('cashier'));
        $ids = array_map('intval', explode(',', (string) $this->argument('orders')));
        $startAt = (float) $this->argument('startAt');

        mt_srand((int) $this->argument('seed'));
        shuffle($ids);

        $orders = Order::query()->whereIn('id', $ids)->get()->keyBy('id');

        while (microtime(true) < $startAt) {
            usleep(500);
        }

        $approved = [];
        $rejected = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $workflow->approve($orders[$id], $cashier, 'manual');
                $approved[] = $id;
            } catch (OrderWorkflowException) {
                $rejected++;
            } catch (Throwable $e) {
                $errors[] = class_basename($e).': '.$e->getMessage();
            }
        }

        $this->line(json_encode(['approved' => $approved, 'rejected' => $rejected, 'errors' => $errors]));

        return self::SUCCESS;
    }
}
