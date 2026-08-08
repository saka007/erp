<?php

namespace DigitalFuzed\TextileCore\Console\Commands;

use DigitalFuzed\TextileCore\Services\TextilePaymentReminderService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'textile:payment-reminders {--force : Ignore the reminder cooldown period}';

    protected $description = 'Send payment reminders for overdue vendor and buyer invoices';

    public function handle(TextilePaymentReminderService $service): int
    {
        $result = $service->sendDueReminders((bool) $this->option('force'));

        $this->info(sprintf(
            'Payment reminders — sent: %d, skipped: %d, failed: %d',
            $result['sent'],
            $result['skipped'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
