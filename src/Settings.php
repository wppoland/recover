<?php

declare(strict_types=1);

namespace Recover;

defined('ABSPATH') || exit;

/**
 * Typed accessor over the `recover_settings` option, with sane defaults so the
 * plugin works out of the box and never fatals on a missing/garbled option.
 */
final class Settings
{
    public const OPTION = 'recover_settings';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION, []);

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled'             => true,
            'capture_guests'      => true,
            'abandon_after'       => 60,   // Minutes of inactivity before a cart is "abandoned".
            'email_delay'         => 30,   // Minutes after abandonment before the recovery email.
            'require_consent'     => true,
            'consent_label'       => '',
            'email_subject'       => '',
            'email_heading'       => '',
            'email_body'          => '',
            'email_button'        => '',
        ];
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $fallback;
    }

    public function enabled(): bool
    {
        return (bool) $this->get('enabled', true);
    }

    public function captureGuests(): bool
    {
        return (bool) $this->get('capture_guests', true);
    }

    public function requireConsent(): bool
    {
        return (bool) $this->get('require_consent', true);
    }

    public function abandonAfterMinutes(): int
    {
        return max(5, (int) $this->get('abandon_after', 60));
    }

    public function emailDelayMinutes(): int
    {
        return max(0, (int) $this->get('email_delay', 30));
    }

    public function consentLabel(): string
    {
        $value = (string) $this->get('consent_label', '');

        return $value !== ''
            ? $value
            : __('Email me a link to recover my cart if I do not complete my order.', 'recover');
    }

    public function emailSubject(): string
    {
        $value = (string) $this->get('email_subject', '');

        return $value !== '' ? $value : __('You left something in your cart', 'recover');
    }

    public function emailHeading(): string
    {
        $value = (string) $this->get('email_heading', '');

        return $value !== '' ? $value : __('Still thinking it over?', 'recover');
    }

    public function emailBody(): string
    {
        $value = (string) $this->get('email_body', '');

        return $value !== ''
            ? $value
            : __('We saved your cart for you. Click the button below to pick up right where you left off.', 'recover');
    }

    public function emailButton(): string
    {
        $value = (string) $this->get('email_button', '');

        return $value !== '' ? $value : __('Complete my order', 'recover');
    }
}
