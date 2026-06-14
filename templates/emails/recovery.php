<?php
/**
 * Recovery email template (HTML).
 *
 * Available variables (all prefixed with recover_):
 *
 * @var string $recover_heading
 * @var string $recover_body
 * @var string $recover_button
 * @var string $recover_restore_url
 * @var string $recover_site_name
 *
 * @package Recover
 */

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo esc_html($recover_heading); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2329;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e3e6ea;">
                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111827;">
                                <?php echo esc_html($recover_heading); ?>
                            </h1>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#374151;">
                                <?php echo esc_html($recover_body); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 32px 8px;">
                            <a href="<?php echo esc_url($recover_restore_url); ?>"
                               style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;padding:14px 28px;border-radius:8px;">
                                <?php echo esc_html($recover_button); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 32px;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;">
                                <?php
                                printf(
                                    /* translators: %s: site name */
                                    esc_html__('This message was sent by %s because a cart was left behind. If this was not you, you can safely ignore this email.', 'recover'),
                                    esc_html($recover_site_name),
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
