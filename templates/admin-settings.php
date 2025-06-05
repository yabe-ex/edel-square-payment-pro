<div class="wrap">
    <h1>Square決済設定</h1>

    <?php settings_errors('edel_square_settings'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('edel_square_settings_nonce'); ?>

        <div class="edel-square-admin-tabs">
            <div class="nav-tab-wrapper">
                <a href="#tab-api" class="nav-tab nav-tab-active">API設定</a>
                <a href="#tab-email" class="nav-tab">メール設定</a>
                <a href="#tab-form" class="nav-tab">フォーム設定</a>
                <a href="#tab-account" class="nav-tab">アカウント設定</a>
                <a href="#tab-recaptcha" class="nav-tab">reCAPTCHA設定</a>
            </div>

            <!-- API設定タブ -->
            <div id="tab-api" class="tab-content">
                <h2>Square API設定</h2>
                <p>Square開発者ダッシュボードで取得したAPIキー情報を入力してください。</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">動作モード</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sandbox_mode" value="1" <?php checked($settings['sandbox_mode'], '1'); ?>>
                                テストモードを有効化（Sandbox環境を使用）
                            </label>
                            <p class="description">本番環境では必ずチェックを外してください。</p>
                        </td>
                    </tr>
                </table>

                <h3>Sandbox環境設定</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Sandbox Access Token</th>
                        <td>
                            <input type="text" name="sandbox_access_token" class="regular-text" value="<?php echo esc_attr($settings['sandbox_access_token']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sandbox Application ID</th>
                        <td>
                            <input type="text" name="sandbox_application_id" class="regular-text" value="<?php echo esc_attr($settings['sandbox_application_id']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sandbox Location ID</th>
                        <td>
                            <input type="text" name="sandbox_location_id" class="regular-text" value="<?php echo esc_attr($settings['sandbox_location_id']); ?>">
                        </td>
                    </tr>
                </table>

                <h3>本番環境設定</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Production Access Token</th>
                        <td>
                            <input type="text" name="production_access_token" class="regular-text" value="<?php echo esc_attr($settings['production_access_token']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Production Application ID</th>
                        <td>
                            <input type="text" name="production_application_id" class="regular-text" value="<?php echo esc_attr($settings['production_application_id']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Production Location ID</th>
                        <td>
                            <input type="text" name="production_location_id" class="regular-text" value="<?php echo esc_attr($settings['production_location_id']); ?>">
                        </td>
                    </tr>
                </table>

                <!-- <div class="card">
                    <h2 class="title"><?php _e('サブスクリプション決済処理', 'edel-square-payment-pro'); ?></h2>
                    <p><?php _e('この機能を使用すると、本日が決済日のすべてのアクティブなサブスクリプションに対して決済処理を実行します。', 'edel-square-payment-pro'); ?></p>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="edel_square_process_subscriptions_admin">
                        <?php wp_nonce_field('edel_square_process_subscriptions'); ?>
                        <p class="submit">
                            <input type="submit" name="submit" class="button button-primary" value="<?php _e('決済処理を実行', 'edel-square-payment-pro'); ?>">
                        </p>
                    </form>

                    <p class="description">
                        <?php _e('注意: この処理は、サーバーのCronジョブによって毎日自動的に実行されます。手動で実行する必要があるのは、テストや緊急時のみです。', 'edel-square-payment-pro'); ?>
                    </p>
                </div> -->
            </div>

            <!-- メール設定タブ -->
            <div id="tab-email" class="tab-content" style="display: none;">
                <h2>メール設定</h2>

                <!-- 基本メール設定 -->
                <table class="form-table">
                    <tr>
                        <th scope="row">送信者名</th>
                        <td>
                            <input type="text" name="sender_name" class="regular-text" value="<?php echo esc_attr($settings['sender_name']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">送信元メールアドレス</th>
                        <td>
                            <input type="email" name="sender_email" class="regular-text" value="<?php echo esc_attr($settings['sender_email']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">管理者メールアドレス</th>
                        <td>
                            <input type="email" name="admin_email" class="regular-text" value="<?php echo esc_attr($settings['admin_email']); ?>">
                            <p class="description">決済通知を受け取るメールアドレスを指定します。</p>
                        </td>
                    </tr>
                </table>
                <hr>
                <!-- 1. 買い切り決済メール設定 -->
                <h2>買い切り決済 メール通知設定</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">メール通知機能</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_onetime_payment_notification" value="1"
                                    <?php checked('1', $settings['enable_onetime_payment_notification'] ?? '1'); ?> />
                                買い切り決済完了時にメール通知を送信する
                            </label>
                            <p class="description">チェックを外すとメール通知が無効になります。</p>
                        </td>
                    </tr>
                </table>

                <h3>管理者向けメール設定</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="admin_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="admin_email_subject" name="admin_email_subject"
                                value="<?php echo esc_attr($settings['admin_email_subject']); ?>"
                                class="regular-text" />
                            <p class="description">管理者向けメールの件名を設定してください。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="admin_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="admin_email_body" name="admin_email_body" rows="8" cols="50" class="large-text"><?php echo esc_textarea($settings['admin_email_body']); ?></textarea>
                            <p class="description">管理者向けメールの本文を設定してください。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-onetime-admin-email" class="button button-secondary">管理者向けメールをプレビュー</button>
                            <p class="description">設定したメール内容をプレビューできます。</p>
                        </td>
                    </tr>
                </table>

                <h3>購入者向けメール設定</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="customer_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="customer_email_subject" name="customer_email_subject"
                                value="<?php echo esc_attr($settings['customer_email_subject']); ?>"
                                class="regular-text" />
                            <p class="description">購入者向けメールの件名を設定してください。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="customer_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="customer_email_body" name="customer_email_body" rows="8" cols="50" class="large-text"><?php echo esc_textarea($settings['customer_email_body']); ?></textarea>
                            <p class="description">購入者向けメールの本文を設定してください。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-onetime-customer-email" class="button button-secondary">購入者向けメールをプレビュー</button>
                            <p class="description">設定したメール内容をプレビューできます。</p>
                        </td>
                    </tr>
                </table>

                <hr>
                <h2>サブスクリプション決済 メール通知設定</h2>

                <h3>サブスクリプション登録完了通知</h3>
                <h4>管理者向けメール設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_admin_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="subscription_admin_email_subject" name="subscription_admin_email_subject"
                                value="<?php echo esc_attr($settings['subscription_admin_email_subject']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscription_admin_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="subscription_admin_email_body" name="subscription_admin_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_admin_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {billing_cycle}, {customer_email}, {subscription_id}, {transaction_date}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-subscription-admin-email" class="button button-secondary">管理者向けメールをプレビュー</button>
                        </td>
                    </tr>
                </table>

                <h4>購入者向けメール設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_customer_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="subscription_customer_email_subject" name="subscription_customer_email_subject"
                                value="<?php echo esc_attr($settings['subscription_customer_email_subject']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscription_customer_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="subscription_customer_email_body" name="subscription_customer_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_customer_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {billing_cycle}, {subscription_id}, {transaction_date}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-subscription-customer-email" class="button button-secondary">購入者向けメールをプレビュー</button>
                        </td>
                    </tr>
                </table>

                <h3>サブスクリプション継続決済完了通知</h3>
                <h4>管理者向けメール設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_payment_admin_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="subscription_payment_admin_email_subject" name="subscription_payment_admin_email_subject"
                                value="<?php echo esc_attr($settings['subscription_payment_admin_email_subject']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscription_payment_admin_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="subscription_payment_admin_email_body" name="subscription_payment_admin_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_payment_admin_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {customer_email}, {subscription_id}, {payment_id}, {transaction_date}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-subscription-payment-admin-email" class="button button-secondary">管理者向けメールをプレビュー</button>
                        </td>
                    </tr>
                </table>

                <h4>購入者向けメール設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_payment_customer_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="subscription_payment_customer_email_subject" name="subscription_payment_customer_email_subject"
                                value="<?php echo esc_attr($settings['subscription_payment_customer_email_subject']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscription_payment_customer_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="subscription_payment_customer_email_body" name="subscription_payment_customer_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_payment_customer_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {subscription_id}, {payment_id}, {transaction_date}, {next_billing_date}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-subscription-payment-customer-email" class="button button-secondary">購入者向けメールをプレビュー</button>
                        </td>
                    </tr>
                </table>

                <hr>
                <h2>サブスクリプションキャンセル メール通知設定</h2>

                <h3>管理者向けキャンセル通知</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_cancel_admin_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="subscription_cancel_admin_email_subject" name="subscription_cancel_admin_email_subject"
                                value="<?php echo esc_attr($settings['subscription_cancel_admin_email_subject']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscription_cancel_admin_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="subscription_cancel_admin_email_body" name="subscription_cancel_admin_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_cancel_admin_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {customer_email}, {subscription_id}, {cancel_date}, {cancellation_type}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-cancel-admin-email" class="button button-secondary">管理者向けメールをプレビュー</button>
                        </td>
                    </tr>
                </table>

                <h3>購入者向けキャンセル通知</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_cancel_customer_email_subject">件名</label>
                        </th>
                        <td>
                            <input type="text" id="subscription_cancel_customer_email_subject" name="subscription_cancel_customer_email_subject"
                                value="<?php echo esc_attr($settings['subscription_cancel_customer_email_subject']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subscription_cancel_customer_email_body">本文</label>
                        </th>
                        <td>
                            <textarea id="subscription_cancel_customer_email_body" name="subscription_cancel_customer_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_cancel_customer_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {subscription_id}, {cancel_date}, {cancellation_type}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-cancel-customer-email" class="button button-secondary">購入者向けメールをプレビュー</button>
                        </td>
                    </tr>
                </table>

                <hr>
                <h3>用可能なプレースホルダー一覧</h3>
                <div class="notice notice-info inline">
                    <p><strong>全メール共通で利用可能：</strong></p>
                    <ul style="margin-left: 20px;">
                        <li><code>{site_name}</code> - サイト名</li>
                        <li><code>{site_url}</code> - サイトURL</li>
                        <li><code>{user_name}</code> - 購入者名</li>
                        <li><code>{user_id}</code> - ユーザーID</li>
                        <li><code>{customer_email}</code> - 購入者メールアドレス</li>
                        <li><code>{transaction_date}</code> - 決済日時</li>
                    </ul>
                    <p><strong>決済関連：</strong></p>
                    <ul style="margin-left: 20px;">
                        <li><code>{item_name}</code> - 商品名/プラン名</li>
                        <li><code>{amount}</code> - 金額</li>
                        <li><code>{payment_id}</code> - 決済ID</li>
                    </ul>
                    <p><strong>サブスクリプション関連：</strong></p>
                    <ul style="margin-left: 20px;">
                        <li><code>{subscription_id}</code> - サブスクリプションID</li>
                        <li><code>{billing_cycle}</code> - 課金周期</li>
                        <li><code>{next_billing_date}</code> - 次回請求日</li>
                        <li><code>{cancel_date}</code> - キャンセル日</li>
                        <li><code>{cancellation_type}</code> - キャンセル種別</li>
                    </ul>
                </div>
                <script>
                    jQuery(document).ready(function($) {
                        // 共通のサンプルデータ
                        const baseSampleData = {
                            '{site_name}': '<?php echo esc_js(get_bloginfo('name')); ?>',
                            '{site_url}': '<?php echo esc_js(get_bloginfo('url')); ?>',
                            '{user_name}': '山田太郎',
                            '{user_id}': '123',
                            '{customer_email}': 'customer@example.com',
                            '{transaction_date}': '<?php echo esc_js(date_i18n('Y年m月d日 H:i')); ?>'
                        };

                        // 買い切り決済用サンプルデータ
                        const onetimeSampleData = {
                            ...baseSampleData,
                            '{item_name}': 'サンプル商品',
                            '{amount}': '1,000',
                            '{payment_id}': 'pay_sample123'
                        };

                        // サブスクリプション用サンプルデータ
                        const subscriptionSampleData = {
                            ...baseSampleData,
                            '{item_name}': 'プレミアムプラン',
                            '{amount}': '2,980',
                            '{subscription_id}': 'sub_sample456',
                            '{billing_cycle}': '月額',
                            '{payment_id}': 'pay_sub789',
                            '{next_billing_date}': '<?php echo esc_js(date_i18n('Y年m月d日', strtotime('+1 month'))); ?>'
                        };

                        // キャンセル用サンプルデータ
                        const cancelSampleData = {
                            ...baseSampleData,
                            '{item_name}': 'プレミアムプラン',
                            '{amount}': '2,980',
                            '{subscription_id}': 'sub_sample456',
                            '{cancel_date}': '<?php echo esc_js(date_i18n('Y年m月d日')); ?>',
                            '{cancellation_type}': 'ユーザー都合によるキャンセル'
                        };

                        // プレビュー機能共通関数
                        function showEmailPreview(title, subject, body, sampleData) {
                            let previewSubject = subject;
                            let previewBody = body;

                            Object.keys(sampleData).forEach(placeholder => {
                                const regex = new RegExp(placeholder.replace(/[{}]/g, '\\$&'), 'g');
                                previewSubject = previewSubject.replace(regex, sampleData[placeholder]);
                                previewBody = previewBody.replace(regex, sampleData[placeholder]);
                            });

                            const previewWindow = window.open('', '_blank', 'width=800,height=700,scrollbars=yes');
                            previewWindow.document.write(`
            <!DOCTYPE html>
            <html lang="ja">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>${title}</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        padding: 20px;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .container {
                        max-width: 700px;
                        width: 100%;
                        background: white;
                        border-radius: 12px;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                        overflow: hidden;
                    }
                    .header {
                        background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                        color: white;
                        padding: 30px;
                        text-align: center;
                    }
                    .header h1 {
                        font-size: 24px;
                        font-weight: 600;
                        margin-bottom: 8px;
                    }
                    .header .subtitle {
                        opacity: 0.9;
                        font-size: 14px;
                    }
                    .content {
                        padding: 40px;
                    }
                    .subject-section {
                        background: #f8fafc;
                        padding: 20px;
                        border-radius: 8px;
                        margin-bottom: 30px;
                        border-left: 4px solid #0073aa;
                    }
                    .subject-label {
                        font-size: 12px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        color: #64748b;
                        margin-bottom: 8px;
                        font-weight: 600;
                    }
                    .subject-text {
                        font-size: 18px;
                        font-weight: 600;
                        color: #1e293b;
                        line-height: 1.4;
                    }
                    .body-section {
                        background: #ffffff;
                        border: 1px solid #e2e8f0;
                        border-radius: 8px;
                        padding: 30px;
                    }
                    .body-label {
                        font-size: 12px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        color: #64748b;
                        margin-bottom: 15px;
                        font-weight: 600;
                    }
                    .body-text {
                        white-space: pre-wrap;
                        line-height: 1.7;
                        color: #374151;
                        font-size: 15px;
                    }
                    .footer {
                        background: #f1f5f9;
                        padding: 20px 40px;
                        text-align: center;
                        color: #64748b;
                        font-size: 13px;
                        border-top: 1px solid #e2e8f0;
                    }
                    .footer .warning {
                        background: #fef3c7;
                        color: #92400e;
                        padding: 8px 12px;
                        border-radius: 6px;
                        display: inline-block;
                        margin-top: 10px;
                        font-weight: 500;
                    }
                    @media (max-width: 600px) {
                        body { padding: 10px; }
                        .container { margin: 10px; }
                        .content, .footer { padding: 20px; }
                        .header { padding: 20px; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>${title}</h1>
                        <div class="subtitle">メール内容プレビュー</div>
                    </div>
                    <div class="content">
                        <div class="subject-section">
                            <div class="subject-label">件名</div>
                            <div class="subject-text">${previewSubject}</div>
                        </div>
                        <div class="body-section">
                            <div class="body-label">本文</div>
                            <div class="body-text">${previewBody}</div>
                        </div>
                    </div>
                    <div class="footer">
                        <div>Square決済プラグイン - メールプレビュー機能</div>
                        <div class="warning">⚠️ このプレビューはサンプルデータを使用しています</div>
                    </div>
                </div>
            </body>
            </html>
        `);
                        }

                        // 1. 買い切り決済メールプレビュー
                        $('#preview-onetime-admin-email').on('click', function() {
                            const subject = $('#admin_email_subject').val();
                            const body = $('#admin_email_body').val();
                            showEmailPreview('📦 買い切り決済 - 管理者向け通知', subject, body, onetimeSampleData);
                        });

                        $('#preview-onetime-customer-email').on('click', function() {
                            const subject = $('#customer_email_subject').val();
                            const body = $('#customer_email_body').val();
                            showEmailPreview('📦 買い切り決済 - 購入者向け通知', subject, body, onetimeSampleData);
                        });

                        // 2. サブスクリプション登録メールプレビュー
                        $('#preview-subscription-admin-email').on('click', function() {
                            const subject = $('#subscription_admin_email_subject').val();
                            const body = $('#subscription_admin_email_body').val();
                            showEmailPreview('🔄 サブスクリプション登録 - 管理者向け通知', subject, body, subscriptionSampleData);
                        });

                        $('#preview-subscription-customer-email').on('click', function() {
                            const subject = $('#subscription_customer_email_subject').val();
                            const body = $('#subscription_customer_email_body').val();
                            showEmailPreview('🔄 サブスクリプション登録 - 購入者向け通知', subject, body, subscriptionSampleData);
                        });

                        // 3. サブスクリプション継続決済メールプレビュー
                        $('#preview-subscription-payment-admin-email').on('click', function() {
                            const subject = $('#subscription_payment_admin_email_subject').val();
                            const body = $('#subscription_payment_admin_email_body').val();
                            showEmailPreview('🔄 サブスクリプション継続決済 - 管理者向け通知', subject, body, subscriptionSampleData);
                        });

                        $('#preview-subscription-payment-customer-email').on('click', function() {
                            const subject = $('#subscription_payment_customer_email_subject').val();
                            const body = $('#subscription_payment_customer_email_body').val();
                            showEmailPreview('🔄 サブスクリプション継続決済 - 購入者向け通知', subject, body, subscriptionSampleData);
                        });

                        // 4. サブスクリプションキャンセルメールプレビュー
                        $('#preview-cancel-admin-email').on('click', function() {
                            const subject = $('#subscription_cancel_admin_email_subject').val();
                            const body = $('#subscription_cancel_admin_email_body').val();
                            showEmailPreview('❌ サブスクリプションキャンセル - 管理者向け通知', subject, body, cancelSampleData);
                        });

                        $('#preview-cancel-customer-email').on('click', function() {
                            const subject = $('#subscription_cancel_customer_email_subject').val();
                            const body = $('#subscription_cancel_customer_email_body').val();
                            showEmailPreview('❌ サブスクリプションキャンセル - 購入者向け通知', subject, body, cancelSampleData);
                        });

                        // プレビューボタンのスタイリング
                        $('[id^="preview-"]').css({
                            'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                            'color': 'white',
                            'border': 'none',
                            'padding': '10px 20px',
                            'border-radius': '6px',
                            'cursor': 'pointer',
                            'font-weight': '500',
                            'transition': 'all 0.3s ease',
                            'box-shadow': '0 2px 4px rgba(0,0,0,0.1)'
                        }).hover(
                            function() {
                                $(this).css({
                                    'transform': 'translateY(-2px)',
                                    'box-shadow': '0 4px 8px rgba(0,0,0,0.2)'
                                });
                            },
                            function() {
                                $(this).css({
                                    'transform': 'translateY(0)',
                                    'box-shadow': '0 2px 4px rgba(0,0,0,0.1)'
                                });
                            }
                        );

                        // プレースホルダーのツールチップ機能
                        $('code').css({
                            'background-color': '#f1f5f9',
                            'padding': '2px 6px',
                            'border-radius': '4px',
                            'font-family': 'Monaco, Consolas, monospace',
                            'font-size': '13px',
                            'color': '#374151',
                            'border': '1px solid #e2e8f0'
                        }).hover(
                            function() {
                                $(this).css('background-color', '#e2e8f0');
                            },
                            function() {
                                $(this).css('background-color', '#f1f5f9');
                            }
                        );

                        // セクション見出しのスタイリング強化
                        $('#tab-email h2').css({
                            'border-left': '4px solid #0073aa',
                            'padding-left': '15px',
                            'margin-top': '30px',
                            'margin-bottom': '20px'
                        });

                        $('#tab-email h3').css({
                            'color': '#374151',
                            'border-bottom': '2px solid #f1f5f9',
                            'padding-bottom': '8px',
                            'margin-top': '25px',
                            'margin-bottom': '15px'
                        });

                        // プレースホルダー説明エリアのスタイリング
                        $('.notice.notice-info').css({
                            'border-left-color': '#3b82f6',
                            'background-color': '#eff6ff'
                        });
                    });
                </script>
            </div>

            <!-- フォーム設定タブ -->
            <div id="tab-form" class="tab-content" style="display: none;">
                <h2>フォーム設定</h2>

                <h3>同意チェックボックス設定</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">表示設定</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_consent_checkbox" value="1" <?php checked($settings['show_consent_checkbox'], '1'); ?>>
                                決済フォームに同意チェックボックスを表示する
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プライバシーポリシーページ</th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'privacy_policy_page',
                                'show_option_none' => '-- 選択してください --',
                                'option_none_value' => '0',
                                'selected' => $settings['privacy_policy_page'],
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">利用規約ページ</th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'terms_page',
                                'show_option_none' => '-- 選択してください --',
                                'option_none_value' => '0',
                                'selected' => $settings['terms_page'],
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">同意文言</th>
                        <td>
                            <textarea name="consent_text" rows="4" class="large-text"><?php echo esc_textarea($settings['consent_text']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: [privacy_policy_link], [terms_link]</p>
                        </td>
                    </tr>
                </table>

                <h3>決済成功時メッセージ</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">成功メッセージ</th>
                        <td>
                            <textarea name="success_message" rows="4" class="large-text"><?php echo esc_textarea($settings['success_message']); ?></textarea>
                            <p class="description">
                                <strong>買い切り決済</strong>とサブスクリプション登録完了時に表示されるメッセージです。<br>
                                改行を入れる場合は通常通り改行してください。HTMLタグは使用できません。
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">プレビュー</th>
                        <td>
                            <button type="button" id="preview-success-message" class="button button-secondary">成功メッセージをプレビュー</button>
                            <p class="description">設定したメッセージ内容をプレビューできます。</p>
                        </td>
                    </tr>
                </table>

                <script>
                    // 成功メッセージのプレビュー機能
                    jQuery(document).ready(function($) {
                        $('#preview-success-message').on('click', function() {
                            const message = $('textarea[name="success_message"]').val();

                            const previewWindow = window.open('', '_blank', 'width=600,height=400,scrollbars=yes');
                            previewWindow.document.write(`
            <!DOCTYPE html>
            <html lang="ja">
            <head>
                <meta charset="UTF-8">
                <title>成功メッセージプレビュー</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                        padding: 20px;
                        background-color: #f5f5f5;
                    }
                    .preview-container {
                        max-width: 500px;
                        margin: 0 auto;
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .success-message {
                        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                        border: 1px solid #c3e6cb;
                        border-left: 4px solid #28a745;
                        border-radius: 8px;
                        padding: 20px;
                        color: #155724;
                        font-size: 16px;
                        line-height: 1.6;
                        font-weight: 500;
                        white-space: pre-wrap;
                    }
                    .success-message::before {
                        content: "✅ ";
                        font-size: 18px;
                        margin-right: 8px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 20px;
                        color: #333;
                    }
                </style>
            </head>
            <body>
                <div class="preview-container">
                    <div class="header">
                        <h2>決済成功メッセージプレビュー</h2>
                        <p>買い切り決済・サブスクリプション決済完了時に表示されます</p>
                    </div>
                    <div class="success-message">${message || 'メッセージが設定されていません'}</div>
                </div>
            </body>
            </html>
        `);
                        });
                    });
                </script>
            </div>

            <!-- アカウント設定タブ -->
            <div id="tab-account" class="tab-content" style="display: none;">
                <h2>アカウント設定</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">マイアカウントページ</th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'myaccount_page',
                                'show_option_none' => '-- 選択してください --',
                                'option_none_value' => '0',
                                'selected' => $settings['myaccount_page'],
                            ));
                            ?>
                            <p class="description">マイアカウント用ページを選択してください。この固定ページに [edel_square_myaccount] ショートコードを追加してください。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ログインページ</th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'login_redirect',
                                'show_option_none' => '-- 選択してください --',
                                'option_none_value' => '0',
                                'selected' => $settings['login_redirect'],
                            ));
                            ?>
                            <p class="description">ログインページを選択してください。この固定ページに [edel_square_login] ショートコードを追加してください。</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- reCAPTCHA設定タブ -->
            <div id="tab-recaptcha" class="tab-content" style="display: none;">
                <h2>reCAPTCHA v3設定</h2>
                <p>ログインフォームでreCAPTCHA v3を使用する場合は、以下の設定を行ってください。</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">Site Key</th>
                        <td>
                            <input type="text" name="recaptcha_site_key" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_site_key']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Secret Key</th>
                        <td>
                            <input type="text" name="recaptcha_secret_key" class="regular-text" value="<?php echo esc_attr($settings['recaptcha_secret_key']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">スコア閾値</th>
                        <td>
                            <input type="text" name="recaptcha_threshold" class="small-text" value="<?php echo esc_attr($settings['recaptcha_threshold']); ?>">
                            <p class="description">0.0から1.0の間の値を指定してください。デフォルトは0.5です。値が高いほど厳しい判定になります。</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <input type="submit" name="edel_square_save_settings" class="button button-primary" value="設定を保存">
        </p>
    </form>
</div>

<script>
    (function($) {
        // タブ切り替え
        $('.edel-square-admin-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();

            // タブの切り替え
            $('.edel-square-admin-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // コンテンツの切り替え
            $('.tab-content').hide();
            $($(this).attr('href')).show();
        });
    })(jQuery);
</script>

<style>
    .tab-content {
        padding: 20px 0;
    }
</style>