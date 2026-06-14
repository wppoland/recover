=== Recover - Abandoned Cart Recovery for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, abandoned cart, cart recovery, email, ecommerce
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts: capture the email early, save the cart, email a secure one-click link to finish checkout.

== Description ==

Recover captures WooCommerce carts that shoppers leave behind and emails them a secure, one-click link that puts every item straight back into their cart so they can finish checking out. It runs entirely on your own site — no third-party service, no data leaves your store.

**How it works**

1. As soon as a shopper has items in the cart, Recover saves a private snapshot of that cart.
2. The customer email is captured early — automatically for logged-in customers, and (with consent) from the checkout email field for guests.
3. If checkout is not completed within a window you choose, the cart is marked **abandoned**.
4. On the next scheduled run, Recover emails a recovery message containing a secure, tokenised restore link.
5. One click on that link repopulates the cart and sends the shopper back to checkout. Recovered carts are tracked separately so you can see your recovery rate.

**Why Recover?**

* **Self-hosted.** Emails are sent through your own WordPress mailer (`wp_mail`). Cart data lives in a single custom table in your own database. Nothing is sent to any external service.
* **Privacy-minded.** Guest email capture is gated behind an explicit consent checkbox (configurable). Restore links are unguessable 64-character random tokens — no customer ids in the URL, no personal data leakage. A one-click data-wipe erases every stored cart for a given email address.
* **Secure by design.** All output is escaped, all input sanitised, every admin form and AJAX call is nonce-protected, and admin pages require the `manage_woocommerce` capability.
* **Lightweight.** A tiny vanilla-JavaScript snippet (no jQuery) handles early email capture on checkout, loaded deferred. The recovery worker runs on WordPress cron and is fully idempotent — re-runs never double-send.
* **Clean install.** One custom `{prefix}_recover_carts` table, version-tracked. Deleting the plugin drops the table, removes its options, and clears the scheduled task.

**Features**

* Automatic cart snapshots whenever the cart changes
* Early email capture for logged-in customers and (consent-gated) guests
* Configurable abandonment window and email delay
* Secure, tokenised one-click restore link that repopulates the cart
* Recovery email sent on a WordPress cron schedule via `wp_mail`
* Abandoned / recovered / pending cart list with a recovery-rate summary
* Customisable email subject, heading, body and button text
* GDPR-friendly consent checkbox and one-click per-email data wipe
* Compatible with WooCommerce HPOS (Custom Order Tables) and Cart/Checkout Blocks

== Installation ==

1. Install and activate WooCommerce (8.0 or later).
2. Install Recover from the WordPress plugin directory, or upload the `recover` folder to `/wp-content/plugins/`.
3. Activate the plugin through the **Plugins** screen.
4. Visit **WooCommerce → Recover** to set your timing and customise the email; sensible defaults work out of the box.
5. Abandoned carts and your recovery rate appear under **WooCommerce → Recover Carts**.

== Frequently Asked Questions ==

= Is Recover free? =
Yes. Recover is free and licensed under the GPL.

= Does Recover require WooCommerce? =
Yes. Recover is a WooCommerce extension and requires WooCommerce 8.0 or later. It shows an admin notice and stays inactive if WooCommerce is missing or out of date.

= How is the recovery email sent? =
On a WordPress cron schedule (hourly by default). Each run marks carts that have been inactive past your window as abandoned, then emails a recovery link to any abandoned cart that is due, using your own site mailer (`wp_mail`). The worker is idempotent, so it never double-sends — each cart receives a single recovery email.

= Is the restore link safe? =
Yes. Each cart has a 64-character cryptographically random token. The restore link contains only that token — no customer id, no email, nothing personal. Without the exact token a cart cannot be restored, so there is no enumeration or IDOR risk.

= Does this comply with GDPR / consent requirements? =
Guest email capture only happens after the shopper ticks a consent checkbox (you can edit the wording, and consent can be required or not). Cart data is stored only in your own database and never sent to any third party. From **WooCommerce → Recover Carts** you can erase all stored cart data for any email address in one click. You remain responsible for your store's privacy policy.

= Where is cart data stored? =
In a custom `{prefix}_recover_carts` table in your WordPress database. Nothing is sent anywhere else.

= How do I remove all plugin data? =
Deleting the plugin from the **Plugins** screen runs the uninstall routine, which drops the `{prefix}_recover_carts` table, removes the `recover_settings` and `recover_db_version` options, and clears the scheduled recovery task.

== External Services ==

Recover does not connect to any external services. Recovery emails are sent through your own site's WordPress mailer (`wp_mail`), and all cart data stays in your WordPress database.

== Screenshots ==

1. Abandoned cart list with pending / abandoned / recovered counts and recovery rate.
2. Settings page — timing, consent, and recovery email customisation.
3. The recovery email with its one-click "Complete my order" button.

== Changelog ==

= 0.1.0 =
* Initial release.
