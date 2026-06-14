<?php

declare(strict_types=1);

namespace Recover\Admin;

defined('ABSPATH') || exit;

use Recover\Contract\HasHooks;
use Recover\Settings;

/**
 * Admin settings page under WooCommerce → Recover.
 *
 * Stores settings in the `recover_settings` option (array).
 */
final class SettingsPage implements HasHooks
{
    private const PAGE = 'recover';

    private const SECTION_GENERAL = 'recover_general';
    private const SECTION_TIMING  = 'recover_timing';
    private const SECTION_EMAIL   = 'recover_email';

    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Recover - Abandoned Carts', 'recover'),
            __('Recover', 'recover'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function enqueueStyles(string $hook): void
    {
        if (! str_contains($hook, self::PAGE)) {
            return;
        }

        wp_enqueue_style(
            'recover-admin',
            \Recover\Plugin::instance()->url('assets/css/admin.css'),
            [],
            \Recover\VERSION,
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            Settings::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        // ── General ──────────────────────────────────────────────────────────
        add_settings_section(self::SECTION_GENERAL, __('General', 'recover'), '__return_false', self::PAGE);

        $this->checkbox('enabled', __('Enable cart recovery', 'recover'), __('Track abandoned carts and send recovery emails.', 'recover'), self::SECTION_GENERAL);
        $this->checkbox('capture_guests', __('Capture guest carts', 'recover'), __('Record carts and emails from visitors who are not logged in.', 'recover'), self::SECTION_GENERAL);
        $this->checkbox('require_consent', __('Require consent', 'recover'), __('Only store a guest email after they tick a consent checkbox at checkout (recommended for GDPR).', 'recover'), self::SECTION_GENERAL);
        $this->text('consent_label', __('Consent checkbox label', 'recover'), $this->settings->consentLabel(), self::SECTION_GENERAL);

        // ── Timing ──────────────────────────────────────────────────────────
        add_settings_section(
            self::SECTION_TIMING,
            __('Timing', 'recover'),
            static function (): void {
                echo '<p>' . esc_html__('Control how long to wait before a cart is considered abandoned and emails go out. The recovery worker runs hourly via wp-cron.', 'recover') . '</p>';
            },
            self::PAGE,
        );

        $this->number('abandon_after', __('Mark abandoned after (minutes)', 'recover'), (string) $this->settings->abandonAfterMinutes(), __('Minutes of inactivity before a pending cart is flagged as abandoned.', 'recover'), self::SECTION_TIMING, 5);
        $this->number('email_delay', __('Email delay (minutes)', 'recover'), (string) $this->settings->emailDelayMinutes(), __('Minutes to wait after abandonment before sending the recovery email.', 'recover'), self::SECTION_TIMING, 0);

        // ── Email ────────────────────────────────────────────────────────────
        add_settings_section(
            self::SECTION_EMAIL,
            __('Recovery email', 'recover'),
            static function (): void {
                echo '<p>' . esc_html__('Customise the recovery email. Leave any field blank to use the built-in default. Emails are sent through your own site mailer; no data leaves your store.', 'recover') . '</p>';
            },
            self::PAGE,
        );

        $this->text('email_subject', __('Subject', 'recover'), $this->settings->emailSubject(), self::SECTION_EMAIL);
        $this->text('email_heading', __('Heading', 'recover'), $this->settings->emailHeading(), self::SECTION_EMAIL);
        $this->textarea('email_body', __('Body text', 'recover'), $this->settings->emailBody(), self::SECTION_EMAIL);
        $this->text('email_button', __('Button label', 'recover'), $this->settings->emailButton(), self::SECTION_EMAIL);
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $cronNext = wp_next_scheduled(\Recover\CRON_HOOK);
        ?>
        <div class="wrap recover-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (! $this->settings->enabled()) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Cart recovery is currently disabled. Enable it below to start tracking abandoned carts.', 'recover'); ?></p>
                </div>
            <?php endif; ?>

            <p class="recover-cron-status">
                <?php
                if ($cronNext !== false) {
                    printf(
                        /* translators: %s: human-readable time difference */
                        esc_html__('Next recovery run: in %s.', 'recover'),
                        esc_html(human_time_diff(time(), $cronNext)),
                    );
                } else {
                    esc_html_e('The recovery worker is not scheduled. Re-activate the plugin to restore it.', 'recover');
                }
                ?>
                &nbsp;<a href="<?php echo esc_url(admin_url('admin.php?page=recover-carts')); ?>"><?php esc_html_e('View abandoned carts →', 'recover'); ?></a>
            </p>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::PAGE);
                do_settings_sections(self::PAGE);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param array{id:string, label:string} $args
     */
    public function renderCheckbox(array $args): void
    {
        $id      = $args['id'];
        $checked = (bool) $this->settings->get($id, false);

        printf(
            '<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
            esc_attr($id),
            esc_attr(Settings::OPTION),
            checked($checked, true, false),
            esc_html($args['label']),
        );
    }

    /**
     * @param array{id:string, value:string, description?:string} $args
     */
    public function renderText(array $args): void
    {
        $id = $args['id'];
        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
            esc_attr($id),
            esc_attr(Settings::OPTION),
            esc_attr($args['value']),
        );
        $this->description($args['description'] ?? '');
    }

    /**
     * @param array{id:string, value:string, description?:string} $args
     */
    public function renderTextarea(array $args): void
    {
        $id = $args['id'];
        printf(
            '<textarea id="%1$s" name="%2$s[%1$s]" class="large-text" rows="3">%3$s</textarea>',
            esc_attr($id),
            esc_attr(Settings::OPTION),
            esc_textarea($args['value']),
        );
        $this->description($args['description'] ?? '');
    }

    /**
     * @param array{id:string, value:string, min:int, description?:string} $args
     */
    public function renderNumber(array $args): void
    {
        $id = $args['id'];
        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$d" step="1" class="small-text" />',
            esc_attr($id),
            esc_attr(Settings::OPTION),
            esc_attr($args['value']),
            (int) $args['min'],
        );
        $this->description($args['description'] ?? '');
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return [
            'enabled'         => ! empty($raw['enabled']),
            'capture_guests'  => ! empty($raw['capture_guests']),
            'require_consent' => ! empty($raw['require_consent']),
            'consent_label'   => sanitize_text_field((string) ($raw['consent_label'] ?? '')),
            'abandon_after'   => max(5, absint($raw['abandon_after'] ?? 60)),
            'email_delay'     => absint($raw['email_delay'] ?? 30),
            'email_subject'   => sanitize_text_field((string) ($raw['email_subject'] ?? '')),
            'email_heading'   => sanitize_text_field((string) ($raw['email_heading'] ?? '')),
            'email_body'      => sanitize_textarea_field((string) ($raw['email_body'] ?? '')),
            'email_button'    => sanitize_text_field((string) ($raw['email_button'] ?? '')),
        ];
    }

