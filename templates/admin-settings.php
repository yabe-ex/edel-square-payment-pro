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

                <div class="card">
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
                </div>
            </div>

            <!-- メール設定タブ -->
            <div id="tab-email" class="tab-content" style="display: none;">
                <h2>メール設定</h2>

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

                <h3>管理者向け通知メール</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">件名</th>
                        <td>
                            <input type="text" name="admin_email_subject" class="regular-text" value="<?php echo esc_attr($settings['admin_email_subject']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">本文</th>
                        <td>
                            <textarea name="admin_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['admin_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {customer_email}, {payment_id}, {transaction_date}, {user_name}, {user_id}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                </table>

                <h3>購入者向け通知メール</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">件名</th>
                        <td>
                            <input type="text" name="customer_email_subject" class="regular-text" value="<?php echo esc_attr($settings['customer_email_subject']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">本文</th>
                        <td>
                            <textarea name="customer_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['customer_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {payment_id}, {transaction_date}, {user_name}, {user_id}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                </table>
                <h3>サブスクリプションキャンセル通知メール</h3>

                <h4>管理者向けキャンセル通知</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">件名</th>
                        <td>
                            <input type="text" name="subscription_cancel_admin_email_subject" class="regular-text" value="<?php echo esc_attr($settings['subscription_cancel_admin_email_subject']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">本文</th>
                        <td>
                            <textarea name="subscription_cancel_admin_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_cancel_admin_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {customer_email}, {subscription_id}, {cancel_date}, {cancellation_type}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                </table>

                <h4>購入者向けキャンセル通知</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">件名</th>
                        <td>
                            <input type="text" name="subscription_cancel_customer_email_subject" class="regular-text" value="<?php echo esc_attr($settings['subscription_cancel_customer_email_subject']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">本文</th>
                        <td>
                            <textarea name="subscription_cancel_customer_email_body" rows="8" class="large-text"><?php echo esc_textarea($settings['subscription_cancel_customer_email_body']); ?></textarea>
                            <p class="description">利用可能なプレースホルダー: {item_name}, {amount}, {subscription_id}, {cancel_date}, {cancellation_type}, {user_name}, {site_name}, {site_url}</p>
                        </td>
                    </tr>
                </table>
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
                        </td>
                    </tr>
                </table>
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