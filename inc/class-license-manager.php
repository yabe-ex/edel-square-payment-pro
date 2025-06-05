<?php

/**
 * 製品ライセンス管理クラス
 *
 * このクラスは製品ごとに複製して使用してください。
 * クラス名と以下のプロパティのみ変更すれば、他の製品でも利用可能です。
 *
 * @package EdelSquraPaymentPro
 * @author  Your Company
 * @version 1.0.0
 * @since   1.0.0
 */
class EdelSquarePaymentProLicense {

    /* ========================================
     * 製品固有設定（製品ごとに変更が必要）
     * ======================================== */

    /**
     * プラグインスラッグ（製品ごとに変更）
     *
     * @var string
     */
    private $plugin_slug = EDEL_SQUARE_PAYMENT_PRO_SLUG;

    /**
     * オプション名のプレフィックス（製品ごとに変更）
     *
     * @var string
     */
    private $option_prefix = EDEL_SQUARE_PAYMENT_PRO_PREFIX;

    /**
     * ライセンスサーバーのデフォルトURL
     *
     * @var string
     */
    private $default_server_url = 'https://edel-wp.com';

    /**
     * 管理ページのスラッグ（製品ごとに変更）
     *
     * @var string
     */
    private $admin_page_slug = 'edel-square-payment-license';

    /* ========================================
     * 内部プロパティ（変更不要）
     * ======================================== */

    /**
     * ライセンス状況のキャッシュ
     *
     * @var bool|null
     */
    private static $cache = null;

    /**
     * キャッシュの作成時刻
     *
     * @var int|null
     */
    private static $cache_time = null;

    /**
     * 設定オプション名
     *
     * @var string
     */
    private $option_name;

    /**
     * 静的インスタンス（シングルトン用）
     *
     * @var EdelAiChatbotLicense|null
     */
    private static $instance = null;

    /**
     * コンストラクタ
     *
     * WordPressフックの登録とオプション名の設定を行います。
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->option_name = $this->option_prefix . 'settings';

        add_action('admin_init', array($this, 'handle_license_actions'));
        add_action('admin_notices', array($this, 'show_license_notices'));
        add_action('wp', array($this, 'schedule_license_check'));
        add_action($this->option_prefix . 'daily_license_check', array($this, 'check_license_status'));

        // 静的インスタンスを設定
        self::$instance = $this;
    }

    /**
     * 静的インスタンスを取得
     *
     * @since 1.0.0
     * @return EdelAiChatbotLicense|null
     */
    public static function get_instance() {
        return self::$instance;
    }

