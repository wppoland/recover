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

Recover captures WooCommerce carts that shoppers leave behind and emails them a secure, one-click link that puts every item straight back into their cart so they can finish checking out. It runs entirely on your own site: no third-party service, no data leaves your store.

Because everything happens on your own server, you can read exactly what it does. The full source lives at https://github.com/wppoland/recover, which is also where to file a bug or request a feature.

**How it works**

1. As soon as a shopper has items in the cart, Recover saves a private snapshot of that cart.
2. The customer email is captured early — automatically for logged-in customers, and (with consent) from the checkout email field for guests.
3. If checkout is not completed within a window you choose, the cart is marked **abandoned**.
4. On the next scheduled run, Recover emails a recovery message containing a secure, tokenised restore link.
5. One click on that link repopulates the cart and sends the shopper back to checkout. Recovered carts are tracked separately so you can see your recovery rate.

**A few things worth knowing**

Emails go out through your own WordPress mailer (`wp_mail`), and cart data lives in a single custom table (`{prefix}_recover_carts`) in your database. Nothing is sent to an external service.

Guest email capture only happens after the shopper ticks a consent checkbox, and you can edit the wording or turn the requirement off. Restore links carry an unguessable 64-character random token and nothing else: no customer id, no email in the URL. From the carts screen you can wipe every stored cart for a single email address in one click.

On the implementation side, all output is escaped and all input sanitised, every admin form and AJAX request is nonce-checked, and the admin pages need the `manage_woocommerce` capability. Early email capture uses a small vanilla-JavaScript snippet (no jQuery) loaded in the footer; the recovery worker runs on WordPress cron and is idempotent, so a re-run never sends a second email for the same cart. Deleting the plugin drops its table, removes its two options, and clears the scheduled task.

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
