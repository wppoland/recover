<?php

declare(strict_types=1);

namespace Recover\Repository;

defined('ABSPATH') || exit;

use Recover\Model\AbandonedCart;
use wpdb;

/**
 * Data access for abandoned-cart records.
 *
 * The table name is built from $wpdb->prefix (own custom plugin table); it
 * therefore cannot be passed as a prepared parameter, so direct interpolation
 * is used for the table name only, with all values bound via placeholders.
 */
final class CartRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'recover_carts';
    }

    /**
     * Insert or update the cart snapshot keyed by session or user.
     *
     * @param array<int, array<string, mixed>> $contents
     */
    public function upsert(
        ?string $sessionKey,
        ?int $userId,
        ?string $email,
        array $contents,
        ?string $currency,
        float $total,
        int $itemCount,
        bool $consent,
    ): ?string {
        $existing = $this->findOpenBySessionOrUser($sessionKey, $userId);
        $now      = current_time('mysql', true);
        $json     = (string) wp_json_encode($contents);

        if ($existing !== null) {
            $data    = [
                'session_key'   => $sessionKey,
                'user_id'       => $userId,
                'cart_contents' => $json,
                'currency'      => $currency,
                'cart_total'    => $total,
                'item_count'    => $itemCount,
                'updated_at'    => $now,
            ];
            $formats = ['%s', '%d', '%s', '%s', '%f', '%d', '%s'];

            if ($email !== null && $email !== '') {
                $data['email']   = $email;
                $formats[]       = '%s';
                $data['consent'] = $consent ? 1 : 0;
                $formats[]       = '%d';
            }

            $this->wpdb->update($this->tableName(), $data, ['id' => $existing->id], $formats, ['%d']);

            return $existing->token;
        }

        $token = $this->generateToken();

        $this->wpdb->insert(
            $this->tableName(),
            [
                'token'         => $token,
                'session_key'   => $sessionKey,
                'user_id'       => $userId,
                'email'         => $email,
                'cart_contents' => $json,
                'currency'      => $currency,
                'cart_total'    => $total,
                'item_count'    => $itemCount,
                'status'        => AbandonedCart::STATUS_PENDING,
                'consent'       => $consent ? 1 : 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%s', '%d', '%s', '%s'],
        );

        return $token;
    }

    public function findOpenBySessionOrUser(?string $sessionKey, ?int $userId): ?AbandonedCart
    {
        $row = null;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, statement prepared with placeholders.
        if ($userId !== null && $userId > 0) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    'SELECT * FROM %i WHERE user_id = %d AND status IN (%s, %s) ORDER BY id DESC LIMIT 1',
                    $this->tableName(),
                    $userId,
                    AbandonedCart::STATUS_PENDING,
                    AbandonedCart::STATUS_ABANDONED,
                ),
            );
        }

        if ($row === null && $sessionKey !== null && $sessionKey !== '') {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    'SELECT * FROM %i WHERE session_key = %s AND status IN (%s, %s) ORDER BY id DESC LIMIT 1',
                    $this->tableName(),
                    $sessionKey,
                    AbandonedCart::STATUS_PENDING,
                    AbandonedCart::STATUS_ABANDONED,
                ),
            );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $row !== null ? AbandonedCart::fromRow($row) : null;
    }

    public function findByToken(string $token): ?AbandonedCart
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, statement prepared with placeholders.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE token = %s LIMIT 1',
                $this->tableName(),
                $token,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $row !== null ? AbandonedCart::fromRow($row) : null;
    }

    /**
     * Pending carts whose last update is older than the given UTC datetime and
     * that have a usable email + consent. Used by the abandonment sweeper.
     *
     * @return list<AbandonedCart>
     */
    public function findPendingOlderThan(string $beforeUtc, int $limit = 100): array
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, statement prepared with placeholders.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE status = %s AND updated_at <= %s AND email IS NOT NULL AND email <> %s AND consent = 1 ORDER BY updated_at ASC LIMIT %d',
                $this->tableName(),
                AbandonedCart::STATUS_PENDING,
                $beforeUtc,
                '',
                $limit,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map(
            static fn (object $row): AbandonedCart => AbandonedCart::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * Abandoned carts that have a contactable, consented email and still have
     * recovery emails left in the configured sequence.
     *
     * @return list<AbandonedCart>
     */
    public function findDueForEmail(int $limit = 50, int $maxEmails = 1): array
    {
        $maxEmails = max(1, $maxEmails);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, statement prepared with placeholders.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE status = %s AND emails_sent < %d AND consent = 1 AND email IS NOT NULL AND email <> %s ORDER BY abandoned_at ASC LIMIT %d',
                $this->tableName(),
                AbandonedCart::STATUS_ABANDONED,
                $maxEmails,
                '',
                $limit,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map(
            static fn (object $row): AbandonedCart => AbandonedCart::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    public function markAbandoned(int $id): void
    {
        $now = current_time('mysql', true);
        $this->wpdb->update(
            $this->tableName(),
            ['status' => AbandonedCart::STATUS_ABANDONED, 'abandoned_at' => $now, 'updated_at' => $now],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d'],
        );
    }

    public function markRecovered(int $id): void
    {
        $cart = $this->findById($id);

        $now = current_time('mysql', true);
        $this->wpdb->update(
            $this->tableName(),
            ['status' => AbandonedCart::STATUS_RECOVERED, 'recovered_at' => $now, 'updated_at' => $now],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d'],
        );

        if ($cart instanceof AbandonedCart) {
            /**
             * Fires after a cart is marked recovered.
             *
             * @param AbandonedCart $cart Cart snapshot before the status change.
             */
            do_action('recover/cart_recovered', $cart);
        }
    }

    public function findById(int $id): ?AbandonedCart
    {
        if ($id <= 0) {
            return null;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d LIMIT 1',
                $this->tableName(),
                $id,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $row instanceof \stdClass ? AbandonedCart::fromRow($row) : null;
    }

    public function recordEmailSent(int $id): void
    {
        $now = current_time('mysql', true);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, atomic increment.
        $this->wpdb->query(
            $this->wpdb->prepare(
                'UPDATE %i SET emails_sent = emails_sent + 1, last_email_at = %s, updated_at = %s WHERE id = %d',
                $this->tableName(),
                $now,
                $now,
                $id,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Resolve an open cart (pending/abandoned) for the given identifiers and
     * mark it recovered. Used when an order is placed.
     */
    public function markRecoveredBySessionOrUser(?string $sessionKey, ?int $userId): void
    {
        $cart = $this->findOpenBySessionOrUser($sessionKey, $userId);
        if ($cart !== null) {
            $this->markRecovered($cart->id);
        }
    }

    /**
     * Most recent carts for the admin list.
     *
     * @return list<AbandonedCart>
     */
    public function findRecent(int $limit = 100): array
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, statement prepared with placeholders.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i ORDER BY updated_at DESC LIMIT %d',
                $this->tableName(),
                $limit,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map(
            static fn (object $row): AbandonedCart => AbandonedCart::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * Carts whose email matches the search term, newest first.
     *
     * Used by the admin carts list page only.
     *
     * @return list<AbandonedCart>
     */
    public function searchByEmail(string $term, int $limit = 200): array
    {
        $like = '%' . $this->wpdb->esc_like($term) . '%';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table, statement prepared with placeholders.
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE email LIKE %s ORDER BY updated_at DESC LIMIT %d',
                $this->tableName(),
                $like,
                $limit,
            ),
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return array_map(
            static fn (object $row): AbandonedCart => AbandonedCart::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * Aggregate counts per status for the dashboard summary.
     *
     * @return array{pending:int, abandoned:int, recovered:int}
     */
    public function statusCounts(): array
    {
        $table = $this->tableName();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own custom plugin table; aggregate over fixed status column.
        $rows = $this->wpdb->get_results(
            "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $counts = [
            AbandonedCart::STATUS_PENDING   => 0,
            AbandonedCart::STATUS_ABANDONED => 0,
            AbandonedCart::STATUS_RECOVERED => 0,
        ];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->total;
            }
        }

        return [
            'pending'   => $counts[AbandonedCart::STATUS_PENDING],
            'abandoned' => $counts[AbandonedCart::STATUS_ABANDONED],
            'recovered' => $counts[AbandonedCart::STATUS_RECOVERED],
        ];
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete($this->tableName(), ['id' => $id], ['%d']);
    }

    /**
     * Erase all stored carts for a given email address (privacy data wipe).
     */
    public function deleteByEmail(string $email): int
    {
        return (int) $this->wpdb->delete($this->tableName(), ['email' => $email], ['%s']);
    }

    private function generateToken(): string
    {
        // 64 hex chars, cryptographically secure, unguessable (no IDOR).
        return bin2hex(random_bytes(32));
    }
}
