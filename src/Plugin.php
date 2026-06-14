<?php

declare(strict_types=1);

namespace Recover;

use Recover\Contract\HasHooks;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    private Container $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();

        // Register service factories up front so the container is usable from the
        // activation hook, which fires before plugins_loaded and boot() run.
        (require PLUGIN_DIR . '/config/services.php')($this->container);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Absolute path to the plugin directory (with optional relative path appended).
     */
    public function path(string $relative = ''): string
    {
        return PLUGIN_DIR . ($relative !== '' ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * URL to the plugin directory (with optional relative path appended).
     */
    public function url(string $relative = ''): string
    {
        return plugins_url($relative, PLUGIN_FILE);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->container->get(Migrator::class)->maybeMigrate();

        /** @var array<class-string<HasHooks>> $hooks */
        $hooks = require PLUGIN_DIR . '/config/hooks.php';
        foreach ($hooks as $id) {
            $service = $this->container->get($id);
            if ($service instanceof HasHooks) {
                $service->registerHooks();
            }
        }
    }
}
