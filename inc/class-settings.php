<?php

/**
 * 設定関連のクラス
 */
class EdelSquarePaymentProSettings {
    /**
     * デフォルト設定を取得
     */
    public static function get_default_settings() {
        return array(
            // API設定
            'sandbox_mode' => '1',
            'sandbox_access_token' => '',
            'sandbox_application_id' => '',
            'sandbox_location_id' => '',
            'production_access_token' => '',
            'production_application_id' => '',
            'production_location_id' => '',

            // メール設定
            'sender_name' => get_bloginfo('name'),
            'sender_email' => get_bloginfo('admin_email'),
            'admin_email' => get_bloginfo('admin_email'),
            'enable_onetime_payment_notification' => '1',

            // 同意チェックボックス設定
            'show_consent_checkbox' => '1',
            'privacy_policy_page' => '',
            'terms_page' => '',
            'consent_text' => 'プライバシーポリシー と 利用規約 を確認し、決済情報を入力されたメールアドレスでユーザーアカウントが作成されることに同意します。',

            // メール通知設定（管理者向け）
            'admin_email_subject' => '【{site_name}】新しい決済がありました',
            'admin_email_body' => "以下の内容で決済がありました。\n\n商品名: {item_name}\n金額: {amount}円\n購入者メール: {customer_email}\n決済ID: {payment_id}\n日時: {transaction_date}\n\n",

            // メール通知設定（購入者向け）
            'customer_email_subject' => '【{site_name}】ご購入ありがとうございます',
            'customer_email_body' => "{user_name} 様\n\nご購入ありがとうございます。以下の内容でご決済いただきました。\n\n商品名: {item_name}\n金額: {amount}円\n決済ID: {payment_id}\n日時: {transaction_date}\n\nマイアカウントページからもご確認いただけます。\n{site_url}\n\n",

            // 成功時メッセージ
            'success_message' => "ご購入ありがとうございます。決済が完了しました。\nマイアカウントページでご確認いただけます。",

            // マイアカウント設定
            'myaccount_page' => '',
            'login_redirect' => '',

            // reCAPTCHA設定
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'recaptcha_threshold' => '0.5',

            // サブスクリプション設定
            'subscription_success_message' => "サブスクリプションの登録が完了しました。\nマイアカウントページからご確認いただけます。",
            'subscription_admin_email_subject' => '【{site_name}】新しいサブスクリプション登録がありました',
            'subscription_admin_email_body' => "以下の内容でサブスクリプション登録がありました。\n\nプラン名: {item_name}\n金額: {amount}円\n課金周期: {billing_cycle}\n購入者メール: {customer_email}\nサブスクリプションID: {subscription_id}\n日時: {transaction_date}\n\n",
            'subscription_customer_email_subject' => '【{site_name}】サブスクリプション登録ありがとうございます',
            'subscription_customer_email_body' => "{user_name} 様\n\nサブスクリプションへのご登録ありがとうございます。以下の内容で登録されました。\n\nプラン名: {item_name}\n金額: {amount}円\n課金周期: {billing_cycle}\nサブスクリプションID: {subscription_id}\n日時: {transaction_date}\n\nマイアカウントページからもご確認いただけます。\n{site_url}\n\n",
            'subscription_payment_admin_email_subject' => '【{site_name}】サブスクリプション決済が完了しました',
            'subscription_payment_admin_email_body' => "以下の内容でサブスクリプション決済が完了しました。\n\nプラン名: {item_name}\n金額: {amount}円\n購入者メール: {customer_email}\nサブスクリプションID: {subscription_id}\n決済ID: {payment_id}\n日時: {transaction_date}\n\n",
            'subscription_payment_customer_email_subject' => '【{site_name}】サブスクリプション決済が完了しました',
            'subscription_payment_customer_email_body' => "{user_name} 様\n\n以下の内容でサブスクリプション決済が完了しました。\n\nプラン名: {item_name}\n金額: {amount}円\nサブスクリプションID: {subscription_id}\n決済ID: {payment_id}\n日時: {transaction_date}\n次回請求日: {next_billing_date}\n\nマイアカウントページからもご確認いただけます。\n{site_url}\n\n",
            'subscription_cancel_admin_email_subject' => '【{site_name}】サブスクリプションがキャンセルされました',
            'subscription_cancel_admin_email_body' => "以下のサブスクリプションがキャンセルされました。\n\nプラン名: {item_name}\n金額: {amount}円\n購入者メール: {customer_email}\nサブスクリプションID: {subscription_id}\nキャンセル日: {cancel_date}\nキャンセルタイプ: {cancellation_type}\n\n",
            'subscription_cancel_customer_email_subject' => '【{site_name}】サブスクリプションのキャンセルを承りました',
            'subscription_cancel_customer_email_body' => "{user_name} 様\n\n以下のサブスクリプションのキャンセルを承りました。\n\nプラン名: {item_name}\n金額: {amount}円\nサブスクリプションID: {subscription_id}\nキャンセル日: {cancel_date}\nキャンセルタイプ: {cancellation_type}\n\nご利用ありがとうございました。\n{site_url}\n\n",
        );
    }

