<?php

namespace App\Console\Commands;

use App\Actions\Charges\GenerateScheduledCharges;
use App\Actions\Charges\MarkOverdueCharges;
use App\Actions\Charges\SendChargeReminders;
use App\Actions\Domains\SendDomainExpiryReminders;
use Illuminate\Console\Command;

class ProcessScheduledCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'charges:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera los cobros programados, marca los vencidos, y envía los recordatorios de cobro y de expiración de dominios.';

    public function handle(
        GenerateScheduledCharges $generateScheduledCharges,
        MarkOverdueCharges $markOverdueCharges,
        SendChargeReminders $sendChargeReminders,
        SendDomainExpiryReminders $sendDomainExpiryReminders,
    ): void {
        $generateScheduledCharges->handle();

        $overdueCount = $markOverdueCharges->handle();

        $sendChargeReminders->handle();

        $sendDomainExpiryReminders->handle();

        $this->info("Cobros vencidos marcados: {$overdueCount}.");
    }
}