    /**
     * ライセンス設定ページの表示
     *
     * 管理画面にライセンス設定フォームを表示します。
     *
     * @since 1.0.0
     * @return void
     */
    public function license_page() {
        $settings = $this->get_license_settings();
        $license_status = $this->get_license_status();

        if (!empty($_GET['message'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>';
        }
        if (!empty($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['error'])) . '</p></div>';
        }
?>
        <div class="wrap">
            <h1>ライセンス設定</h1>

            <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
                <div style="background: #f0f0f0; padding: 10px; margin: 20px 0; border-left: 4px solid #0073aa;">
                    <h4>デバッグ情報</h4>
                    <p><strong>現在のページ:</strong> <?php echo esc_html($_GET['page'] ?? 'N/A'); ?></p>
                    <p><strong>POST データ:</strong> <?php echo !empty($_POST) ? 'あり' : 'なし'; ?></p>
                    <p><strong>設定保存状況:</strong> オプション名 = <?php echo esc_html($this->option_name); ?></p>
                    <p><strong>現在の設定:</strong></p>
                    <pre style="background: white; padding: 10px; font-size: 11px;"><?php print_r($settings); ?></pre>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . $this->admin_page_slug)); ?>">
                <?php wp_nonce_field($this->option_prefix . 'license_action', 'license_nonce'); ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="license_key">ライセンスキー</label>
                            </th>
                            <td>
                                <input type="text"
                                    id="license_key"
                                    name="license_key"
                                    value="<?php echo esc_attr($settings['license_key']); ?>"
                                    class="regular-text"
                                    placeholder="例: MYPLUG-ABC123DEF456"
                                    <?php echo $license_status['is_active'] ? 'readonly' : ''; ?>>
                                <p class="description">
                                    購入時に受け取ったライセンスキーを入力してください。
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="api_key">APIキー</label>
                            </th>
                            <td>
                                <input type="text"
                                    id="api_key"
                                    name="api_key"
                                    value="<?php echo esc_attr($settings['api_key']); ?>"
                                    class="regular-text"
                                    placeholder="例: edel_xyz789abc123">
                                <p class="description">
                                    ライセンスサーバーとの通信に必要なAPIキーです。
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="background: white; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">
                    <h3>ライセンス状況</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 150px;"><strong>ステータス:</strong></td>
                            <td>
                                <?php if ($license_status['is_active']) : ?>
                                    <span style="color: green;">有効</span>
                                <?php else : ?>
                                    <span style="color: red;">無効</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if (!empty($license_status['expires_at'])) : ?>
                            <tr>
                                <td><strong>有効期限:</strong></td>
                                <td><?php echo esc_html($license_status['expires_at']); ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($license_status['activations_remaining'])) : ?>
                            <tr>
                                <td><strong>残り有効化数:</strong></td>
                                <td><?php echo esc_html($license_status['activations_remaining']); ?>サイト</td>
                            </tr>
                        <?php endif; ?>

                        <tr>
                            <td><strong>最終確認:</strong></td>
                            <td><?php echo $license_status['last_checked'] ? wp_date('Y-m-d H:i', $license_status['last_checked']) : '未確認'; ?></td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="action" value="設定保存" class="button button-primary">

                    <?php if ($license_status['is_active']) : ?>
                        <input type="submit" name="action" value="ライセンス再確認" class="button button-secondary">
                        <input type="submit" name="action" value="ライセンス無効化" class="button"
                            onclick="return confirm('このサイトでのライセンスを無効化しますか？');">
                    <?php else : ?>
                        <input type="submit" name="action" value="ライセンス認証" class="button button-secondary">
                    <?php endif; ?>
                </p>
            </form>

            <div style="background: #fff; padding: 15px; border: 1px solid #ddd; margin-top: 20px;">
                <h3>接続テスト</h3>
                <p>ライセンスサーバーとの接続をテストします。</p>
                <button type="button" class="button" onclick="testServerConnection()">接続テスト実行</button>
                <div id="connection-test-result" style="margin-top: 10px;"></div>
            </div>

            <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
                <div style="background: #f0f0f0; padding: 10px; margin-top: 20px;">
                    <h4>ライセンス状況データ</h4>
                    <pre><?php var_dump($license_status); ?></pre>
                </div>
            <?php endif; ?>
        </div>

        <script>
            function testServerConnection() {
                const resultDiv = document.getElementById('connection-test-result');
                const licenseKey = document.getElementById('license_key').value;
                const apiKey = document.getElementById('api_key').value;

                resultDiv.innerHTML = 'テスト中...';

                if (!licenseKey || !apiKey) {
                    resultDiv.innerHTML = 'ライセンスキーとAPIキーを入力してください。';
                    return;
                }

                setTimeout(() => {
                    if (licenseKey.length > 10 && apiKey.length > 10) {
                        resultDiv.innerHTML = '設定は正常に見えます。「設定保存」→「ライセンス認証」をお試しください。';
                    } else {
                        resultDiv.innerHTML = 'ライセンスキーまたはAPIキーが短すぎる可能性があります。';
                    }
                }, 1000);
            }
        </script>
        <?php
    }

    /**
     * ライセンス関連アクションの処理
     *
     * POST送信されたライセンス関連のアクションを処理します。
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_license_actions() {
        if (!isset($_POST['license_nonce']) || !wp_verify_nonce($_POST['license_nonce'], $this->option_prefix . 'license_action')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);
        $settings = array(
            'license_key' => sanitize_text_field($_POST['license_key']),
            'server_url' => $this->default_server_url,
            'api_key' => sanitize_text_field($_POST['api_key'])
        );

        update_option($this->option_name, $settings);

        $redirect_url = admin_url('admin.php?page=' . $this->admin_page_slug);
        $message = '';
        $error = '';

        switch ($action) {
            case 'ライセンス認証':
                if (empty($settings['license_key'])) {
                    $error = 'ライセンスキーを入力してください。';
                } elseif (empty($settings['api_key'])) {
                    $error = 'APIキーを入力してください。';
                } else {
                    $result = $this->activate_license($settings);
                    if ($result['success']) {
                        $message = 'ライセンスの認証に成功しました。';
                    } else {
                        $error = $result['message'];
                    }
                }
                break;

            case 'ライセンス無効化':
                if (empty($settings['license_key']) || empty($settings['api_key'])) {
                    $error = 'ライセンス無効化にはライセンスキーとAPIキーが必要です。';
                } else {
                    $result = $this->deactivate_license($settings);
                    if ($result['success']) {
                        $message = 'ライセンスを無効化しました。';
                    } else {
                        $error = $result['message'];
                    }
                }
                break;

            case 'ライセンス再確認':
                if (empty($settings['license_key']) || empty($settings['api_key'])) {
                    $error = 'ライセンス確認にはライセンスキーとAPIキーが必要です。';
                } else {
                    $result = $this->check_license($settings);
                    if ($result['success']) {
                        $message = 'ライセンス状況を更新しました。';
                    } else {
                        $error = $result['message'];
                    }
                }
                break;

            case '設定保存':
            default:
                $message = '設定を保存しました。';
                break;
        }

        if (!empty($message)) {
            $redirect_url = add_query_arg('message', urlencode($message), $redirect_url);
        }
        if (!empty($error)) {
            $redirect_url = add_query_arg('error', urlencode($error), $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * ライセンス認証API呼び出し
     *
     * ライセンスサーバーに対してライセンスの認証リクエストを送信します。
     *
     * @since 1.0.0
     * @param array $settings ライセンス設定配列
     * @return array 処理結果 ['success' => bool, 'message' => string]
     */
    private function activate_license($settings) {
        $api_url = trailingslashit($settings['server_url']) . 'wp-json/edel_lisense_manager_v1/activate';

        $request_data = array(
            'license_key' => $settings['license_key'],
            'site_url' => home_url(),
            'product_id' => $this->plugin_slug,
            'instance_id' => $this->get_instance_id()
        );

        error_log('[License Debug] ACTIVATE - site_url: ' . $request_data['site_url']);
        error_log('[License Debug] ACTIVATE - instance_id: ' . $request_data['instance_id']);
        error_log('[License Debug] ACTIVATE - product_id: ' . $request_data['product_id']);

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $settings['api_key']
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('[License Debug] ACTIVATE - WP Error: ' . $response->get_error_message());
            return array('success' => false, 'message' => 'ライセンスサーバーとの通信に失敗しました: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('[License Debug] ACTIVATE - Response code: ' . $response_code);
        error_log('[License Debug] ACTIVATE - Response body: ' . $body);

        $data = json_decode($body, true);

        if (isset($data['activated']) && $data['activated']) {
            update_option($this->option_name . '_status', array(
                'is_active' => true,
                'expires_at' => $data['expires_at'] ?? null,
                'activations_remaining' => $data['activations_remaining'] ?? null,
                'last_checked' => time()
            ));
            error_log('[License Debug] ACTIVATE - Success');
            return array('success' => true);
        } else {
            error_log('[License Debug] ACTIVATE - Failed: ' . ($data['message'] ?? 'Unknown error'));
            return array('success' => false, 'message' => $data['message'] ?? 'ライセンス認証に失敗しました。');
        }
    }

    /**
     * ライセンス無効化API呼び出し
     *
     * ライセンスサーバーに対してライセンスの無効化リクエストを送信します。
     *
     * @since 1.0.0
     * @param array $settings ライセンス設定配列
     * @return array 処理結果 ['success' => bool, 'message' => string]
     */
    private function deactivate_license($settings) {
        $api_url = trailingslashit($settings['server_url']) . 'wp-json/edel_lisense_manager_v1/deactivate';

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $settings['api_key']
            ),
            'body' => json_encode(array(
                'license_key' => $settings['license_key'],
                'site_url' => home_url(),
                'product_id' => $this->plugin_slug,
                'instance_id' => $this->get_instance_id()
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => '通信エラー: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['deactivated']) && $data['deactivated']) {
            update_option($this->option_name . '_status', array(
                'is_active' => false,
                'last_checked' => time()
            ));
            return array('success' => true);
        } else {
            return array('success' => false, 'message' => $data['message'] ?? 'ライセンス無効化に失敗しました。');
        }
    }

    /**
     * ライセンス確認API呼び出し
     *
     * ライセンスサーバーに対してライセンスの状態確認リクエストを送信します。
     *
     * @since 1.0.0
     * @param array $settings ライセンス設定配列
     * @return array 処理結果 ['success' => bool, 'message' => string]
     */
    private function check_license($settings) {
        $api_url = trailingslashit($settings['server_url']) . 'wp-json/edel_lisense_manager_v1/check';

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $settings['api_key']
            ),
            'body' => json_encode(array(
                'license_key' => $settings['license_key'],
                'site_url' => home_url(),
                'product_id' => $this->plugin_slug,
                'instance_id' => $this->get_instance_id()
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => '通信エラー: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        update_option($this->option_name . '_status', array(
            'is_active' => $data['valid'] ?? false,
            'expires_at' => $data['expires_at'] ?? null,
            'activations_remaining' => $data['activations_remaining'] ?? null,
            'last_checked' => time()
        ));

        return array('success' => true);
    }

    /**
     * インスタンスIDを取得
     *
     * サイト固有の識別子を取得または生成します。
     *
     * @since 1.0.0
     * @return string インスタンスID
     */
    private function get_instance_id() {
        $option_name = $this->option_name . '_instance_id';
        $instance_id = get_option($option_name);

        if (!$instance_id) {
            $instance_id = wp_generate_password(32, false, false);
            update_option($option_name, $instance_id);
            error_log('[License Debug] New instance_id created: ' . $instance_id);
        }

        error_log('[License Debug] Using instance_id: ' . $instance_id . ' (from option: ' . $option_name . ')');
        return $instance_id;
    }

    /**
     * 定期的なライセンスチェックをスケジュール
     *
     * WordPress cronを使用して日次のライセンスチェックをスケジュールします。
     *
     * @since 1.0.0
     * @return void
     */
    public function schedule_license_check() {
        if (!wp_next_scheduled($this->option_prefix . 'daily_license_check')) {
            wp_schedule_event(time(), 'daily', $this->option_prefix . 'daily_license_check');
        }
    }

    /**
     * ライセンス状況の定期確認
     *
     * 定期実行されるライセンス状況のチェック処理です。
     *
     * @since 1.0.0
     * @return void
     */
    public function check_license_status() {
        $settings = $this->get_license_settings();
        if (!empty($settings['license_key'])) {
            $this->check_license($settings);
        }
    }

    /**
     * 管理画面での通知表示
     *
     * ライセンスが無効な場合に管理画面に警告を表示します。
     *
     * @since 1.0.0
     * @return void
     */
    public function show_license_notices() {
        $current_screen = get_current_screen();
        if (!$current_screen || $current_screen->parent_base !== 'options-general') {
            return;
        }

        $license_status = $this->get_license_status();

        if (!$license_status['is_active']) {
        ?>
            <div class="notice notice-warning">
                <p>
                    <strong>ライセンス警告:</strong>
                    ライセンスが認証されていません。
                    <a href="<?php echo admin_url('admin.php?page=' . $this->admin_page_slug); ?>">
                        ライセンス設定ページ
                    </a>でライセンスキーを入力してください。
                </p>
            </div>
<?php
        }
    }

    /**
     * ライセンスが有効かチェック
     *
     * プラグイン機能で使用するライセンス有効性の確認メソッドです。
     *
     * @since 1.0.0
     * @return bool ライセンスが有効かどうか
     */
    public static function is_license_valid() {
        if (self::$cache !== null && (time() - self::$cache_time) < 60) {
            return self::$cache;
        }

        $instance = self::get_instance();
        if (!$instance) {
            return self::cache_result(false);
        }

        $settings = $instance->get_license_settings();
        $status = $instance->get_license_status();

        if (empty($settings['license_key']) || empty($settings['api_key'])) {
            return self::cache_result(false);
        }

        if (!$status['is_active']) {
            return self::cache_result(false);
        }

        if (!empty($status['expires_at'])) {
            $expires_timestamp = strtotime($status['expires_at']);
            if ($expires_timestamp && $expires_timestamp < time()) {
                return self::cache_result(false);
            }
        }

        $last_check = $status['last_checked'] ?? 0;
        if ((time() - $last_check) > (0.01 * HOUR_IN_SECONDS)) {
            $instance->verify_with_server();
        }

        return self::cache_result(true);
    }

    /**
     * サーバーでのライセンス確認
     *
     * ライセンスサーバーに対して現在のライセンス状況を確認します。
     *
     * @since 1.0.0
     * @return bool ライセンスが有効かどうか
     */
    public function verify_with_server() {
        error_log('[License Debug] verify_with_server() started');

        $settings = $this->get_license_settings();

        if (empty($settings['license_key']) || empty($settings['api_key'])) {
            error_log('[License Debug] Missing license_key or api_key');
            return false;
        }

        $api_url = trailingslashit($settings['server_url']) . 'wp-json/edel_lisense_manager_v1/check';

        $request_data = array(
            'license_key' => $settings['license_key'],
            'site_url' => home_url(),
            'product_id' => $this->plugin_slug,
            'instance_id' => $this->get_instance_id()
        );

        error_log('[License Debug] VERIFY - API URL: ' . $api_url);
        error_log('[License Debug] VERIFY - license_key: ' . $request_data['license_key']);
        error_log('[License Debug] VERIFY - site_url: ' . $request_data['site_url']);
        error_log('[License Debug] VERIFY - product_id: ' . $request_data['product_id']);
        error_log('[License Debug] VERIFY - instance_id: ' . $request_data['instance_id']);

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $settings['api_key']
            ),
            'body' => json_encode($request_data),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            error_log('[License Debug] VERIFY - WP Error: ' . $response->get_error_message());
            return $this->get_license_status()['is_active'] ?? false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('[License Debug] VERIFY - Response code: ' . $response_code);
        error_log('[License Debug] VERIFY - Response body: ' . $body);

        $data = json_decode($body, true);
        error_log('[License Debug] VERIFY - Parsed data: ' . print_r($data, true));

        if (isset($data['valid'])) {
            update_option($this->option_name . '_status', array(
                'is_active' => $data['valid'],
                'expires_at' => $data['expires_at'] ?? null,
                'activations_remaining' => $data['activations_remaining'] ?? null,
                'last_checked' => time()
            ));

            self::$cache = null;
            self::$cache_time = null;

            error_log('[License Debug] VERIFY - Status updated: ' . ($data['valid'] ? 'Valid' : 'Invalid'));
            return $data['valid'];
        }

        error_log('[License Debug] VERIFY - Invalid response format');
        return false;
    }

    /**
     * ライセンス設定を取得
     *
     * データベースからライセンス設定を取得します。
     *
     * @since 1.0.0
     * @return array ライセンス設定配列
     */
    private function get_license_settings() {
        return get_option($this->option_name, array(
            'license_key' => '',
            'server_url' => $this->default_server_url,
            'api_key' => ''
        ));
    }

    /**
     * ライセンス状況を取得
     *
     * データベースからライセンス状況を取得します。
     *
     * @since 1.0.0
     * @return array ライセンス状況配列
     */
    private function get_license_status() {
        return get_option($this->option_name . '_status', array(
            'is_active' => false,
            'expires_at' => null,
            'last_checked' => 0
        ));
    }

    /**
     * 結果をキャッシュ
     *
     * ライセンス確認結果をメモリキャッシュに保存します。
     *
     * @since 1.0.0
     * @param bool $result キャッシュする結果
     * @return bool キャッシュされた結果
     */
    private static function cache_result($result) {
        self::$cache = $result;
        self::$cache_time = time();
        return $result;
    }

    /**
     * 開発・デバッグ用：ライセンス情報取得
     *
     * デバッグ目的でライセンス関連情報を取得します。
     *
     * @since 1.0.0
     * @return array|null ライセンス情報配列またはnull
     */
    public static function get_license_info() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return null;
        }

        $instance = self::get_instance();
        if (!$instance) {
            return null;
        }

        return array(
            'settings' => $instance->get_license_settings(),
            'status' => $instance->get_license_status(),
            'cache' => self::$cache,
            'cache_time' => self::$cache_time
        );
    }
}

// インスタンス化
$license_manager = new EdelSquarePaymentProLicense();
