<?php

/**
 * サブスクリプション定期実行処理を管理するクラス
 *
 * @package EdelSquarePaymentPro
 */

defined('ABSPATH') || exit;

/**
 * サブスクリプションスケジューラークラス
 */
class EdelSquarePaymentProScheduler {

    /**
     * クラスのシングルトンインスタンス
     *
     * @var EdelSquarePaymentProScheduler
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @return EdelSquarePaymentProScheduler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        // Cronイベントの登録
        add_action('init', array($this, 'register_cron_events'));

        // Cronイベントハンドラーの登録
        add_action('edel_square_daily_subscription_payment', array($this, 'process_daily_subscription_payments'));
    }

    /**
     * Cronイベントを登録
     */
    public function register_cron_events() {
        if (!wp_next_scheduled('edel_square_daily_subscription_payment')) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'edel_square_daily_subscription_payment');
        }
    }

    /**
     * 日次サブスクリプション決済処理
     */
    public function process_daily_subscription_payments() {
        global $wpdb;

        // ログ出力
        error_log('日次サブスクリプション決済処理を開始: ' . date_i18n('Y-m-d H:i:s'));

        // 本日が次回請求日のアクティブなサブスクリプションを取得
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';
        $today = date_i18n('Y-m-d');

        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                WHERE status = 'ACTIVE'
                AND DATE(next_billing_date) <= %s
                ORDER BY next_billing_date ASC",
                $today
            )
        );

        error_log('処理対象のサブスクリプション数: ' . count($subscriptions));

        // サブスクリプションごとに決済処理
        foreach ($subscriptions as $subscription) {
            try {
                $this->process_subscription_payment($subscription);
            } catch (Exception $e) {
                error_log('サブスクリプション決済エラー: ' . $e->getMessage() . ' - サブスクリプションID: ' . $subscription->subscription_id);
            }
        }

        error_log('日次サブスクリプション決済処理を完了: ' . date_i18n('Y-m-d H:i:s'));
    }

    /**
     * 個別のサブスクリプション決済処理
     */
    private function process_subscription_payment($subscription) {
        // Square APIをロード
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
        $square_api = new EdelSquarePaymentProAPI();

        // DBクラスをロード
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';

        // プラン情報の取得
        $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);
        if (!$plan) {
            throw new Exception('プラン情報が見つかりません');
        }

        // 保存されているカードで決済実行
        $payment_result = $square_api->charge_saved_card(
            $subscription->customer_id,
            $subscription->card_id,
            $subscription->amount,
            $subscription->currency,
            'サブスクリプション定期決済 - ' . $plan['name'],
            array('subscription_id' => $subscription->subscription_id)
        );

        if (!$payment_result) {
            throw new Exception('決済処理に失敗しました');
        }

        // 決済IDを取得
        $payment_id = is_object($payment_result) && method_exists($payment_result, 'getId') ?
            $payment_result->getId() : (is_array($payment_result) && isset($payment_result['id']) ? $payment_result['id'] : '');

        // 支払い情報をDBに保存
        $payment_data = array(
            'subscription_id' => $subscription->subscription_id,
            'payment_id' => $payment_id,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => 'SUCCESS',
            'payment_date' => date_i18n('Y-m-d H:i:s')
        );

        $payment_save_result = EdelSquarePaymentProDB::save_payment($payment_data);

        // 次回請求日を更新
        $this->update_next_billing_date($subscription);

        // メール通知
        $this->send_payment_notification($subscription, $payment_id);

        error_log('サブスクリプション決済成功: サブスクリプションID=' . $subscription->subscription_id . ', 支払いID=' . $payment_id);

        return $payment_id;
    }

    /**
     * 次回請求日の更新
     */
    private function update_next_billing_date($subscription) {
        global $wpdb;

        // プラン情報の取得
        $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);

        // 次回請求日の計算
        $current_period_start = date_i18n('Y-m-d H:i:s');
        $next_billing_date = new DateTime($current_period_start);

        // billing_cycleに応じて次回請求日を設定
        $interval = '+' . $plan['billing_interval'] . ' ';

        switch ($plan['billing_cycle']) {
            case 'DAILY':
                $interval .= 'day';
                break;
            case 'WEEKLY':
                $interval .= 'week';
                break;
            case 'YEARLY':
                $interval .= 'year';
                break;
            case 'MONTHLY':
            default:
                $interval .= 'month';
                break;
        }

        $next_billing_date->modify($interval);
        $current_period_end = $next_billing_date->format('Y-m-d H:i:s');

        // DBを更新
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';

        $wpdb->update(
            $table_name,
            array(
                'current_period_start' => $current_period_start,
                'current_period_end' => $current_period_end,
                'next_billing_date' => $current_period_end,
                'updated_at' => date_i18n('Y-m-d H:i:s')
            ),
            array('subscription_id' => $subscription->subscription_id),
            array('%s', '%s', '%s', '%s'),
            array('%s')
        );

        return $current_period_end;
    }

    /**
     * 決済通知メールの送信
     */
    private function send_payment_notification($subscription, $payment_id) {
        // ユーザー情報を取得
        $user = get_userdata($subscription->user_id);
        if (!$user) {
            return false;
        }

        // プラン情報
        $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);

        // 管理者向けメール
        $admin_email = get_option('admin_email');
        $admin_subject = '【' . get_bloginfo('name') . '】サブスクリプション決済完了（顧客ID: ' . $subscription->customer_id . '）';
        $admin_message = "サブスクリプションの定期決済が完了しました。\n\n";
        $admin_message .= "サブスクリプションID: " . $subscription->subscription_id . "\n";
        $admin_message .= "決済ID: " . $payment_id . "\n";
        $admin_message .= "プラン: " . $plan['name'] . "\n";
        $admin_message .= "金額: " . number_format($subscription->amount) . $subscription->currency . "\n";
        $admin_message .= "決済日時: " . date_i18n('Y-m-d H:i:s') . "\n";
        $admin_message .= "ユーザー: " . $user->display_name . "\n";
        $admin_message .= "メールアドレス: " . $user->user_email . "\n\n";
        $admin_message .= "※このメールは自動送信されています。";

        wp_mail($admin_email, $admin_subject, $admin_message);

        // ユーザー向けメール
        $user_subject = '【' . get_bloginfo('name') . '】サブスクリプション定期決済のお知らせ';
        $user_message = $user->display_name . " 様\n\n";
        $user_message .= "サブスクリプションの定期決済が完了しました。\n\n";
        $user_message .= "プラン: " . $plan['name'] . "\n";
        $user_message .= "金額: " . number_format($subscription->amount) . $subscription->currency . "\n";
        $user_message .= "決済日時: " . date_i18n('Y-m-d H:i:s') . "\n\n";
        $user_message .= "ご利用いただき、誠にありがとうございます。\n\n";
        $user_message .= "※このメールは自動送信されています。";

        wp_mail($user->user_email, $user_subject, $user_message);

        return true;
    }
}

// インスタンス作成
EdelSquarePaymentProScheduler::get_instance();
