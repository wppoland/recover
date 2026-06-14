<?php

declare(strict_types=1);

namespace Recover\Admin;

defined('ABSPATH') || exit;

use Recover\Contract\HasHooks;
use Recover\Model\AbandonedCart;
use Recover\Repository\CartRepository;

/**
 * Admin list of abandoned / recovered carts under WooCommerce → Recover → Carts.
 */
final class CartsPage implements HasHooks
{
    private const PAGE       = 'recover-carts';
    private const NONCE_WIPE = 'recover_wipe_email';

    public function __construct(
        private readonly CartRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'maybeWipeEmail']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Recover - Carts', 'recover'),
            __('Recover Carts', 'recover'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    /**
     * Privacy: erase every stored cart for a given email address.
     */
    public function maybeWipeEmail(): void
    {
        if (! isset($_POST['recover_wipe_email'], $_POST['_wpnonce']) || ! current_user_can('manage_woocommerce')) {
            return;
        }

        if (! wp_verify_nonce(sanitize_key(wp_unslash((string) $_POST['_wpnonce'])), self::NONCE_WIPE)) {
            wp_die(esc_html__('Security check failed.', 'recover'));
        }

        $email = sanitize_email(wp_unslash((string) $_POST['recover_wipe_email']));
        if ($email !== '' && is_email($email)) {
            $this->repository->deleteByEmail($email);
        }

        wp_safe_redirect(add_query_arg('recover_wiped', '1', admin_url('admin.php?page=' . self::PAGE)));
        exit;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $counts = $this->repository->statusCounts();
        $rows   = $this->repository->findRecent(200);
        $total  = $counts['abandoned'] + $counts['recovered'];
        $rate   = $total > 0 ? round(($counts['recovered'] / $total) * 100, 1) : 0.0;
        ?>
        <div class="wrap recover-wrap">
            <h1><?php esc_html_e('Recover - Abandoned Carts', 'recover'); ?></h1>

            <?php if (isset($_GET['recover_wiped'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cart data for that email address has been erased.', 'recover'); ?></p></div>
            <?php endif; ?>

            <div class="recover-cards">
                <div class="recover-card"><span class="recover-card__num"><?php echo esc_html((string) $counts['pending']); ?></span><span class="recover-card__label"><?php esc_html_e('Pending', 'recover'); ?></span></div>
                <div class="recover-card"><span class="recover-card__num"><?php echo esc_html((string) $counts['abandoned']); ?></span><span class="recover-card__label"><?php esc_html_e('Abandoned', 'recover'); ?></span></div>
                <div class="recover-card"><span class="recover-card__num"><?php echo esc_html((string) $counts['recovered']); ?></span><span class="recover-card__label"><?php esc_html_e('Recovered', 'recover'); ?></span></div>
                <div class="recover-card recover-card--accent"><span class="recover-card__num"><?php echo esc_html((string) $rate); ?>%</span><span class="recover-card__label"><?php esc_html_e('Recovery rate', 'recover'); ?></span></div>
            </div>

            <?php if ($rows === []) : ?>
                <p><?php esc_html_e('No carts recorded yet. Once shoppers add items and leave without checking out, they will appear here.', 'recover'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Email', 'recover'); ?></th>
                            <th scope="col"><?php esc_html_e('Items', 'recover'); ?></th>
                            <th scope="col"><?php esc_html_e('Value', 'recover'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'recover'); ?></th>
                            <th scope="col"><?php esc_html_e('Emails', 'recover'); ?></th>
                            <th scope="col"><?php esc_html_e('Last activity', 'recover'); ?></th>
                            <th scope="col"><?php esc_html_e('Privacy', 'recover'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $cart) : ?>
                            <tr>
                                <td><?php echo esc_html($cart->email ?? __('(no email yet)', 'recover')); ?></td>
                                <td><?php echo esc_html((string) $cart->itemCount); ?></td>
                                <td><?php echo wp_kses_post(wc_price($cart->cartTotal, ['currency' => $cart->currency ?? ''])); ?></td>
                                <td><span class="recover-badge recover-badge--<?php echo esc_attr($cart->status); ?>"><?php echo esc_html($this->statusLabel($cart->status)); ?></span></td>
                                <td><?php echo esc_html((string) $cart->emailsSent); ?></td>
                                <td><?php echo esc_html($cart->updatedAt->format('Y-m-d H:i')); ?></td>
                                <td>
                                    <?php if ($cart->email !== null) : ?>
                                        <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Erase all stored cart data for this email address?', 'recover')); ?>');" style="display:inline;">
                                            <?php wp_nonce_field(self::NONCE_WIPE); ?>
                                            <input type="hidden" name="recover_wipe_email" value="<?php echo esc_attr($cart->email); ?>" />
                                            <button type="submit" class="button-link delete"><?php esc_html_e('Erase', 'recover'); ?></button>
                                        </form>
                                    <?php else : ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AbandonedCart::STATUS_PENDING   => __('Pending', 'recover'),
            AbandonedCart::STATUS_ABANDONED => __('Abandoned', 'recover'),
            AbandonedCart::STATUS_RECOVERED => __('Recovered', 'recover'),
            default                         => $status,
        };
    }
}
