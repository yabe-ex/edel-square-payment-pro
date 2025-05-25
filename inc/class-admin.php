<?php

/**
 * 管理画面関連のクラス
 */
class EdelSquarePaymentProAdmin {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_post_edel_square_process_subscriptions_admin', array($this, 'handle_process_subscriptions_admin'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    public function register_admin_menu() {
        // メインメニュー
        add_menu_page(
            'Square決済',
            'Square決済',
            'manage_options',
            'edel-square-payment-pro',
            array($this, 'render_payments_page'),
            'dashicons-cart',
            30
        );

        // 決済一覧
        add_submenu_page(
            'edel-square-payment-pro',
            '決済一覧',
            '決済一覧',
            'manage_options',
            'edel-square-payment-pro-list',
            array($this, 'render_payments_page')
        );

        // サブスクリプション一覧
        add_submenu_page(
            'edel-square-payment-pro',
            'サブスク決済一覧',
            'サブスク決済一覧',
            'manage_options',
            'edel-square-payment-pro-subscriptions',
            array($this, 'render_subscriptions_page')
        );

        // サブスクリプション編集ページ（非表示）
        add_submenu_page(
            null, // 親メニューにnullを指定すると非表示になる
            'サブスクリプション編集',
            'サブスクリプション編集',
            'manage_options',
            'edel-square-payment-pro-edit-subscription',
            array($this, 'render_edit_subscription_page')
        );

        // サブスクリプションプラン一覧
        add_submenu_page(
            'edel-square-payment-pro',
            'サブスクプラン一覧',
            'サブスクプラン一覧',
            'manage_options',
            'edel-square-payment-pro-plans',
            array($this, 'render_plans_page')
        );

        // プラン編集ページ（非表示）
        add_submenu_page(
            null,
            'プラン編集',
            'プラン編集',
            'manage_options',
            'edel-square-payment-pro-edit-plan',
            array($this, 'render_edit_plan_page')
        );

        // プラン追加ページ（非表示）
        add_submenu_page(
            null,
            'プラン追加',
            'プラン追加',
            'manage_options',
            'edel-square-payment-pro-add-plan',
            array($this, 'render_add_plan_page')
        );

