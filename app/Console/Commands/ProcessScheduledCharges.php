<?php

namespace App\Console\Commands;

use App\Actions\Charges\GenerateScheduledCharges;
use App\Actions\Charges\MarkOverdueCharges;
use App\Actions\Charges\SendChargeReminders;
use App\Actions\Domains\SendDomainExpiryReminders;
use App\Actions\Quotes\ExpireStaleQuotes;
use App\Actions\Renewals\OpenRenewalCycles;
use App\Actions\Renewals\SendRenewalNotices;
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
    protected $description = 'Genera los cobros programados, marca los vencidos, abre los ciclos de renovación, expira las cotizaciones vencidas y envía los recordatorios internos y los avisos de renovación al cliente.';

    public function handle(
        GenerateScheduledCharges $generateScheduledCharges,
        MarkOverdueCharges $markOverdueCharges,
        SendChargeReminders $sendChargeReminders,
        SendDomainExpiryReminders $sendDomainExpiryReminders,
        OpenRenewalCycles $openRenewalCycles,
        SendRenewalNotices $sendRenewalNotices,
        ExpireStaleQuotes $expireStaleQuotes,
    ): void {
        $generateScheduledCharges->handle();

        $overdueCount = $markOverdueCharges->handle();

        $sendChargeReminders->handle();

        $sendDomainExpiryReminders->handle();

        $openedCycles = $openRenewalCycles->handle();

        $noticesSent = $sendRenewalNotices->handle();

        $expiredQuotes = $expireStaleQuotes->handle();

        $this->info("Cobros vencidos marcados: {$overdueCount}.");
        $this->info("Ciclos de renovación abiertos: {$openedCycles}.");
        $this->info("Avisos de renovación enviados al cliente: {$noticesSent}.");
        $this->info("Cotizaciones expiradas: {$expiredQuotes}.");
    }
}