    private function description(string $text): void
    {
        if ($text !== '') {
            printf('<p class="description">%s</p>', esc_html($text));
        }
    }

    private function checkbox(string $id, string $title, string $label, string $section): void
    {
        add_settings_field($id, $title, [$this, 'renderCheckbox'], self::PAGE, $section, ['id' => $id, 'label' => $label]);
    }

    private function text(string $id, string $title, string $value, string $section): void
    {
        $options = (array) get_option(Settings::OPTION, []);
        $current = isset($options[$id]) ? (string) $options[$id] : '';
        add_settings_field($id, $title, [$this, 'renderText'], self::PAGE, $section, ['id' => $id, 'value' => $current]);
        unset($value);
    }

    private function textarea(string $id, string $title, string $value, string $section): void
    {
        $options = (array) get_option(Settings::OPTION, []);
        $current = isset($options[$id]) ? (string) $options[$id] : '';
        add_settings_field($id, $title, [$this, 'renderTextarea'], self::PAGE, $section, ['id' => $id, 'value' => $current]);
        unset($value);
    }

    private function number(string $id, string $title, string $value, string $description, string $section, int $min): void
    {
        add_settings_field($id, $title, [$this, 'renderNumber'], self::PAGE, $section, ['id' => $id, 'value' => $value, 'description' => $description, 'min' => $min]);
    }
}
