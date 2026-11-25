<?php

namespace App\Console\Commands;

use App\Models\JobOffer;
use App\Services\AuditLogService;
use Illuminate\Console\Command;

class ExpireOverdueJobOffers extends Command
{
    protected $signature = 'offers:expire-overdue';

    protected $description = 'Mark sent job offers whose expiration timestamp has passed as expired.';

    public function handle(AuditLogService $auditLog): int
    {
        $overdueOffers = JobOffer::query()
            ->where('status', 'sent')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdueOffers as $offer) {
            $offer->update(['status' => 'expired']);
            $auditLog->log(
                eventType: 'offer.expired',
                auditable: $offer,
                description: "Job offer {$offer->offer_number} expired automatically"
            );
        }

        $this->info("Expired {$overdueOffers->count()} overdue job offer(s).");

        return self::SUCCESS;
    }
}
