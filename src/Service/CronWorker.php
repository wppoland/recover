<?php

declare(strict_types=1);

namespace Recover\Service;

defined('ABSPATH') || exit;

use Recover\Contract\HasHooks;
use Recover\Model\AbandonedCart;
use Recover\Repository\CartRepository;
use Recover\Settings;

use const Recover\CRON_HOOK;

/**
 * wp-cron worker. Two idempotent passes per run:
 *   1. Sweep: mark pending carts inactive past the configured window as abandoned.
 *   2. Send: email a recovery link for each abandoned cart that is due and has
 *      not been emailed yet.
 *
 * Idempotency: a cart is only marked abandoned once (status transition), and
 * emails_sent gates the send so a re-run never double-sends.
 */
final class CronWorker implements HasHooks
{
    public function __construct(
        private readonly CartRepository $repository,
        private readonly Settings $settings,
        private readonly RecoveryMailer $mailer,
    ) {
    }

    public function registerHooks(): void
    {
        add_action(CRON_HOOK, [$this, 'run']);

        // Self-heal: ensure the event exists even if activation was missed
        // (e.g. plugin updated via file copy without re-activation).
        if (! wp_next_scheduled(CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', CRON_HOOK);
        }
    }

    public function run(): void
    {
        if (! $this->settings->enabled()) {
            return;
        }

        $this->sweepAbandoned();
        $this->sendDue();
    }

    private function sweepAbandoned(): void
    {
        $cutoff = $this->utcMinutesAgo($this->settings->abandonAfterMinutes());

        foreach ($this->repository->findPendingOlderThan($cutoff, 200) as $cart) {
            $this->repository->markAbandoned($cart->id);
        }
    }

    private function sendDue(): void
    {
        $maxEmails = max(1, (int) apply_filters('recover/max_emails', 1));
        $due       = $this->repository->findDueForEmail(50, $maxEmails);

        foreach ($due as $cart) {
            $step = $cart->emailsSent;

            if (! $this->isStepDue($cart, $step)) {
                continue;
            }

            if ($this->mailer->send($cart, $step)) {
                $this->repository->recordEmailSent($cart->id);
            }
        }
    }

    private function isStepDue(AbandonedCart $cart, int $step): bool
    {
        $delay = (int) apply_filters(
            'recover/email_step_delay',
            $this->settings->emailDelayMinutes(),
            $cart,
            $step,
        );
        $delay = max(0, $delay);

        $anchor = ($step === 0 || $cart->lastEmailAt === null)
            ? $cart->abandonedAt
            : $cart->lastEmailAt;

        if ($anchor === null) {
            return false;
        }

        $dueAt = $anchor->modify('+' . $delay . ' minutes');

        return $dueAt <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function utcMinutesAgo(int $minutes): string
    {
        return gmdate('Y-m-d H:i:s', time() - ($minutes * MINUTE_IN_SECONDS));
    }
}
