<?php

/**
 * Plugin Name: Edel Square Payment Pro
 * Plugin URI:
 * Description: ショートコードで指定した金額のカード決済、およびサブスクリプション決済を行います。
 * Version: 1.0
 * Author: yabea
 * Author URI:
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit();

$info = get_file_data(__FILE__, array('plugin_name' => 'Plugin Name', 'version' => 'Version'));

define('EDEL_SQUARE_PAYMENT_PRO_URL', plugins_url('', __FILE__));  // http(s)://〜/wp-content/plugins/edel-square-payment-pro（URL）
define('EDEL_SQUARE_PAYMENT_PRO_PATH', dirname(__FILE__));         // /home/〜/wp-content/plugins/edel-square-payment-pro (パス)
define('EDEL_SQUARE_PAYMENT_PRO_NAME', $info['plugin_name']);
define('EDEL_SQUARE_PAYMENT_PRO_SLUG', 'edel-square-payment-pro');
define('EDEL_SQUARE_PAYMENT_PRO_PREFIX', 'edel_square_payment_pro_');
define('EDEL_SQUARE_PAYMENT_PRO_VERSION', $info['version']);
define('EDEL_SQUARE_PAYMENT_PRO_DEVELOP', true);

// Composerのオートローダー
if (file_exists(EDEL_SQUARE_PAYMENT_PRO_PATH . '/vendor/autoload.php')) {
    require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/vendor/autoload.php';
}

class EdelSquarePaymentPro {
    /**
     * インスタンス
     */
    private static $instance = null;

    /**
     * インスタンスを取得
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
        // プラグインの初期化
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * プラグインの初期化
     */
    public function init() {
        // データベースクラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';

        // 管理画面クラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-admin.php';
        $admin = new EdelSquarePaymentProAdmin();

        // 管理画面フックの追加
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'plugin_action_links'));

        // AJAX処理の登録
        add_action('wp_ajax_edel_square_process_refund', array($admin, 'process_refund'));

        // 設定クラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';

        // ショートコードクラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-shortcodes.php';
        $shortcodes = new EdelSquarePaymentProShortcodes();

        // Square APIクラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';

        // サブスクリプションクラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-subscriptions.php';
        $subscriptions = new EdelSquarePaymentProSubscriptions();

        // 定期実行処理の登録
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-cron.php';
        $scheduler = EdelSquarePaymentProScheduler::get_instance();

        // 購入時に管理者以外は管理画面にリダイレクトしないようにする
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);
    }

    /**
     * ログインリダイレクト
     */
    public function login_redirect($redirect_to, $requested_redirect_to, $user) {
        // 管理者の場合はデフォルトのリダイレクト先を使用
        if (isset($user->roles) && in_array('administrator', (array)$user->roles)) {
            return $redirect_to;
        }

        // マイアカウントページが設定されている場合はそこにリダイレクト
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        if (!empty($settings['myaccount_page'])) {
            $myaccount_url = get_permalink((int)$settings['myaccount_page']);
            if ($myaccount_url) {
                return $myaccount_url;
            }
        }

        return $redirect_to;
    }
}

function edel_square_payment_pro_activate() {
    if (!wp_next_scheduled('edel_square_daily_subscription_payment')) {
        wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'edel_square_daily_subscription_payment');
    }
}

function edel_square_payment_pro_deactivate() {
    wp_clear_scheduled_hook('edel_square_daily_subscription_payment');
}

require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
register_activation_hook(__FILE__, array('EdelSquarePaymentProDB', 'create_tables'));
register_activation_hook(__FILE__, 'edel_square_payment_pro_activate');
register_deactivation_hook(__FILE__, 'edel_square_payment_pro_deactivate');

EdelSquarePaymentPro::get_instance();

// function force_register_plugin_menu() {
//     require_once WP_PLUGIN_DIR . '/edel-square-payment-pro/inc/class-admin.php';
//     $admin = new EdelSquarePaymentProAdmin();
//     $admin->register_admin_menu();
// }
// add_action('admin_menu', 'force_register_plugin_menu', 999);