        // 設定ページ
        add_submenu_page(
            'edel-square-payment-pro',
            '設定',
            '設定',
            'manage_options',
            'edel-square-payment-pro-settings',
            array($this, 'show_settings_page')
        );
    }

    // 既存のメソッドはそのまま維持...

    public function render_edit_subscription_page() {
        // subscription_idを複数の方法で取得
        $subscription_id = '';
        if (isset($_GET['subscription_id']) && !empty($_GET['subscription_id'])) {
            $subscription_id = sanitize_text_field($_GET['subscription_id']);
        } elseif (isset($_REQUEST['subscription_id']) && !empty($_REQUEST['subscription_id'])) {
            $subscription_id = sanitize_text_field($_REQUEST['subscription_id']);
        } elseif (isset($_GET['id']) && !empty($_GET['id'])) {
            $subscription_id = sanitize_text_field($_GET['id']);
        }

        // subscription_idが空の場合はエラー
        if (empty($subscription_id)) {
            wp_die('サブスクリプションIDが指定されていません。');
        }

        // グローバル変数として設定（テンプレートで使用するため）
        global $subscription_id;

        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/edit-subscription.php';
    }

    public function render_edit_plan_page() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/edit-plan.php';
    }

    public function render_add_plan_page() {
        // 新規プラン追加ページ
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/add-plan.php';
    }

    // 他のメソッドは元のコードのまま...
    public function handle_admin_actions() {
        $this->handle_update_plan();
        $this->handle_process_subscriptions();
        $this->handle_cancel_subscription();
        $this->handle_manual_payment();
    }

    /**
     * サブスクリプションキャンセル処理
     */
    private function handle_cancel_subscription() {
        if (!isset($_POST['cancel_subscription']) || !isset($_POST['cancel_nonce'])) {
            return;
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('この操作を実行する権限がありません。');
        }

        // nonceチェック
        if (!wp_verify_nonce($_POST['cancel_nonce'], 'cancel_subscription_nonce')) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';

        if (empty($subscription_id)) {
            wp_die('サブスクリプションIDが指定されていません。');
        }

        // サブスクリプション情報を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

        if (!$subscription) {
            wp_die('サブスクリプションが見つかりません。');
        }

        // キャンセル処理
        $now = current_time('mysql');
        $update_data = array(
            'status' => 'CANCELED',
            'canceled_at' => $now,
            'updated_at' => $now
        );

        $result = EdelSquarePaymentProDB::update_subscription($subscription_id, $update_data);

        // リダイレクト
        $redirect_url = add_query_arg(
            array(
                'page' => 'edel-square-payment-pro-edit-subscription',
                'subscription_id' => $subscription_id,
                'canceled' => $result ? '1' : '0'
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * 手動決済処理
     */
    private function handle_manual_payment() {
        if (!isset($_POST['manual_payment']) || !isset($_POST['manual_payment_nonce'])) {
            return;
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('この操作を実行する権限がありません。');
        }

        // nonceチェック
        if (!wp_verify_nonce($_POST['manual_payment_nonce'], 'manual_payment_nonce')) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';

        if (empty($subscription_id)) {
            wp_die('サブスクリプションIDが指定されていません。');
        }

        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

        if (!$subscription) {
            wp_die('サブスクリプションが見つかりません。');
        }

        $payment_result = false;
        $error_message = '';

        if ($subscription->status === 'ACTIVE') {
            try {
                // Cronクラスを使用して手動決済実行
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-cron.php';
                $scheduler = EdelSquarePaymentProScheduler::get_instance();

                $reflection = new ReflectionClass($scheduler);
                $method = $reflection->getMethod('process_subscription_payment');
                $method->setAccessible(true);
                $result = $method->invoke($scheduler, $subscription);

                $payment_result = !empty($result);
                if (!$payment_result) {
                    $error_message = '決済処理に失敗しました。';
                }
            } catch (Exception $e) {
                $error_message = '決済処理エラー: ' . $e->getMessage();
            }
        } else {
            $error_message = 'アクティブでないサブスクリプションです。';
        }

        // リダイレクト
        $redirect_url = add_query_arg(
            array(
                'page' => 'edel-square-payment-pro-edit-subscription',
                'subscription_id' => $subscription_id,
                'manual_payment' => $payment_result ? '1' : '0',
                'error_message' => $error_message ? urlencode($error_message) : ''
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function handle_update_plan() {
        if (!isset($_POST['edel_square_update_plan']) || !isset($_POST['plan_id'])) {
            return;
        }

        $plan_id = sanitize_text_field($_POST['plan_id']);

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update_plan_' . $plan_id)) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        if (!current_user_can('manage_options')) {
            wp_die('この操作を実行する権限がありません。');
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'JPY';
        $billing_cycle = isset($_POST['billing_cycle']) ? sanitize_text_field($_POST['billing_cycle']) : 'MONTHLY';
        $billing_interval = isset($_POST['billing_interval']) ? intval($_POST['billing_interval']) : 1;
        $trial_period_days = isset($_POST['trial_period_days']) ? intval($_POST['trial_period_days']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'ACTIVE';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $updated = EdelSquarePaymentProDB::update_plan(array(
            'plan_id' => $plan_id,
            'name' => $name,
            'amount' => $amount,
            'currency' => $currency,
            'billing_cycle' => $billing_cycle,
            'billing_interval' => $billing_interval,
            'trial_period_days' => $trial_period_days,
            'status' => $status,
            'description' => $description,
            'updated_at' => current_time('mysql')
        ));

        $redirect_url = add_query_arg(
            array(
                'page' => 'edel-square-payment-pro-plans',
                'updated' => $updated ? 'true' : 'false'
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_form_submissions() {
        if (isset($_POST['update_subscription']) && isset($_POST['subscription_nonce'])) {
            check_admin_referer('update_subscription_nonce', 'subscription_nonce');

            $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
            $status = isset($_POST['subscription_status']) ? sanitize_text_field($_POST['subscription_status']) : '';
            $next_billing_date = isset($_POST['next_billing_date']) ? sanitize_text_field($_POST['next_billing_date']) : '';

            if (!empty($next_billing_date)) {
                $next_billing_date = date('Y-m-d H:i:s', strtotime($next_billing_date));
            }

            $update_data = array();
            if (!empty($status)) {
                $update_data['status'] = $status;
            }
            if (!empty($next_billing_date)) {
                $update_data['next_billing_date'] = $next_billing_date;
            }

            $updated = false;
            if (!empty($update_data) && !empty($subscription_id)) {
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
                $updated = EdelSquarePaymentProDB::update_subscription($subscription_id, $update_data);
            }

            $redirect_url = add_query_arg(
                array(
                    'page' => 'edel-square-payment-pro-edit-subscription',
                    'subscription_id' => $subscription_id,
                    'updated' => $updated ? '1' : '0'
                ),
                admin_url('admin.php')
            );

            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    // 残りのメソッドは元のコードのまま維持...
    public function activate_plugin() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        EdelSquarePaymentProDB::create_tables();

        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $default_settings = EdelSquarePaymentProSettings::get_default_settings();
        update_option(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'settings', $default_settings);
    }

    public function render_settings_page() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/admin-settings.php';
    }

    public function render_payments_page() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/payments.php';
    }

    public function render_subscriptions_page() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/subscriptions.php';
    }

    public function render_plans_page() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/views/subscription-plans.php';
    }

    // 他の既存メソッドも維持...
    public function handle_update_subscription() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }

        check_admin_referer('update_subscription_nonce', 'subscription_nonce');

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        $status = isset($_POST['subscription_status']) ? sanitize_text_field($_POST['subscription_status']) : '';
        $next_billing_date = isset($_POST['next_billing_date']) ? sanitize_text_field($_POST['next_billing_date']) : '';

        if (!empty($next_billing_date)) {
            $next_billing_date = date('Y-m-d H:i:s', strtotime($next_billing_date));
        }

        if (empty($subscription_id)) {
            wp_redirect(admin_url('admin.php?page=edel-square-payment-pro-subscriptions&error=1'));
            exit;
        }

        $update_data = array();
        if (!empty($status)) {
            $update_data['status'] = $status;
        }
        if (!empty($next_billing_date)) {
            $update_data['next_billing_date'] = $next_billing_date;
        }

        $updated = false;
        if (!empty($update_data)) {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
            $updated = EdelSquarePaymentProDB::update_subscription($subscription_id, $update_data);
        }

        $redirect_url = admin_url('admin.php?page=edel-square-payment-pro-edit-subscription&subscription_id=' . $subscription_id);
        if ($updated) {
            $redirect_url .= '&updated=1';
        } else {
            $redirect_url .= '&error=1';
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_process_subscriptions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['edel_square_process_subscriptions'])) {
            check_admin_referer('edel_square_process_subscriptions');

            $cron_file = EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-cron.php';
            if (file_exists($cron_file)) {
                require_once $cron_file;

                if (class_exists('EdelSquarePaymentProScheduler')) {
                    $scheduler = EdelSquarePaymentProScheduler::get_instance();
                    $scheduler->process_daily_subscription_payments();
                } else {
                    add_settings_error(
                        'edel_square_payment',
                        'scheduler_class_not_found',
                        'スケジューラークラスが見つかりませんでした。',
                        'error'
                    );
                }
            } else {
                add_settings_error(
                    'edel_square_payment',
                    'cron_file_not_found',
                    'Cronファイルが見つかりません: ' . $cron_file,
                    'error'
                );
            }

            wp_redirect(add_query_arg('processed', 'true', admin_url('admin.php?page=edel-square-payment-pro')));
            exit;
        }
    }

    public function handle_process_subscriptions_admin() {
        if (!current_user_can('manage_options')) {
            wp_die(__('この操作を実行する権限がありません。', 'edel-square-payment-pro'));
        }

        check_admin_referer('edel_square_process_subscriptions');
        error_log('決済処理の手動実行を開始します: ' . date_i18n('Y-m-d H:i:s'));

        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-cron.php';
        $scheduler = EdelSquarePaymentProScheduler::get_instance();
        $result = $scheduler->process_daily_subscription_payments();

        error_log('決済処理の手動実行が完了しました: ' . date_i18n('Y-m-d H:i:s'));

        $redirect_url = add_query_arg(array(
            'page' => 'edel-square-payment-pro',
            'tab' => 'settings',
            'processed' => 'true'
        ), admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    public function admin_enqueue($hook) {
        $version = (defined('EDEL_SQUARE_PAYMENT_PRO_DEVELOP') && true === EDEL_SQUARE_PAYMENT_PRO_DEVELOP) ? time() : EDEL_SQUARE_PAYMENT_PRO_VERSION;

        wp_register_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-admin', EDEL_SQUARE_PAYMENT_PRO_URL . '/js/admin.js', array('jquery'), $version, true);
        wp_register_style(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-admin', EDEL_SQUARE_PAYMENT_PRO_URL . '/css/admin.css', array(), $version);

        if (strpos($hook, EDEL_SQUARE_PAYMENT_PRO_SLUG) !== false) {
            wp_enqueue_style(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-admin');
            wp_enqueue_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-admin');

            $admin_params = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'admin_nonce'),
                'i18n' => array(
                    'confirmRefund' => '本当に返金処理を行いますか？この操作は元に戻せません。',
                    'loading' => '処理中...',
                    'error' => 'エラーが発生しました。',
                    'success' => '処理が完了しました。',
                ),
            );

            wp_localize_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-admin', 'edelSquareAdminParams', $admin_params);
        }
    }

    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=' . EDEL_SQUARE_PAYMENT_PRO_SLUG . '-settings') . '">設定</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // 残りのメソッドも元のコードを維持...

    public function show_settings_page() {
        if (isset($_POST['edel_square_save_settings']) && check_admin_referer('edel_square_settings_nonce')) {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';

            $settings = array();

            // API設定
            $settings['sandbox_mode'] = isset($_POST['sandbox_mode']) ? '1' : '0';
            $settings['sandbox_access_token'] = sanitize_text_field($_POST['sandbox_access_token']);
            $settings['sandbox_application_id'] = sanitize_text_field($_POST['sandbox_application_id']);
            $settings['sandbox_location_id'] = sanitize_text_field($_POST['sandbox_location_id']);
            $settings['production_access_token'] = sanitize_text_field($_POST['production_access_token']);
            $settings['production_application_id'] = sanitize_text_field($_POST['production_application_id']);
            $settings['production_location_id'] = sanitize_text_field($_POST['production_location_id']);

            // メール設定
            $settings['sender_name'] = sanitize_text_field($_POST['sender_name']);
            $settings['sender_email'] = sanitize_email($_POST['sender_email']);
            $settings['admin_email'] = sanitize_email($_POST['admin_email']);

            // 同意チェックボックス設定
            $settings['show_consent_checkbox'] = isset($_POST['show_consent_checkbox']) ? '1' : '0';
            $settings['privacy_policy_page'] = absint($_POST['privacy_policy_page']);
            $settings['terms_page'] = absint($_POST['terms_page']);
            $settings['consent_text'] = sanitize_textarea_field($_POST['consent_text']);

            // メール通知設定（管理者向け）
            $settings['admin_email_subject'] = sanitize_text_field($_POST['admin_email_subject']);
            $settings['admin_email_body'] = sanitize_textarea_field($_POST['admin_email_body']);

            // メール通知設定（購入者向け）
            $settings['customer_email_subject'] = sanitize_text_field($_POST['customer_email_subject']);
            $settings['customer_email_body'] = sanitize_textarea_field($_POST['customer_email_body']);

            // 成功時メッセージ
            $settings['success_message'] = sanitize_textarea_field($_POST['success_message']);

            // マイアカウント設定
            $settings['myaccount_page'] = absint($_POST['myaccount_page']);
            $settings['login_redirect'] = absint($_POST['login_redirect']);

            // reCAPTCHA設定
            $settings['recaptcha_site_key'] = sanitize_text_field($_POST['recaptcha_site_key']);
            $settings['recaptcha_secret_key'] = sanitize_text_field($_POST['recaptcha_secret_key']);
            $settings['recaptcha_threshold'] = sanitize_text_field($_POST['recaptcha_threshold']);

            // サブスクリプション設定
            if (isset($_POST['subscription_success_message'])) {
                $settings['subscription_success_message'] = sanitize_textarea_field($_POST['subscription_success_message']);
            }

            if (isset($_POST['subscription_admin_email_subject'])) {
                $settings['subscription_admin_email_subject'] = sanitize_text_field($_POST['subscription_admin_email_subject']);
            }

            if (isset($_POST['subscription_admin_email_body'])) {
                $settings['subscription_admin_email_body'] = sanitize_textarea_field($_POST['subscription_admin_email_body']);
            }

            if (isset($_POST['subscription_customer_email_subject'])) {
                $settings['subscription_customer_email_subject'] = sanitize_text_field($_POST['subscription_customer_email_subject']);
            }

            if (isset($_POST['subscription_customer_email_body'])) {
                $settings['subscription_customer_email_body'] = sanitize_textarea_field($_POST['subscription_customer_email_body']);
            }

            if (isset($_POST['subscription_payment_admin_email_subject'])) {
                $settings['subscription_payment_admin_email_subject'] = sanitize_text_field($_POST['subscription_payment_admin_email_subject']);
            }

            if (isset($_POST['subscription_payment_admin_email_body'])) {
                $settings['subscription_payment_admin_email_body'] = sanitize_textarea_field($_POST['subscription_payment_admin_email_body']);
            }

            if (isset($_POST['subscription_payment_customer_email_subject'])) {
                $settings['subscription_payment_customer_email_subject'] = sanitize_text_field($_POST['subscription_payment_customer_email_subject']);
            }

            if (isset($_POST['subscription_payment_customer_email_body'])) {
                $settings['subscription_payment_customer_email_body'] = sanitize_textarea_field($_POST['subscription_payment_customer_email_body']);
            }

            if (isset($_POST['subscription_cancel_admin_email_subject'])) {
                $settings['subscription_cancel_admin_email_subject'] = sanitize_text_field($_POST['subscription_cancel_admin_email_subject']);
            }

            if (isset($_POST['subscription_cancel_admin_email_body'])) {
                $settings['subscription_cancel_admin_email_body'] = sanitize_textarea_field($_POST['subscription_cancel_admin_email_body']);
            }

            if (isset($_POST['subscription_cancel_customer_email_subject'])) {
                $settings['subscription_cancel_customer_email_subject'] = sanitize_text_field($_POST['subscription_cancel_customer_email_subject']);
            }

            if (isset($_POST['subscription_cancel_customer_email_body'])) {
                $settings['subscription_cancel_customer_email_body'] = sanitize_textarea_field($_POST['subscription_cancel_customer_email_body']);
            }

            EdelSquarePaymentProSettings::update_settings($settings);

            add_settings_error(
                'edel_square_settings',
                'settings_updated',
                '設定を保存しました。',
                'updated'
            );
        }

        // 設定値を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        include EDEL_SQUARE_PAYMENT_PRO_PATH . '/templates/admin-settings.php';
    }

    /**
     * 返金処理のAJAXハンドラー
     */
    public function process_refund() {
        // セキュリティチェック
        check_ajax_referer(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません。');
        }

        // パラメータの検証
        if (empty($_POST['payment_id']) || empty($_POST['amount'])) {
            wp_send_json_error('必要なパラメータが不足しています。');
        }

        $payment_id = sanitize_text_field($_POST['payment_id']);
        $amount = intval($_POST['amount']);
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        // 返金処理
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
        $square_api = new EdelSquarePaymentProAPI();

        $result = $square_api->refund_payment($payment_id, $amount, $reason);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => '返金処理が完了しました。',
                'refund_id' => $result['refund_id'],
                'status' => $result['status'],
            ));
        } else {
            wp_send_json_error(array(
                'message' => '返金処理に失敗しました。',
                'error' => isset($result['error']) ? $result['error'] : '',
            ));
        }
    }
}
