<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Bindings are lazy — nothing is instantiated until first resolved.
 *
 * @package Recover
 */

declare(strict_types=1);

use Recover\Admin\CartsPage;
use Recover\Admin\SettingsPage;
use Recover\Container;
use Recover\Migrator;
use Recover\Repository\CartRepository;
use Recover\Service\CartTracker;
use Recover\Service\CronWorker;
use Recover\Service\RecoveryMailer;
use Recover\Service\RestoreHandler;
use Recover\Settings;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    // Infrastructure.
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());
    $c->singleton(Settings::class, static fn (): Settings => new Settings());
    $c->singleton(CartRepository::class, static function (): CartRepository {
        global $wpdb;
        return new CartRepository($wpdb);
    });

    // Services.
    $c->singleton(RecoveryMailer::class, static fn (Container $c): RecoveryMailer => new RecoveryMailer(
        $c->get(Settings::class),
    ));
    $c->singleton(CartTracker::class, static fn (Container $c): CartTracker => new CartTracker(
        $c->get(CartRepository::class),
        $c->get(Settings::class),
    ));
    $c->singleton(RestoreHandler::class, static fn (Container $c): RestoreHandler => new RestoreHandler(
        $c->get(CartRepository::class),
    ));
    $c->singleton(CronWorker::class, static fn (Container $c): CronWorker => new CronWorker(
        $c->get(CartRepository::class),
        $c->get(Settings::class),
        $c->get(RecoveryMailer::class),
    ));

    // Admin (only in wp-admin context).
    if (is_admin()) {
        $c->singleton(SettingsPage::class, static fn (Container $c): SettingsPage => new SettingsPage(
            $c->get(Settings::class),
        ));
        $c->singleton(CartsPage::class, static fn (Container $c): CartsPage => new CartsPage(
            $c->get(CartRepository::class),
        ));
    }
};