    /**
     * 設定を取得
     */
    public static function get_settings() {
        $default_settings = self::get_default_settings();
        $saved_settings = get_option(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'settings', array());

        return wp_parse_args($saved_settings, $default_settings);
    }

    /**
     * 特定の設定値を取得
     */
    public static function get_setting($key, $default = '') {
        $settings = self::get_settings();

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        return $default;
    }

    /**
     * 設定を更新
     */
    public static function update_settings($new_settings) {
        $current_settings = self::get_settings();
        $updated_settings = wp_parse_args($new_settings, $current_settings);

        update_option(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'settings', $updated_settings);

        return $updated_settings;
    }

    /**
     * プレースホルダーを置換
     */
    public static function replace_placeholders($text, $data) {
        $placeholders = array(
            '{item_name}' => isset($data['item_name']) ? $data['item_name'] : '',
            '{amount}' => isset($data['amount']) ? number_format($data['amount']) : '',
            '{customer_email}' => isset($data['customer_email']) ? $data['customer_email'] : '',
            '{payment_id}' => isset($data['payment_id']) ? $data['payment_id'] : '',
            '{subscription_id}' => isset($data['subscription_id']) ? $data['subscription_id'] : '',
            '{customer_id}' => isset($data['customer_id']) ? $data['customer_id'] : '',
            '{transaction_date}' => isset($data['transaction_date']) ? $data['transaction_date'] : current_time('mysql', false),
            '{user_name}' => isset($data['user_name']) ? $data['user_name'] : '',
            '{user_id}' => isset($data['user_id']) ? $data['user_id'] : '',
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
            '{plan_id}' => isset($data['plan_id']) ? $data['plan_id'] : '',
            '{billing_cycle}' => isset($data['billing_cycle']) ? $data['billing_cycle'] : '',
            '{next_billing_date}' => isset($data['next_billing_date']) ? $data['next_billing_date'] : '',
            '{cancel_date}' => isset($data['cancel_date']) ? $data['cancel_date'] : '',
            '{cancellation_type}' => isset($data['cancellation_type']) ? $data['cancellation_type'] : '',
            '{trial_days}' => isset($data['trial_days']) ? $data['trial_days'] : '',
        );

        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    /**
     * 同意テキストのリンク置換
     */
    public static function process_consent_text($text) {
        $settings = self::get_settings();

        // プライバシーポリシーリンク
        $privacy_link = '#';
        if (!empty($settings['privacy_policy_page'])) {
            $privacy_link = get_permalink((int)$settings['privacy_policy_page']);
        }

        // 利用規約リンク
        $terms_link = '#';
        if (!empty($settings['terms_page'])) {
            $terms_link = get_permalink((int)$settings['terms_page']);
        }

        // リンクタグで置換
        $text = str_replace(
            array('[privacy_policy_link]', '[terms_link]'),
            array(
                '<a href="' . esc_url($privacy_link) . '" target="_blank">プライバシーポリシー</a>',
                '<a href="' . esc_url($terms_link) . '" target="_blank">利用規約</a>'
            ),
            $text
        );

        return $text;
    }
}
