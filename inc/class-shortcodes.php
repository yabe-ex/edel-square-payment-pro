<?php

/**
 * ショートコード関連のクラス
 */
class EdelSquarePaymentProShortcodes {
    /**
     * コンストラクタ
     */
    public function __construct() {
        // OneTime決済用ショートコード
        add_shortcode('edel_square_onetime', array($this, 'render_onetime_payment_form'));

        // マイアカウントページ用ショートコード
        add_shortcode('edel_square_myaccount', array($this, 'render_myaccount_page'));

        // ログインフォーム用ショートコード
        add_shortcode('edel_square_login', array($this, 'render_login_form'));

        // サブスクリプション用ショートコード
        add_shortcode('edel_square_subscription', array($this, 'render_subscription_form'));

        // サブスクリプション管理用ショートコード
        add_shortcode('edel_square_manage_subscription', array($this, 'render_manage_subscription'));

        // フロントエンドスクリプトとスタイルの読み込み
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // AJAXハンドラーの登録
        add_action('wp_ajax_edel_square_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_nopriv_edel_square_process_payment', array($this, 'process_payment'));

        // 買い切り決済専用のAjaxハンドラー
        add_action('wp_ajax_edel_square_process_onetime_payment_ajax', array($this, 'process_onetime_payment_ajax'));
        add_action('wp_ajax_nopriv_edel_square_process_onetime_payment_ajax', array($this, 'process_onetime_payment_ajax'));

        // ログイン処理
        add_action('wp_ajax_nopriv_edel_square_process_login', array($this, 'process_login'));

        // ログアウト処理のリダイレクト
        add_action('wp_logout', array($this, 'logout_redirect'));

        // サブスクリプション関連のAJAXハンドラー
        add_action('wp_ajax_edel_square_update_payment_method', array($this, 'update_payment_method'));

        add_shortcode('edel_square_my_subscriptions', array($this, 'display_user_subscriptions'));

        // AJAX処理の登録
        add_action('wp_ajax_edel_square_update_card', array($this, 'handle_update_card'));
    }

    /**
     * フロントエンド用のアセットを読み込み
     */
    public function enqueue_frontend_assets() {
        global $post;

        // ショートコードが存在するページでのみ読み込み
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'edel_square_onetime') ||
            has_shortcode($post->post_content, 'edel_square_myaccount') ||
            has_shortcode($post->post_content, 'edel_square_login') ||
            has_shortcode($post->post_content, 'edel_square_subscription') ||
            has_shortcode($post->post_content, 'edel_square_manage_subscription')
        )) {
            // Square Web Payments SDK
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
            $square_api = new EdelSquarePaymentProAPI();

            $version = (defined('EDEL_SQUARE_PAYMENT_PRO_DEVELOP') && true === EDEL_SQUARE_PAYMENT_PRO_DEVELOP) ? time() : EDEL_SQUARE_PAYMENT_PRO_VERSION;
            $strategy = array('in_footer' => true, 'strategy' => 'defer');

            wp_enqueue_style(
                EDEL_SQUARE_PAYMENT_PRO_SLUG . '-frontend',
                EDEL_SQUARE_PAYMENT_PRO_URL . '/css/front.css',
                array(),
                $version
            );

            wp_enqueue_script(
                EDEL_SQUARE_PAYMENT_PRO_SLUG . '-frontend',
                EDEL_SQUARE_PAYMENT_PRO_URL . '/js/front.js',
                array('jquery'),
                $version,
                $strategy
            );

            // 共通パラメータの定義
            wp_localize_script(
                EDEL_SQUARE_PAYMENT_PRO_SLUG . '-frontend',
                'edelSquarePaymentParams',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'nonce'),
                    'appId' => $square_api->get_application_id(),
                    'locationId' => $square_api->get_location_id(),
                )
            );

            // 決済フォームが存在する場合の追加スクリプト
            if (
                has_shortcode($post->post_content, 'edel_square_onetime') ||
                has_shortcode($post->post_content, 'edel_square_subscription')
            ) {
                wp_enqueue_script(
                    'square-web-payments-sdk',
                    'https://sandbox.web.squarecdn.com/v1/square.js',
                    array(),
                    null,
                    false
                );
            }

            // ログインフォームが存在する場合
            if (has_shortcode($post->post_content, 'edel_square_login')) {
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
                $settings = EdelSquarePaymentProSettings::get_settings();

                // ログイン用パラメータ
                wp_localize_script(
                    EDEL_SQUARE_PAYMENT_PRO_SLUG . '-frontend',
                    'edelSquareLoginParams',
                    array(
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'login_nonce'),
                    )
                );

                // reCAPTCHA v3
                if (!empty($settings['recaptcha_site_key'])) {
                    wp_enqueue_script(
                        'recaptcha-v3',
                        'https://www.google.com/recaptcha/api.js?render=' . esc_attr($settings['recaptcha_site_key']),
                        array(),
                        null,
                        true
                    );

                    wp_localize_script(
                        EDEL_SQUARE_PAYMENT_PRO_SLUG . '-frontend',
                        'edelSquareRecaptchaParams',
                        array(
                            'siteKey' => $settings['recaptcha_site_key'],
                        )
                    );
                }
            }

            // サブスクリプション管理ページが存在する場合
            if (has_shortcode($post->post_content, 'edel_square_manage_subscription')) {
                wp_localize_script(
                    EDEL_SQUARE_PAYMENT_PRO_SLUG . '-frontend',
                    'edelSquareSubscriptionParams',
                    array(
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscription_nonce'),
                    )
                );
            }
        }
    }

    /**
     * OneTime決済フォームのレンダリング
     */
    public function render_onetime_payment_form($atts) {
        $atts = shortcode_atts(array(
            'amount' => 0,
            'item_name' => 'One-time Payment',
            'button_text' => '支払う',
        ), $atts, 'edel_square_onetime');

        // 金額が指定されていない場合はエラーメッセージ
        if (empty($atts['amount']) || !is_numeric($atts['amount'])) {
            return '<p class="edel-square-error">金額が正しく指定されていません。</p>';
        }

        // 設定の読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();
        // var_dump($settings);
        // APIクラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
        $square_api = new EdelSquarePaymentProAPI();

        // API接続チェック
        if (empty($settings['sandbox_application_id']) || empty($settings['sandbox_location_id']) || empty($settings['sandbox_access_token'])) {
            if (current_user_can('manage_options')) {
                return '<p class="edel-square-error">Square APIの設定が完了していません。管理画面から設定を行ってください。</p>';
            } else {
                return '<p class="edel-square-error">決済システムが現在利用できません。</p>';
            }
        }

        // 同意文言の処理
        $consent_text = '';
        if (!empty($settings['show_consent_checkbox']) && $settings['show_consent_checkbox'] === '1') {
            $consent_text = EdelSquarePaymentProSettings::process_consent_text($settings['consent_text']);
        }

        // ログイン中のユーザー情報を取得
        $user_email = '';
        $is_logged_in = is_user_logged_in();
        if ($is_logged_in) {
            $current_user = wp_get_current_user();
            $user_email = $current_user->user_email;
        }

        // フォームの出力
        ob_start();
?>
        <div class="edel-square-payment-form-container">
            <div class="edel-square-payment-form">
                <h3><?php echo esc_html($atts['item_name']); ?></h3>
                <p class="edel-square-amount">金額: <?php echo number_format((int)$atts['amount']); ?>円</p>

                <div class="edel-square-form-group">
                    <label for="edel-square-email">メールアドレス <span class="required">（必須）</span></label>
                    <?php if ($is_logged_in): ?>
                        <input type="email" id="edel-square-email" name="email" value="<?php echo esc_attr($user_email); ?>" readonly class="readonly-field">
                        <input type="hidden" id="edel-square-email-hidden" name="email_hidden" value="<?php echo esc_attr($user_email); ?>">
                    <?php else: ?>
                        <input type="email" id="edel-square-email" name="email" required>
                    <?php endif; ?>
                </div>

                <div class="edel-square-form-group">
                    <label for="card-container">クレジットカード情報</label>
                    <div id="card-container"></div> <!-- この要素に直接アタッチ -->
                </div>

                <?php if (!empty($consent_text)): ?>
                    <div class="edel-square-form-group">
                        <label class="edel-square-checkbox-label">
                            <input type="checkbox" id="edel-square-consent" name="consent" required>
                            <?php echo $consent_text; ?>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="edel-square-form-group">
                    <div id="edel-square-payment-status" class="edel-square-payment-status"></div>
                    <button type="button" id="edel-square-submit" class="edel-square-submit-button" data-amount="<?php echo esc_attr($atts['amount']); ?>" data-item-name="<?php echo esc_attr($atts['item_name']); ?>"><?php echo esc_html($atts['button_text']); ?></button>
                </div>
            </div>
            <div id="edel-square-success-message" class="edel-square-success-message" style="display: none;"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    // ユーザーのサブスクリプション一覧ページに表示するコード例
    public function display_user_subscriptions($atts) {
        // ユーザーが未ログインの場合はログインフォームを表示
        if (!is_user_logged_in()) {
            return '<p>サブスクリプション情報を表示するにはログインしてください。</p>' . wp_login_form(array('echo' => false));
        }

        $current_user_id = get_current_user_id();

        // ユーザーのサブスクリプション情報を取得
        $subscriptions = EdelSquarePaymentProDB::get_user_subscriptions($current_user_id);

        if (empty($subscriptions)) {
            return '<p>有効なサブスクリプションはありません。</p>';
        }

        ob_start(); // 出力バッファリング開始
    ?>
        <div class="edel-square-subscriptions">
            <?php foreach ($subscriptions as $subscription) :
                $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);
                $plan_name = $plan ? $plan['name'] : '不明なプラン';

                // メタデータがあれば取得
                $metadata = json_decode($subscription->metadata, true);
                $card_info = '';
                if (is_array($metadata) && !empty($metadata['card_brand']) && !empty($metadata['last_4'])) {
                    $card_info = $metadata['card_brand'] . ' **** **** **** ' . $metadata['last_4'];
                    if (!empty($metadata['exp_month']) && !empty($metadata['exp_year'])) {
                        $card_info .= ' (' . $metadata['exp_month'] . '/' . $metadata['exp_year'] . ')';
                    }
                }
            ?>
                <div class="subscription-item">
                    <h3><?php echo esc_html($plan_name); ?></h3>
                    <table class="subscription-details">
                        <tr>
                            <th>金額:</th>
                            <td><?php echo esc_html($subscription->amount) . ' ' . esc_html($subscription->currency); ?></td>
                        </tr>
                        <tr>
                            <th>ステータス:</th>
                            <td><?php
                                switch ($subscription->status) {
                                    case 'ACTIVE':
                                        echo '<span class="status-active">有効</span>';
                                        break;
                                    case 'PAUSED':
                                        echo '<span class="status-paused">一時停止</span>';
                                        break;
                                    case 'CANCELED':
                                        echo '<span class="status-canceled">キャンセル済み</span>';
                                        break;
                                    default:
                                        echo esc_html($subscription->status);
                                }
                                ?></td>
                        </tr>
                        <tr>
                            <th>次回請求日:</th>
                            <td><?php echo esc_html(date_i18n('Y年m月d日', strtotime($subscription->next_billing_date))); ?></td>
                        </tr>
                        <?php if (!empty($card_info)) : ?>
                            <tr>
                                <th>支払い方法:</th>
                                <td><?php echo esc_html($card_info); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <?php
                    // カード情報不足または一時停止状態の場合、カード更新フォームを表示
                    if (
                        $atts['show_card_form'] === 'yes' &&
                        ($subscription->status === 'PAUSED' || empty($subscription->card_id))
                    ) :
                    ?>
                        <div class="card-update-section">
                            <h4>カード情報の更新</h4>
                            <p>サブスクリプションを再開するには、カード情報を更新してください。</p>

                            <form method="post" id="card-update-form-<?php echo esc_attr($subscription->subscription_id); ?>" class="card-update-form">
                                <div id="card-container-<?php echo esc_attr($subscription->subscription_id); ?>" class="square-card-container"></div>
                                <div id="card-errors-<?php echo esc_attr($subscription->subscription_id); ?>" class="card-errors" role="alert"></div>
                                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->subscription_id); ?>">
                                <input type="hidden" name="action" value="edel_square_update_card">
                                <input type="hidden" name="payment_token" id="payment-token-<?php echo esc_attr($subscription->subscription_id); ?>" value="">
                                <?php wp_nonce_field('edel_square_update_card_nonce', 'card_update_nonce'); ?>
                                <button type="submit" class="button update-card-button" data-subscription-id="<?php echo esc_attr($subscription->subscription_id); ?>">カード情報を更新する</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($subscription->status === 'ACTIVE') : ?>
                        <div class="subscription-actions">
                            <form method="post" class="cancel-subscription-form">
                                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->subscription_id); ?>">
                                <input type="hidden" name="action" value="edel_square_cancel_subscription">
                                <?php wp_nonce_field('edel_square_cancel_subscription_nonce', 'cancel_subscription_nonce'); ?>
                                <button type="submit" class="button cancel-subscription-button" onclick="return confirm('このサブスクリプションをキャンセルしてもよろしいですか？');">サブスクリプションをキャンセルする</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            .edel-square-subscriptions {
                margin: 20px 0;
            }

            .subscription-item {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .subscription-details {
                width: 100%;
                margin-bottom: 15px;
            }

            .subscription-details th {
                text-align: left;
                padding: 5px 10px 5px 0;
                width: 30%;
            }

            .status-active {
                color: green;
                font-weight: bold;
            }

            .status-paused {
                color: orange;
                font-weight: bold;
            }

            .status-canceled {
                color: red;
            }

            .card-update-section {
                background: #f9f9f9;
                padding: 15px;
                margin-top: 15px;
                border-radius: 5px;
            }

            .square-card-container {
                height: 100px;
                margin-bottom: 15px;
            }

            .card-errors {
                color: red;
                margin-bottom: 10px;
            }

            .subscription-actions {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
        </style>
    <?php
        return ob_get_clean(); // バッファの内容を返して終了
    }

    /**
     * 買い切り決済専用のAjaxハンドラー
     */
    public function process_onetime_payment_ajax() {
        try {
            // nonceの検証
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'nonce')) {
                wp_send_json_error('セキュリティチェックに失敗しました。');
                return;
            }

            // 必須パラメータの検証
            $payment_token = isset($_POST['payment_token']) ? sanitize_text_field($_POST['payment_token']) : '';
            if (empty($payment_token)) {
                wp_send_json_error('決済情報が不足しています。');
                return;
            }

            // 金額の検証
            $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
            if ($amount <= 0) {
                wp_send_json_error('無効な金額です。');
                return;
            }

            // メールアドレスの検証
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            if (empty($email) || !is_email($email)) {
                wp_send_json_error('有効なメールアドレスを入力してください。');
                return;
            }

            // 商品名の取得
            $item_name = isset($_POST['item_name']) ? sanitize_text_field($_POST['item_name']) : '商品';

            // ユーザー情報の取得
            $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
            $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

            // ユーザー登録処理
            $user_result = $this->process_user_registration($email, $first_name, $last_name);
            $user_id = $user_result['user_id'];
            $is_new_user = $user_result['is_new_user'];
            $is_logged_in = $user_result['is_logged_in'];
            $password = $user_result['password'];

            // ユーザーIDが取得できない場合
            if ($user_id <= 0) {
                wp_send_json_error('ユーザー登録・確認処理に失敗しました。');
                return;
            }

            // デバッグ情報
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('買い切り決済処理開始: ' . date_i18n('Y-m-d H:i:s'));
                error_log('決済パラメータ: トークン=' . substr($payment_token, 0, 10) . '..., 金額=' . $amount . ', メール=' . $email);
                error_log('ユーザー情報: ID=' . $user_id . ', 新規=' . ($is_new_user ? 'はい' : 'いいえ') . ', ログイン=' . ($is_logged_in ? 'はい' : 'いいえ'));
            }

            // Square APIインスタンスの取得
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
            $square_api = new EdelSquarePaymentProAPI();

            // 参照ID生成
            $reference_id = 'order_' . uniqid();

            // 専用メソッドで決済処理を実行
            $payment_result = $square_api->process_single_payment(
                $payment_token,
                $amount,
                'JPY',
                $email,
                $first_name,
                $last_name,
                $item_name,
                array('reference_id' => $reference_id)
            );

            // 決済結果の処理
            if ($payment_result) {
                // 決済成功
                $payment_id = $payment_result->getId();
                $customer_id = $payment_result->getCustomerId() ?? '';

                // データベースに保存
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
                $payment_data = array(
                    'payment_id' => $payment_id,
                    'user_id' => $user_id,
                    'customer_id' => $customer_id,
                    'amount' => $amount,
                    'currency' => 'JPY',
                    'item_name' => $item_name,
                    'status' => 'COMPLETED',
                    'metadata' => json_encode(array(
                        'email' => $email,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'reference_id' => $reference_id,
                    )),
                    'created_at' => current_time('mysql')
                );

                // データベースに保存
                $save_result = EdelSquarePaymentProDB::save_payment($payment_data);

                if ($save_result === false) {
                    error_log('Square Payment Pro - 決済データ保存エラー');
                }

                // 決済成功後の処理
                $redirect_url = $this->handle_payment_success($payment_data, $user_id, $is_new_user, $is_logged_in, $password);

                // 成功レスポンス
                wp_send_json_success(array(
                    'message' => '決済が完了しました。',
                    'payment_id' => $payment_id,
                    'redirect_url' => $redirect_url
                ));
                return;
            } else {
                // 決済失敗
                wp_send_json_error('決済処理に失敗しました。カード情報をご確認ください。');
            }
        } catch (Exception $e) {
            // 例外発生
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('買い切り決済処理例外: ' . $e->getMessage());
            }
            wp_send_json_error('決済処理中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * カード情報更新処理
     */
    public function handle_update_card() {
        check_ajax_referer('edel_square_update_card_nonce', 'card_update_nonce');

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';
        $payment_token = isset($_POST['payment_token']) ? sanitize_text_field($_POST['payment_token']) : '';

        if (empty($subscription_id) || empty($payment_token)) {
            wp_send_json_error('必要なパラメータが不足しています。');
            return;
        }

        // ユーザー確認
        $current_user_id = get_current_user_id();
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

        if (empty($subscription) || $subscription->user_id != $current_user_id) {
            wp_send_json_error('サブスクリプションが見つからないか、アクセス権限がありません。');
            return;
        }

        try {
            // Square APIの初期化
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
            $square_api = new EdelSquarePaymentProAPI();

            // カード情報の登録または更新
            $user = get_userdata($current_user_id);
            $card_result = $square_api->create_card(
                $subscription->customer_id,
                $payment_token,
                ($user->first_name ?? '') . ' ' . ($user->last_name ?? '')
            );

            // カードIDの取得
            $card_id = '';
            if (is_object($card_result) && method_exists($card_result, 'getId')) {
                $card_id = $card_result->getId();
                $card_brand = method_exists($card_result, 'getCardBrand') ? $card_result->getCardBrand() : '';
                $last_4 = method_exists($card_result, 'getLast4') ? $card_result->getLast4() : '';
                $exp_month = method_exists($card_result, 'getExpMonth') ? $card_result->getExpMonth() : '';
                $exp_year = method_exists($card_result, 'getExpYear') ? $card_result->getExpYear() : '';
            } elseif (is_array($card_result)) {
                $card_id = isset($card_result['id']) ? $card_result['id'] : '';
                $card_brand = isset($card_result['card_brand']) ? $card_result['card_brand'] : '';
                $last_4 = isset($card_result['last_4']) ? $card_result['last_4'] : '';
                $exp_month = isset($card_result['exp_month']) ? $card_result['exp_month'] : '';
                $exp_year = isset($card_result['exp_year']) ? $card_result['exp_year'] : '';
            } elseif (is_string($card_result)) {
                $card_id = $card_result;
            }

            if (empty($card_id)) {
                wp_send_json_error('カード情報の登録に失敗しました。');
                return;
            }

            // メタデータを更新
            $metadata = json_decode($subscription->metadata, true);
            if (!is_array($metadata)) {
                $metadata = array();
            }

            $metadata['card_brand'] = $card_brand ?? '';
            $metadata['last_4'] = $last_4 ?? '';
            $metadata['exp_month'] = $exp_month ?? '';
            $metadata['exp_year'] = $exp_year ?? '';

            // カード情報とステータスを更新
            EdelSquarePaymentProDB::update_subscription($subscription_id, array(
                'card_id' => $card_id,
                'status' => 'ACTIVE',
                'metadata' => json_encode($metadata),
                'updated_at' => current_time('mysql')
            ));

            wp_send_json_success(array(
                'message' => 'カード情報が更新され、サブスクリプションが再開されました。',
                'card_info' => $card_brand . ' **** **** **** ' . $last_4
            ));
        } catch (Exception $e) {
            wp_send_json_error('エラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 買い切り決済処理
     *
     * フロントエンドからのAJAXリクエストを受け取り、決済処理を行う
     */
    public function process_payment() {
        try {
            // nonceの検証
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'nonce')) {
                wp_send_json_error('セキュリティチェックに失敗しました。');
                return;
            }

            // 必須パラメータの検証
            $payment_token = isset($_POST['payment_token']) ? sanitize_text_field($_POST['payment_token']) : '';
            if (empty($payment_token)) {
                wp_send_json_error('決済情報が不足しています。');
                return;
            }

            // 金額の検証
            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
            if ($amount <= 0) {
                wp_send_json_error('無効な金額です。');
                return;
            }

            // 商品情報の取得
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '商品';

            // カード所有者名の取得
            $cardholder_name = isset($_POST['cardholder_name']) ? sanitize_text_field($_POST['cardholder_name']) : '';

            // 顧客情報の取得
            $customer_id = '';
            $user_id = get_current_user_id();

            if ($user_id > 0) {
                // ログインユーザーの場合
                $user = get_userdata($user_id);
                $email = $user->user_email;

                // Square APIインスタンスの取得
                $square_api = new EdelSquarePaymentProAPI();

                // 顧客情報の取得または作成
                $customer = $square_api->get_or_create_customer($email, $user->first_name, $user->last_name, array('user_id' => $user_id));

                if ($customer) {
                    $customer_id = is_object($customer) && method_exists($customer, 'getId') ?
                        $customer->getId() : $customer;
                }
            } else {
                // 非ログインユーザーの場合
                $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
                $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
                $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    // Square APIインスタンスの取得
                    $square_api = new EdelSquarePaymentProAPI();

                    // 顧客情報の取得または作成
                    $customer = $square_api->get_or_create_customer($email, $first_name, $last_name);

                    if ($customer) {
                        $customer_id = is_object($customer) && method_exists($customer, 'getId') ?
                            $customer->getId() : $customer;
                    }
                }
            }

            // 注文参照IDの生成
            $reference_id = 'order_' . uniqid();

            // Square APIインスタンスの取得（まだ取得していない場合）
            if (!isset($square_api)) {
                $square_api = new EdelSquarePaymentProAPI();
            }

            // 注文メモの作成
            $note = $product_name . ' - ' . date_i18n('Y-m-d H:i:s');

            // デバッグ情報
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('決済処理開始: ' . date_i18n('Y-m-d H:i:s'));
                error_log('決済パラメータ: トークン=' . substr($payment_token, 0, 10) . '..., 金額=' . $amount . ', 顧客ID=' . $customer_id);
            }

            // 決済処理の実行
            $payment_result = $square_api->process_onetime_payment(
                $payment_token,
                $customer_id,
                $amount,
                'JPY',
                $note,
                array(
                    'reference_id' => $reference_id,
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'user_id' => $user_id
                )
            );

            // 決済結果の処理
            if ($payment_result) {
                // 決済成功
                $payment_id = is_object($payment_result) && method_exists($payment_result, 'getId') ?
                    $payment_result->getId() : (is_array($payment_result) && isset($payment_result['id']) ? $payment_result['id'] : '');

                // 注文データの作成
                $order_data = array(
                    'user_id' => $user_id,
                    'customer_id' => $customer_id,
                    'payment_id' => $payment_id,
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'amount' => $amount,
                    'currency' => 'JPY',
                    'reference_id' => $reference_id,
                    'status' => 'completed',
                    'payment_date' => date_i18n('Y-m-d H:i:s'),
                    'metadata' => array(
                        'email' => $email,
                        'cardholder_name' => $cardholder_name
                    )
                );

                // 注文データをDBに保存
                $order_id = $this->save_order($order_data);

                if ($order_id) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('注文保存成功: 注文ID=' . $order_id);
                    }

                    // リダイレクト先の取得
                    $redirect_url = '';
                    $options = get_option('edel_square_payment_settings', array());
                    if (isset($options['thankyou_page']) && !empty($options['thankyou_page'])) {
                        $redirect_url = get_permalink($options['thankyou_page']);

                        // クエリパラメータの追加
                        $redirect_url = add_query_arg(array(
                            'order_id' => $order_id,
                            'payment_id' => $payment_id
                        ), $redirect_url);
                    }

                    // メール通知
                    $this->send_order_notification($order_data);

                    // 成功レスポンス
                    wp_send_json_success(array(
                        'message' => '決済が完了しました。',
                        'order_id' => $order_id,
                        'payment_id' => $payment_id,
                        'redirect_url' => $redirect_url
                    ));
                } else {
                    // 注文保存失敗（決済自体は成功）
                    wp_send_json_error('注文情報の保存に失敗しました。');
                }
            } else {
                // 決済失敗
                wp_send_json_error('決済処理に失敗しました。カード情報をご確認ください。');
            }
        } catch (Exception $e) {
            // 例外発生
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('決済処理例外: ' . $e->getMessage());
            }
            wp_send_json_error('決済処理中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 注文データを保存
     *
     * @param array $order_data 注文データ
     * @return int|false 成功時は注文ID、失敗時はfalse
     */
    private function save_order($order_data) {
        global $wpdb;

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_main';

        // 必須項目のチェック
        if (empty($order_data['payment_id']) || empty($order_data['amount'])) {
            return false;
        }

        // メタデータの処理
        $metadata = isset($order_data['metadata']) ? $order_data['metadata'] : array();
        $metadata_json = !empty($metadata) ? json_encode($metadata) : '';

        // 挿入データの準備
        $insert_data = array(
            'user_id' => isset($order_data['user_id']) ? $order_data['user_id'] : 0,
            'customer_id' => isset($order_data['customer_id']) ? $order_data['customer_id'] : '',
            'payment_id' => $order_data['payment_id'],
            'product_id' => isset($order_data['product_id']) ? $order_data['product_id'] : 0,
            'product_name' => isset($order_data['product_name']) ? $order_data['product_name'] : '',
            'amount' => $order_data['amount'],
            'currency' => isset($order_data['currency']) ? $order_data['currency'] : 'JPY',
            'reference_id' => isset($order_data['reference_id']) ? $order_data['reference_id'] : '',
            'status' => isset($order_data['status']) ? $order_data['status'] : 'completed',
            'payment_date' => isset($order_data['payment_date']) ? $order_data['payment_date'] : date_i18n('Y-m-d H:i:s'),
            'metadata' => $metadata_json,
            'created_at' => date_i18n('Y-m-d H:i:s')
        );

        // データ型指定
        $format = array(
            '%d', // user_id
            '%s', // customer_id
            '%s', // payment_id
            '%d', // product_id
            '%s', // product_name
            '%d', // amount
            '%s', // currency
            '%s', // reference_id
            '%s', // status
            '%s', // payment_date
            '%s', // metadata
            '%s'  // created_at
        );

        // DBに挿入
        $result = $wpdb->insert($table_name, $insert_data, $format);

        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('注文保存エラー: ' . $wpdb->last_error);
            }
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 注文完了メール通知
     *
     * @param array $order_data 注文データ
     * @return bool 成功時はtrue、失敗時はfalse
     */
    private function send_order_notification($order_data) {
        // メール設定の取得
        $options = get_option('edel_square_payment_settings', array());
        $admin_email = isset($options['admin_email']) ? $options['admin_email'] : get_option('admin_email');

        // ユーザーメールアドレスの取得
        $user_email = '';
        if (!empty($order_data['user_id'])) {
            $user = get_userdata($order_data['user_id']);
            if ($user) {
                $user_email = $user->user_email;
            }
        } elseif (isset($order_data['metadata']) && is_array($order_data['metadata']) && isset($order_data['metadata']['email'])) {
            $user_email = $order_data['metadata']['email'];
        }

        // メールアドレスが空の場合
        if (empty($user_email)) {
            return false;
        }

        // 管理者向けメール
        $admin_subject = '【' . get_bloginfo('name') . '】新規注文が完了しました（注文ID: ' . $order_data['payment_id'] . '）';
        $admin_message = "新しい注文が完了しました。\n\n";
        $admin_message .= "注文ID: " . $order_data['payment_id'] . "\n";
        $admin_message .= "商品名: " . $order_data['product_name'] . "\n";
        $admin_message .= "金額: " . number_format($order_data['amount']) . $order_data['currency'] . "\n";
        $admin_message .= "決済日時: " . $order_data['payment_date'] . "\n";
        $admin_message .= "購入者: " . (isset($order_data['metadata']['cardholder_name']) ? $order_data['metadata']['cardholder_name'] : '') . "\n";
        $admin_message .= "メールアドレス: " . $user_email . "\n\n";
        $admin_message .= "※このメールは自動送信されています。";

        $admin_headers = array('Content-Type: text/plain; charset=UTF-8');

        // 購入者向けメール
        $user_subject = '【' . get_bloginfo('name') . '】ご注文ありがとうございます';
        $user_message = "この度はご注文いただき、誠にありがとうございます。\n\n";
        $user_message .= "以下の内容でご注文を承りました。\n\n";
        $user_message .= "注文ID: " . $order_data['payment_id'] . "\n";
        $user_message .= "商品名: " . $order_data['product_name'] . "\n";
        $user_message .= "金額: " . number_format($order_data['amount']) . $order_data['currency'] . "\n";
        $user_message .= "決済日時: " . $order_data['payment_date'] . "\n\n";

        if (isset($options['thankyou_message']) && !empty($options['thankyou_message'])) {
            $user_message .= $options['thankyou_message'] . "\n\n";
        }

        $user_message .= "※このメールは自動送信されています。";

        $user_headers = array('Content-Type: text/plain; charset=UTF-8');

        // メール送信
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_message, $admin_headers);
        $user_sent = wp_mail($user_email, $user_subject, $user_message, $user_headers);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('管理者向けメール送信結果: ' . ($admin_sent ? '成功' : '失敗'));
            error_log('購入者向けメール送信結果: ' . ($user_sent ? '成功' : '失敗'));
        }

        return $admin_sent && $user_sent;
    }

    /**
     * マイアカウントページのレンダリング
     */
    /**
     * マイアカウントページの表示
     *
     * @param array $atts ショートコード属性
     * @return string HTML出力
     */
    public function render_myaccount_page($atts) {
        // ログインしていない場合はログインページにリダイレクト
        if (!is_user_logged_in()) {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();

            // ログインページが設定されていれば、そこにリダイレクト
            if (!empty($settings['login_redirect'])) {
                $login_url = get_permalink((int)$settings['login_redirect']);
                if ($login_url) {
                    wp_redirect($login_url);
                    exit;
                }
            }

            // 設定がなければデフォルトのログインフォームを表示
            return $this->render_login_form($atts);
        }

        // 現在のユーザー情報
        $user = wp_get_current_user();

        // 決済履歴を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $payments = EdelSquarePaymentProDB::get_user_payments($user->ID);

        // サブスクリプション情報を取得
        $subscriptions = EdelSquarePaymentProDB::get_user_subscriptions($user->ID);

        ob_start();
    ?>
        <div class="edel-square-myaccount">
            <h2>マイアカウント</h2>

            <?php
            // ここにメッセージ表示機能を追加
            if (isset($_GET['result']) && $_GET['result'] === 'cancel_success') {
                echo '<div class="edel-square-message success">次回更新時にサブスクリプションがキャンセルされます。</div>';
            } elseif (isset($_GET['error'])) {
                $error_message = '';
                switch ($_GET['error']) {
                    case 'not_logged_in':
                        $error_message = 'ログインが必要です。';
                        break;
                    case 'no_subscription_id':
                        $error_message = 'サブスクリプションIDが指定されていません。';
                        break;
                    case 'subscription_not_found':
                        $error_message = 'サブスクリプションが見つかりません。';
                        break;
                    case 'permission_denied':
                        $error_message = 'このサブスクリプションをキャンセルする権限がありません。';
                        break;
                    default:
                        $error_message = 'エラーが発生しました。';
                }
                echo '<div class="edel-square-message error">' . esc_html($error_message) . '</div>';
            }
            ?>

            <div class="edel-square-user-info">
                <p>ユーザー名: <?php echo esc_html($user->display_name); ?></p>
                <p>メールアドレス: <?php echo esc_html($user->user_email); ?></p>
            </div>

            <!-- サブスクリプション情報 -->
            <h3>サブスクリプション</h3>

            <?php if (empty($subscriptions)): ?>
                <p>アクティブなサブスクリプションはありません。</p>
            <?php else: ?>
                <div class="edel-square-subscriptions">
                    <?php foreach ($subscriptions as $subscription):
                        // サブスクリプションが配列かオブジェクトかを確認
                        $subscription_data = is_array($subscription) ? $subscription : (array)$subscription;

                        // プラン情報を取得
                        $plan_id = isset($subscription_data['plan_id']) ? $subscription_data['plan_id'] : '';
                        $plan = $plan_id ? EdelSquarePaymentProDB::get_plan($plan_id) : null;

                        if (!$plan) {
                            error_log('プランが見つかりません: ' . $plan_id);
                        }

                        $plan_name = $plan ? $plan['name'] : '不明なプラン(ID: ' . $plan_id . ')';

                        // メタデータの解析
                        $metadata = isset($subscription_data['metadata']) ? $subscription_data['metadata'] : '';
                        if (is_string($metadata)) {
                            $metadata = json_decode($metadata, true);
                        }

                        $card_info = '';
                        if (is_array($metadata) && !empty($metadata['card_brand']) && !empty($metadata['last_4'])) {
                            $card_info = $metadata['card_brand'] . ' **** **** **** ' . $metadata['last_4'];
                            if (!empty($metadata['exp_month']) && !empty($metadata['exp_year'])) {
                                $card_info .= ' (' . $metadata['exp_month'] . '/' . $metadata['exp_year'] . ')';
                            }
                        }

                        // ステータスに応じたクラスとラベル
                        $status = isset($subscription_data['status']) ? $subscription_data['status'] : '';
                        $status_class = '';
                        $status_label = '';

                        switch ($status) {
                            case 'ACTIVE':
                                $status_class = 'status-active';
                                $status_label = '有効';
                                break;
                            case 'PAUSED':
                                $status_class = 'status-paused';
                                $status_label = '一時停止';
                                break;
                            case 'CANCELED':
                                $status_class = 'status-canceled';
                                $status_label = 'キャンセル済み';
                                break;
                            default:
                                $status_label = $status;
                        }

                        // 金額と通貨
                        $amount = isset($subscription_data['amount']) ? $subscription_data['amount'] : 0;
                        $currency = isset($subscription_data['currency']) ? $subscription_data['currency'] : 'JPY';

                        // 通貨ごとの表示方法を適用
                        if ($currency === 'JPY') {
                            // JPYはそのまま表示
                            $currency = "円";
                            $formatted_amount = number_format($amount) . $currency;
                        } else {
                            // USD, EUR などは小数点以下2桁で表示
                            $formatted_amount = number_format($amount / 100, 2) . $currency;
                        }

                        // 次回請求日
                        $next_billing_date = isset($subscription_data['next_billing_date']) ? $subscription_data['next_billing_date'] : '';
                        $formatted_date = $next_billing_date ? date_i18n('Y年m月d日', strtotime($next_billing_date)) : '';

                        // サブスクリプションID
                        $subscription_id = isset($subscription_data['subscription_id']) ? $subscription_data['subscription_id'] : '';

                        // デバッグ情報
                        if (WP_DEBUG) {
                            error_log('サブスクリプションデータ: ' . print_r($subscription_data, true));
                        }
                    ?>
                        <div class="subscription-item">
                            <h4><?php echo esc_html($plan_name); ?></h4>
                            <table class="subscription-details">
                                <tr>
                                    <th>金額:</th>
                                    <td><?php echo esc_html($formatted_amount); ?></td>
                                </tr>
                                <tr>
                                    <th>ステータス:</th>
                                    <td><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                                </tr>
                                <?php if ($formatted_date): ?>
                                    <tr>
                                        <th>次回請求日:</th>
                                        <td><?php echo esc_html($formatted_date); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($card_info)): ?>
                                    <tr>
                                        <th>支払い方法:</th>
                                        <td><?php echo esc_html($card_info); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>

                            <?php
                            // カード情報不足または一時停止状態の場合、警告メッセージを表示
                            $card_id = isset($subscription_data['card_id']) ? $subscription_data['card_id'] : '';
                            if ($status === 'PAUSED' || empty($card_id)):
                            ?>
                                <div class="subscription-warning">
                                    <p><strong>注意:</strong> このサブスクリプションは一時停止されています。カード情報の更新が必要です。</p>
                                    <p>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=edel-square-payment-pro-update-card&subscription_id=' . $subscription_id)); ?>" class="button update-card-button">カード情報を更新する</a>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php
                            // アクティブなサブスクリプションの場合、キャンセルボタンを表示
                            if ($status === 'ACTIVE'):
                            ?>
                                <div class="subscription-actions">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cancel-subscription-form" onsubmit="return confirm('このサブスクリプションをキャンセルしてもよろしいですか？この操作は取り消せません。');">
                                        <input type="hidden" name="action" value="edel_square_cancel_subscription">
                                        <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription_id); ?>">
                                        <?php
                                        $nonce = wp_create_nonce('cancel_subscription_' . $subscription_id);
                                        echo '<input type="hidden" name="cancel_nonce" value="' . esc_attr($nonce) . '">';
                                        ?>
                                        <button type="submit" class="button cancel-button">サブスクリプションをキャンセルする</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3>決済履歴</h3>

            <?php if (empty($payments)): ?>
                <p>決済履歴はありません。</p>
            <?php else: ?>
                <table class="edel-square-payment-history">
                    <thead>
                        <tr>
                            <th>商品名</th>
                            <th>金額</th>
                            <th>ステータス</th>
                            <th>日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo esc_html($payment['item_name']); ?></td>
                                <td><?php echo number_format($payment['amount']); ?>円</td>
                                <td><?php echo esc_html($this->get_status_label($payment['status'])); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p class="edel-square-logout">
                <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="edel-square-logout-link">ログアウト</a>
            </p>
        </div>

        <style>

        </style>
    <?php
        return ob_get_clean();
    }

    /**
     * 決済ステータスのラベルを取得
     *
     * @param string $status ステータスコード
     * @return string ステータスラベル
     */
    private function get_status_label($status) {
        switch ($status) {
            case 'SUCCESS':
            case 'COMPLETED':
                return '成功';
            case 'PENDING':
                return '処理中';
            case 'FAILED':
                return '失敗';
            case 'CANCELED':
                return 'キャンセル';
            case 'REFUNDED':
                return '返金済み';
            case 'PARTIALLY_REFUNDED':
                return '一部返金済み';
            default:
                return $status; // 不明なステータスはそのまま返す
        }
    }

    /**
     * サブスクリプションキャンセル処理
     */
    public function handle_cancel_subscription() {
        // nonceの検証
        if (!isset($_POST['cancel_nonce']) || !isset($_POST['subscription_id'])) {
            wp_die('不正なリクエストです。');
        }

        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        if (!wp_verify_nonce($_POST['cancel_nonce'], 'cancel_subscription_' . $subscription_id)) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        // 現在のユーザー
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_die('ログインが必要です。');
        }

        // サブスクリプション情報を取得
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);
        if (!$subscription || $subscription->user_id != $user_id) {
            wp_die('指定されたサブスクリプションが見つからないか、アクセス権がありません。');
        }

        // サブスクリプションをキャンセル
        $now = current_time('mysql');
        $result = EdelSquarePaymentProDB::update_subscription($subscription_id, array(
            'status' => 'CANCELED',
            'cancel_at' => $now,
            'updated_at' => $now
        ));

        // サブスクリプションキャンセルのメール通知
        if ($result) {
            // メール送信処理を追加（オプション）
            $this->send_subscription_cancel_email($subscription_id);
        }

        // リダイレクト（マイアカウントページに戻る）
        $redirect_url = remove_query_arg(array('subscription_id', 'action', 'cancel_nonce'));
        wp_redirect(add_query_arg('canceled', '1', $redirect_url));
        exit;
    }

    /**
     * サブスクリプションキャンセル通知メールを送信
     *
     * @param string $subscription_id サブスクリプションID
     * @return bool 送信成功時はtrue、失敗時はfalse
     */
    private function send_subscription_cancel_email($subscription_id) {
        // サブスクリプション情報を取得
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);
        if (empty($subscription)) {
            error_log('Edel Square Payment Pro: メール送信失敗 - サブスクリプションが見つかりません: ' . $subscription_id);
            return false;
        }

        // ユーザー情報を取得
        $user = get_userdata($subscription->user_id);
        if (!$user) {
            error_log('Edel Square Payment Pro: メール送信失敗 - ユーザーが見つかりません: ' . $subscription->user_id);
            return false;
        }

        // プラン情報を取得
        $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);
        $plan_name = $plan ? $plan['name'] : 'サブスクリプションプラン';

        // メールの件名と本文を作成
        $subject = get_bloginfo('name') . ' - サブスクリプションがキャンセルされました';

        $message = sprintf(
            "こんにちは、%s様\n\n" .
                "あなたの %s のサブスクリプションがキャンセルされました。\n\n" .
                "サブスクリプション情報:\n" .
                "- プラン: %s\n" .
                "- 金額: %s %s\n" .
                "- サブスクリプションID: %s\n\n" .
                "キャンセル日時: %s\n\n" .
                "ご利用いただきありがとうございました。また機会がございましたらご利用ください。\n\n" .
                "ご不明な点がございましたら、お気軽にお問い合わせください。\n\n" .
                "よろしくお願いいたします。\n" .
                "%s",
            $user->display_name,
            get_bloginfo('name'),
            $plan_name,
            number_format($subscription->amount),
            $subscription->currency,
            $subscription_id,
            date_i18n('Y年m月d日 H時i分', strtotime($subscription->cancel_at)),
            get_bloginfo('name')
        );

        // メールヘッダー
        $headers = array(
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Content-Type: text/plain; charset=UTF-8'
        );

        // メール送信
        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if ($sent) {
            error_log('Edel Square Payment Pro: サブスクリプションキャンセル通知メールを送信しました - サブスクリプションID: ' . $subscription_id);
        } else {
            error_log('Edel Square Payment Pro: サブスクリプションキャンセル通知メールの送信に失敗しました - サブスクリプションID: ' . $subscription_id);
        }

        return $sent;
    }

    /**
     * ログインフォームのレンダリング
     */
    public function render_login_form($atts) {
        // すでにログイン済みの場合はマイアカウントページにリダイレクト
        if (is_user_logged_in()) {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();

            if (!empty($settings['myaccount_page'])) {
                $myaccount_url = get_permalink((int)$settings['myaccount_page']);
                if ($myaccount_url) {
                    wp_redirect($myaccount_url);
                    exit;
                }
            }
        }

        // reCAPTCHAの設定を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();
        $use_recaptcha = !empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key']);

        ob_start();
    ?>
        <div class="edel-square-login-form">
            <h2>ログイン</h2>

            <div id="edel-square-login-message" class="edel-square-message"></div>

            <form id="edel-square-login-form" method="post">
                <div class="edel-square-form-group">
                    <label for="edel-square-login-email">メールアドレス</label>
                    <input type="email" id="edel-square-login-email" name="email" required>
                </div>

                <div class="edel-square-form-group">
                    <label for="edel-square-login-password">パスワード</label>
                    <input type="password" id="edel-square-login-password" name="password" required>
                </div>

                <?php if ($use_recaptcha): ?>
                    <input type="hidden" id="edel-square-recaptcha-token" name="recaptcha_token">
                <?php endif; ?>

                <div class="edel-square-form-group">
                    <button type="submit" id="edel-square-login-button" class="edel-square-submit-button">ログイン</button>
                </div>

                <div class="edel-square-form-links">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">パスワードをお忘れですか？</a>
                </div>
            </form>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * ログイン処理のAJAXハンドラー
     */
    public function process_login() {
        // デバッグ情報
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ログイン処理開始: ' . json_encode($_POST));
        }

        try {
            // セキュリティチェック
            check_ajax_referer(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'login_nonce', 'nonce');

            // パラメータの検証
            if (empty($_POST['email']) || empty($_POST['password'])) {
                wp_send_json(array(
                    'success' => false,
                    'message' => 'メールアドレスとパスワードは必須です。'
                ));
                return;
            }

            $email = sanitize_email($_POST['email']);
            $password = $_POST['password'];

            // reCAPTCHA検証（オプション）
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();

            if (!empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])) {
                if (empty($_POST['recaptcha_token'])) {
                    error_log('Square Payment Pro - reCAPTCHAトークンが指定されていません');
                } else {
                    $recaptcha_token = sanitize_text_field($_POST['recaptcha_token']);
                    $recaptcha_result = $this->verify_recaptcha($recaptcha_token);

                    // reCAPTCHAの結果を詳細にログ出力
                    error_log('Square Payment Pro - reCAPTCHA検証結果: ' . json_encode($recaptcha_result));

                    // スコアを特に詳しくログ出力
                    if (isset($recaptcha_result['score'])) {
                        error_log('Square Payment Pro - reCAPTCHAスコア: ' . $recaptcha_result['score'] . ' (0.0-1.0の範囲、高いほど人間の可能性が高い)');

                        // アクション（何のためのチェックか）も記録
                        if (isset($recaptcha_result['action'])) {
                            error_log('Square Payment Pro - reCAPTCHAアクション: ' . $recaptcha_result['action']);
                        }

                        // しきい値との比較（設定があれば）
                        $threshold = !empty($settings['recaptcha_threshold']) ? (float)$settings['recaptcha_threshold'] : 0.5;
                        error_log('Square Payment Pro - reCAPTCHAしきい値: ' . $threshold);
                        error_log('Square Payment Pro - reCAPTCHA判定: ' . ($recaptcha_result['score'] >= $threshold ? '合格' : '不合格'));
                    }

                    // ホスト名の確認（サイト名の検証）
                    if (isset($recaptcha_result['hostname'])) {
                        error_log('Square Payment Pro - reCAPTCHAホスト名: ' . $recaptcha_result['hostname']);
                    }

                    // エラーコードの確認
                    if (isset($recaptcha_result['error-codes']) && is_array($recaptcha_result['error-codes'])) {
                        error_log('Square Payment Pro - reCAPTCHAエラーコード: ' . implode(', ', $recaptcha_result['error-codes']));
                    }
                }
            } else {
                error_log('Square Payment Pro - reCAPTCHA設定が有効になっていません');
            }

            // メールアドレスからユーザーを取得
            $user = get_user_by('email', $email);

            if (!$user) {
                wp_send_json(array(
                    'success' => false,
                    'message' => 'メールアドレスまたはパスワードが正しくありません。'
                ));
                return;
            }

            // パスワード検証
            $check = wp_check_password($password, $user->user_pass, $user->ID);

            if (!$check) {
                wp_send_json(array(
                    'success' => false,
                    'message' => 'メールアドレスまたはパスワードが正しくありません。'
                ));
                return;
            }

            // ログイン処理
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);

            // リダイレクト先URL
            $redirect_url = home_url();
            if (!empty($settings['myaccount_page'])) {
                $myaccount_url = get_permalink((int)$settings['myaccount_page']);
                if ($myaccount_url) {
                    $redirect_url = $myaccount_url;
                }
            }

            // 成功レスポンスを返す
            wp_send_json(array(
                'success' => true,
                'message' => 'ログインに成功しました。',
                'redirect_url' => $redirect_url
            ));
        } catch (Exception $e) {
            // 例外発生時
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ログイン処理例外: ' . $e->getMessage());
            }

            wp_send_json(array(
                'success' => false,
                'message' => 'ログイン処理中にエラーが発生しました。'
            ));
        }
    }


    /**
     * サブスクリプションフォームのレンダリング
     */
    public function render_subscription_form($atts) {

        // ここから追加するデバッグコード
        ob_start();
        echo '<div style="background-color: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
        echo '<h3>Square API機能テスト</h3>';

        // APIクラスの読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
        $square_api = new EdelSquarePaymentProAPI();

        echo '<p><strong>API接続ステータス:</strong> ';
        if ($square_api->is_connected()) {
            echo '<span style="color: green;">接続成功 ✓</span>';
        } else {
            echo '<span style="color: red;">接続失敗 ✗</span>';
        }
        echo '</p>';

        echo '<p><strong>使用モード:</strong> ';
        echo $square_api->is_sandbox_mode() ? 'サンドボックス' : '本番';
        echo '</p>';

        echo '<p><strong>アプリケーションID:</strong> ';
        $app_id = $square_api->get_application_id();
        echo !empty($app_id) ? substr($app_id, 0, 5) . '...' . substr($app_id, -5) : '未設定';
        echo '</p>';

        echo '<p><strong>ロケーションID:</strong> ';
        $location_id = $square_api->get_location_id();
        echo !empty($location_id) ? substr($location_id, 0, 5) . '...' . substr($location_id, -5) : '未設定';
        echo '</p>';

        // API機能チェック
        $api_client = null;
        $api_available = true;

        try {
            // APIクライアントの取得（プライベートメソッド/プロパティへのアクセス方法）
            $reflection = new ReflectionClass($square_api);
            $property = $reflection->getProperty('api_client');
            $property->setAccessible(true);
            $api_client = $property->getValue($square_api);

            echo '<p><strong>APIクライアント:</strong> <span style="color: green;">利用可能 ✓</span></p>';
        } catch (Exception $e) {
            echo '<p><strong>APIクライアント:</strong> <span style="color: red;">エラー ✗</span> ' . $e->getMessage() . '</p>';
            $api_available = false;
        }

        if ($api_available && $api_client) {
            // 顧客API
            try {
                $customers_api = $api_client->getCustomersApi();
                echo '<p><strong>顧客API:</strong> <span style="color: green;">利用可能 ✓</span></p>';
            } catch (Exception $e) {
                echo '<p><strong>顧客API:</strong> <span style="color: red;">エラー ✗</span> ' . $e->getMessage() . '</p>';
            }

            // カードAPI
            try {
                $cards_api = $api_client->getCardsApi();
                echo '<p><strong>カードAPI:</strong> <span style="color: green;">利用可能 ✓</span></p>';
            } catch (Exception $e) {
                echo '<p><strong>カードAPI:</strong> <span style="color: red;">エラー ✗</span> ' . $e->getMessage() . '</p>';
            }

            // 決済API
            try {
                $payments_api = $api_client->getPaymentsApi();
                echo '<p><strong>決済API:</strong> <span style="color: green;">利用可能 ✓</span></p>';
            } catch (Exception $e) {
                echo '<p><strong>決済API:</strong> <span style="color: red;">エラー ✗</span> ' . $e->getMessage() . '</p>';
            }
        }

        // AJAX処理テスト用のボタン
        echo '<div style="margin-top: 15px;">';
        echo '<button id="test-subscription-ajax" style="background-color: #1e4b7a; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">サブスクリプションAPI呼び出しテスト</button>';
        echo '<div id="test-subscription-result" style="margin-top: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; display: none;"></div>';
        echo '</div>';

        // テスト用の簡易スクリプト
        echo '<script>
        jQuery(document).ready(function($) {
            $("#test-subscription-ajax").on("click", function() {
                $("#test-subscription-result").html("テスト中...").show();

                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    type: "POST",
                    data: {
                        action: "edel_square_test_subscription",
                        nonce: "' . wp_create_nonce(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'nonce') . '"
                    },
                    success: function(response) {
                        $("#test-subscription-result").html("<strong>成功:</strong> " + JSON.stringify(response)).css("color", "green");
                    },
                    error: function(xhr, status, error) {
                        $("#test-subscription-result").html("<strong>エラー:</strong> " + error).css("color", "red");
                    }
                });
            });
        });
    </script>';

        echo '</div>';
        $debug_output = ob_get_clean();
        // var_dump($debug_output);

        $atts = shortcode_atts(array(
            'plan_id' => '',
            'amount' => 0,
            'item_name' => 'サブスクリプションプラン',
            'description' => '',
            'billing_cycle' => 'monthly',
            'billing_interval' => 1,
            'trial_days' => 0,
            'button_text' => '登録する',
        ), $atts, 'edel_square_subscription');

        // プラン情報の取得とフォーム出力実装
        // 詳細な実装は省略

        // フォームの出力サンプル
        ob_start();
    ?>
        <div class="edel-square-subscription-form-container">
            <div class="edel-square-subscription-form">
                <h3><?php echo esc_html($atts['item_name']); ?></h3>

                <?php if (!empty($atts['description'])): ?>
                    <p class="edel-square-description"><?php echo nl2br(esc_html($atts['description'])); ?></p>
                <?php endif; ?>

                <p class="edel-square-amount">金額: <?php echo number_format((int)$atts['amount']); ?>円
                    <?php
                    // 請求周期の表示
                    $cycle_text = '';
                    switch ($atts['billing_cycle']) {
                        case 'daily':
                            $cycle_text = $atts['billing_interval'] > 1 ? $atts['billing_interval'] . '日ごと' : '毎日';
                            break;
                        case 'weekly':
                            $cycle_text = $atts['billing_interval'] > 1 ? $atts['billing_interval'] . '週間ごと' : '毎週';
                            break;
                        case 'yearly':
                            $cycle_text = $atts['billing_interval'] > 1 ? $atts['billing_interval'] . '年ごと' : '毎年';
                            break;
                        case 'monthly':
                        default:
                            $cycle_text = $atts['billing_interval'] > 1 ? $atts['billing_interval'] . 'ヶ月ごと' : '毎月';
                            break;
                    }
                    echo '（' . $cycle_text . '）';
                    ?>
                </p>

                <?php if (!empty($atts['trial_days']) && $atts['trial_days'] > 0): ?>
                    <p class="edel-square-trial"><?php echo esc_html($atts['trial_days']); ?>日間の無料トライアルがあります。</p>
                <?php endif; ?>

                <div class="edel-square-form-group">
                    <label for="edel-square-sub-email">メールアドレス <span class="required">（必須）</span></label>
                    <input type="email" id="edel-square-sub-email" name="email" required>
                </div>

                <div class="edel-square-form-group">
                    <label for="card-container">クレジットカード情報</label>
                    <div id="card-container"></div>
                </div>

                <div class="edel-square-form-group">
                    <div id="edel-square-subscription-status" class="edel-square-payment-status"></div>
                    <button type="button" id="edel-square-subscription-submit" class="edel-square-submit-button"
                        data-plan-id="<?php echo esc_attr($atts['plan_id']); ?>"
                        data-amount="<?php echo esc_attr($atts['amount']); ?>"
                        data-item-name="<?php echo esc_attr($atts['item_name']); ?>"
                        data-billing-cycle="<?php echo esc_attr($atts['billing_cycle']); ?>"
                        data-billing-interval="<?php echo esc_attr($atts['billing_interval']); ?>"
                        data-trial-days="<?php echo esc_attr($atts['trial_days']); ?>">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>
            </div>
            <div id="edel-square-subscription-success" class="edel-square-success-message" style="display: none;"></div>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * 決済完了後の処理
     *
     * @param array $payment_data 決済データ
     * @param int $user_id ユーザーID
     * @param bool $is_new_user 新規ユーザーかどうか
     * @param bool $is_logged_in ログイン済みかどうか
     * @param string $password 新規ユーザーの初期パスワード
     * @return string リダイレクトURL
     */
    private function handle_payment_success($payment_data, $user_id, $is_new_user = false, $is_logged_in = false, $password = '') {
        // 設定を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        // 新規ユーザーの場合はログイン情報をメールで通知
        if ($is_new_user && $user_id > 0) {
            $this->send_new_user_notification($user_id, $password);
        }

        // リダイレクト先の決定
        $redirect_url = home_url('/');

        // 新規ユーザーか、既存ユーザーでログイン済みの場合はマイアカウントページへ
        if ($is_new_user || $is_logged_in) {
            if (!empty($settings['myaccount_page'])) {
                $redirect_url = get_permalink($settings['myaccount_page']);
            }
        }
        // 既存ユーザーでログインしていない場合はログインページへ
        else {
            if (!empty($settings['login_redirect'])) {
                $redirect_url = get_permalink($settings['login_redirect']);
            }
        }

        // フィルターを追加して変更可能に
        return apply_filters('edel_square_payment_success_redirect', $redirect_url, $payment_data, $user_id, $is_new_user, $is_logged_in);
    }

    /**
     * 新規ユーザーにログイン情報をメールで通知
     *
     * @param int $user_id ユーザーID
     * @param string $password ユーザーの初期パスワード（指定がない場合は取得を試みる）
     * @return bool メール送信結果
     */
    public static function send_new_user_notification($user_id, $password = '') {
        // ユーザー情報を取得
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // パスワードが指定されていない場合、transientから取得を試みる
        if (empty($password)) {
            $password = get_transient('edel_square_initial_password_' . $user_id);

            // transientからも取得できない場合、新しいパスワードを生成してユーザーを更新
            if (empty($password)) {
                $password = wp_generate_password(12, true, false);
                wp_set_password($password, $user_id);

                // 一時的にtransientに保存（1時間有効）
                set_transient('edel_square_initial_password_' . $user_id, $password, HOUR_IN_SECONDS);
            }
        }

        // 設定を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        // サイト情報
        $site_name = get_bloginfo('name');
        $site_url = home_url('/');

        // ログインページのURL
        $login_url = home_url('/');
        if (!empty($settings['login_redirect'])) {
            $login_url = get_permalink($settings['login_redirect']);
        }

        // メールの件名
        $subject = sprintf(__('[%s] アカウント登録完了のお知らせ', 'edel-square-payment-pro'), $site_name);

        // メールの本文
        $message = sprintf(
            __('
%s 様

%s へのアカウント登録が完了しました。

以下の情報でログインできます。
メールアドレス: %s
パスワード: %s

セキュリティのため、初回ログイン後にパスワードの変更をお勧めします。

ログインページ: %s

このメールにお心当たりがない場合は、お手数ですが管理者までご連絡ください。

------------------------------
%s
%s
', 'edel-square-payment-pro'),
            $user->display_name,
            $site_name,
            $user->user_email,
            $password,
            $login_url,
            $site_name,
            $site_url
        );

        // メールヘッダー
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );

        // メール送信
        $mail_sent = wp_mail($user->user_email, $subject, $message, $headers);

        // ログ記録
        if ($mail_sent) {
            error_log('Square Payment Pro - 新規ユーザーメール送信成功: ' . $user->user_email);
        } else {
            error_log('Square Payment Pro - 新規ユーザーメール送信失敗: ' . $user->user_email);
        }

        return $mail_sent;
    }

    /**
     * 支払い方法の更新
     */
    public function update_payment_method() {
        // 支払い方法更新処理実装（省略）
        wp_send_json_success('支払い方法が更新されました。');
    }

    /**
     * サブスクリプション処理
     */
    public function process_subscription() {
        // ログ出力
        file_put_contents(
            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
            "サブスクリプション処理開始: " . date_i18n('Y-m-d H:i:s') . "\n" .
                "POST データ: " . json_encode($_POST) . "\n",
            FILE_APPEND
        );

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        // デバッグ情報
        error_log('サブスクリプション処理 - POST email: ' . $email);

        // セキュリティチェック
        check_ajax_referer(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'nonce', 'nonce');

        $response = array(
            'success' => false,
            'message' => '処理に失敗しました。'
        );

        try {
            // パラメータ検証
            if (
                empty($_POST['payment_token']) || empty($_POST['email']) ||
                (empty($_POST['plan_id']) && empty($_POST['amount']))
            ) {
                throw new Exception('必要なパラメータが不足しています。');
            }

            $payment_token = sanitize_text_field($_POST['payment_token']);
            $email = sanitize_email($_POST['email']);
            $plan_id = isset($_POST['plan_id']) ? sanitize_text_field($_POST['plan_id']) : '';
            $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
            $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

            // ユーザー登録/ログイン処理
            $user_result = $this->process_user_registration($email, $first_name, $last_name);

            // user_resultの内容を詳細にログ出力
            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "process_user_registration結果: " . json_encode($user_result) . "\n",
                FILE_APPEND
            );

            $user_id = isset($user_result['user_id']) ? $user_result['user_id'] : 0;
            $is_new_user = isset($user_result['is_new_user']) ? $user_result['is_new_user'] : false;
            $is_logged_in = isset($user_result['is_logged_in']) ? $user_result['is_logged_in'] : false;
            $password = isset($user_result['password']) ? $user_result['password'] : '';

            if (!$user_id) {
                throw new Exception('ユーザー登録に失敗しました。');
            }

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "ユーザーID: " . $user_id . "\n" .
                    "新規ユーザー: " . ($is_new_user ? 'はい' : 'いいえ') . "\n" .
                    "ログイン状態: " . ($is_logged_in ? 'ログイン中' : '未ログイン') . "\n" .
                    "パスワード: " . (!empty($password) ? '設定あり' : '設定なし') . "\n",
                FILE_APPEND
            );

            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';
            $square_api = new EdelSquarePaymentProAPI();

            // プラン情報の取得または作成
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';

            if (!empty($plan_id)) {
                // 既存プランを取得
                $plan = EdelSquarePaymentProDB::get_plan($plan_id);

                if (!$plan) {
                    throw new Exception('指定されたプランが存在しません。');
                }
            } else {
                // カスタムプラン情報の作成
                $amount = intval($_POST['amount']);
                $item_name = isset($_POST['item_name']) ? sanitize_text_field($_POST['item_name']) : 'カスタムプラン';
                $billing_cycle = isset($_POST['billing_cycle']) ? strtoupper(sanitize_text_field($_POST['billing_cycle'])) : 'MONTHLY';
                $billing_interval = isset($_POST['billing_interval']) ? intval($_POST['billing_interval']) : 1;
                $trial_days = isset($_POST['trial_days']) ? intval($_POST['trial_days']) : 0;

                // 一時的なプランIDを生成
                $plan_id = 'plan_' . uniqid();

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "プラン作成: {$plan_id}, 金額: {$amount}, 名前: {$item_name}\n",
                    FILE_APPEND
                );

                // プランをDBに保存
                $plan_result = EdelSquarePaymentProDB::save_plan(array(
                    'plan_id' => $plan_id,
                    'name' => $item_name,
                    'amount' => $amount,
                    'billing_cycle' => $billing_cycle,
                    'billing_interval' => $billing_interval,
                    'trial_period_days' => $trial_days,
                    'status' => 'ACTIVE'
                ));

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "プラン保存結果: " . json_encode($plan_result) . "\n",
                    FILE_APPEND
                );

                // 保存したプランを取得
                $plan = EdelSquarePaymentProDB::get_plan($plan_id);

                if (!$plan) {
                    throw new Exception('プラン情報の作成に失敗しました。');
                }
            }

            // 顧客情報の取得または作成
            $user = get_userdata($user_id);
            $customer_result = $square_api->get_or_create_customer(
                $user_id,
                $email,
                $user->last_name . " " . $user->first_name ?? ''
            );

            // 顧客IDの取得
            $customer_id = '';
            if (is_object($customer_result) && method_exists($customer_result, 'getId')) {
                $customer_id = $customer_result->getId();
            } elseif (is_array($customer_result) && isset($customer_result['id'])) {
                $customer_id = $customer_result['id'];
            } elseif (is_string($customer_result)) {
                $customer_id = $customer_result;
            } else {
                throw new Exception('顧客情報の取得に失敗しました。');
            }

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "顧客ID: {$customer_id}\n",
                FILE_APPEND
            );

            // トライアル期間の確認
            $has_trial = !empty($plan['trial_period_days']) && $plan['trial_period_days'] > 0;

            // カード情報の変数を初期化
            $card_data = [
                'card_id' => '',
                'card_brand' => '',
                'last_4' => '',
                'exp_month' => '',
                'exp_year' => '',
                'cardholder_name' => '',
            ];

            $payment_id = '';

            // カード情報の保存
            try {
                $card_result = $square_api->create_card(
                    $customer_id,
                    $payment_token,
                    ($user->first_name ?? '') . ' ' . ($user->last_name ?? '')
                );

                if (is_object($card_result) && method_exists($card_result, 'getId')) {
                    // カードオブジェクトからデータを取得
                    $card_data['card_id'] = $card_result->getId();
                    $card_data['card_brand'] = method_exists($card_result, 'getCardBrand') ? $card_result->getCardBrand() : '';
                    $card_data['last_4'] = method_exists($card_result, 'getLast4') ? $card_result->getLast4() : '';
                    $card_data['exp_month'] = method_exists($card_result, 'getExpMonth') ? $card_result->getExpMonth() : '';
                    $card_data['exp_year'] = method_exists($card_result, 'getExpYear') ? $card_result->getExpYear() : '';
                    $card_data['cardholder_name'] = method_exists($card_result, 'getCardholderName') ? $card_result->getCardholderName() : '';
                } elseif (is_array($card_result)) {
                    // 配列の場合
                    $card_data['card_id'] = isset($card_result['id']) ? $card_result['id'] : '';
                    $card_data['card_brand'] = isset($card_result['card_brand']) ? $card_result['card_brand'] : '';
                    $card_data['last_4'] = isset($card_result['last_4']) ? $card_result['last_4'] : '';
                    $card_data['exp_month'] = isset($card_result['exp_month']) ? $card_result['exp_month'] : '';
                    $card_data['exp_year'] = isset($card_result['exp_year']) ? $card_result['exp_year'] : '';
                    $card_data['cardholder_name'] = isset($card_result['cardholder_name']) ? $card_result['cardholder_name'] : '';
                } elseif (is_string($card_result)) {
                    // 文字列（カードID）の場合
                    $card_data['card_id'] = $card_result;
                }

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "カード保存結果: " . json_encode($card_data) . "\n",
                    FILE_APPEND
                );
            } catch (Exception $e) {
                error_log("カード作成エラー: " . $e->getMessage());
                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "カード作成エラー: " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
                // カード作成エラーでも処理は続行（トライアルなしの場合は後で決済時にカード情報を取得）
            }

            // トライアルがない場合は初回決済を行う
            if (!$has_trial) {
                // 決済処理
                $payment_result = $square_api->process_subscription_payment(
                    $customer_id,
                    $payment_token,
                    $plan['amount'],
                    isset($plan['currency']) ? $plan['currency'] : 'JPY',
                    $plan['name'] . ' - 初回決済',
                    array(
                        'user_id' => $user_id,
                    )
                );

                if (!$payment_result) {
                    throw new Exception('初回決済に失敗しました');
                }

                // 決済IDの取得
                if (is_object($payment_result)) {
                    if (method_exists($payment_result, 'getId')) {
                        $payment_id = $payment_result->getId();
                    }

                    // カード情報の取得（まだカードIDがない場合）
                    if (empty($card_data['card_id'])) {
                        try {
                            if (
                                method_exists($payment_result, 'getCardDetails') &&
                                $payment_result->getCardDetails() !== null &&
                                method_exists($payment_result->getCardDetails(), 'getCard') &&
                                $payment_result->getCardDetails()->getCard() !== null
                            ) {

                                $card = $payment_result->getCardDetails()->getCard();
                                $card_data['card_id'] = method_exists($card, 'getId') ? $card->getId() : '';
                                $card_data['card_brand'] = method_exists($card, 'getCardBrand') ? $card->getCardBrand() : '';
                                $card_data['last_4'] = method_exists($card, 'getLast4') ? $card->getLast4() : '';
                                $card_data['exp_month'] = method_exists($card, 'getExpMonth') ? $card->getExpMonth() : '';
                                $card_data['exp_year'] = method_exists($card, 'getExpYear') ? $card->getExpYear() : '';
                            }
                        } catch (Exception $e) {
                            error_log("決済結果からのカード情報取得エラー: " . $e->getMessage());
                        }
                    }
                } elseif (is_array($payment_result)) {
                    $payment_id = isset($payment_result['id']) ? $payment_result['id'] : '';

                    // カード情報の取得（まだカードIDがない場合）
                    if (empty($card_data['card_id']) && isset($payment_result['card_details']) && isset($payment_result['card_details']['card'])) {
                        $card = $payment_result['card_details']['card'];
                        $card_data['card_id'] = isset($card['id']) ? $card['id'] : '';
                        $card_data['card_brand'] = isset($card['card_brand']) ? $card['card_brand'] : '';
                        $card_data['last_4'] = isset($card['last_4']) ? $card['last_4'] : '';
                        $card_data['exp_month'] = isset($card['exp_month']) ? $card['exp_month'] : '';
                        $card_data['exp_year'] = isset($card['exp_year']) ? $card['exp_year'] : '';
                    }
                }

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "初回決済結果: " . (is_string($payment_result) ? $payment_result : json_encode($payment_result)) . "\n",
                    FILE_APPEND
                );

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "決済後のカード情報: " . json_encode($card_data) . "\n",
                    FILE_APPEND
                );
            }

            // カードIDの有無をチェック
            if (empty($card_data['card_id'])) {
                // カードIDがなければ、顧客の登録済みカードを取得する
                try {
                    $cards = $square_api->get_customer_cards($customer_id);

                    if (!empty($cards)) {
                        foreach ($cards as $card) {
                            if (is_object($card) && method_exists($card, 'getId')) {
                                $card_data['card_id'] = $card->getId();
                                $card_data['card_brand'] = method_exists($card, 'getCardBrand') ? $card->getCardBrand() : '';
                                $card_data['last_4'] = method_exists($card, 'getLast4') ? $card->getLast4() : '';
                                $card_data['exp_month'] = method_exists($card, 'getExpMonth') ? $card->getExpMonth() : '';
                                $card_data['exp_year'] = method_exists($card, 'getExpYear') ? $card->getExpYear() : '';
                                break;
                            } elseif (is_array($card)) {
                                $card_data['card_id'] = isset($card['id']) ? $card['id'] : '';
                                $card_data['card_brand'] = isset($card['card_brand']) ? $card['card_brand'] : '';
                                $card_data['last_4'] = isset($card['last_4']) ? $card['last_4'] : '';
                                $card_data['exp_month'] = isset($card['exp_month']) ? $card['exp_month'] : '';
                                $card_data['exp_year'] = isset($card['exp_year']) ? $card['exp_year'] : '';
                                break;
                            }
                        }

                        file_put_contents(
                            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                            "顧客カードから取得したカード情報: " . json_encode($card_data) . "\n",
                            FILE_APPEND
                        );
                    }
                } catch (Exception $e) {
                    error_log("カード情報取得エラー: " . $e->getMessage());
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "カード情報取得エラー: " . $e->getMessage() . "\n",
                        FILE_APPEND
                    );
                }

                // 再度カード作成を試みる
                if (empty($card_data['card_id'])) {
                    try {
                        $new_card_result = $square_api->create_card(
                            $customer_id,
                            $payment_token,
                            ($user->first_name ?? '') . ' ' . ($user->last_name ?? '')
                        );

                        if (is_object($new_card_result) && method_exists($new_card_result, 'getId')) {
                            $card_data['card_id'] = $new_card_result->getId();
                            $card_data['card_brand'] = method_exists($new_card_result, 'getCardBrand') ? $new_card_result->getCardBrand() : '';
                            $card_data['last_4'] = method_exists($new_card_result, 'getLast4') ? $new_card_result->getLast4() : '';
                            $card_data['exp_month'] = method_exists($new_card_result, 'getExpMonth') ? $new_card_result->getExpMonth() : '';
                            $card_data['exp_year'] = method_exists($new_card_result, 'getExpYear') ? $new_card_result->getExpYear() : '';
                        } elseif (is_array($new_card_result)) {
                            $card_data['card_id'] = isset($new_card_result['id']) ? $new_card_result['id'] : '';
                            $card_data['card_brand'] = isset($new_card_result['card_brand']) ? $new_card_result['card_brand'] : '';
                            $card_data['last_4'] = isset($new_card_result['last_4']) ? $new_card_result['last_4'] : '';
                            $card_data['exp_month'] = isset($new_card_result['exp_month']) ? $new_card_result['exp_month'] : '';
                            $card_data['exp_year'] = isset($new_card_result['exp_year']) ? $new_card_result['exp_year'] : '';
                        } elseif (is_string($new_card_result)) {
                            $card_data['card_id'] = $new_card_result;
                        }

                        file_put_contents(
                            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                            "追加カード作成結果: " . json_encode($card_data) . "\n",
                            FILE_APPEND
                        );
                    } catch (Exception $e) {
                        error_log("追加カード作成エラー: " . $e->getMessage());
                        file_put_contents(
                            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                            "追加カード作成エラー: " . $e->getMessage() . "\n",
                            FILE_APPEND
                        );
                    }
                }
            }

            // サブスクリプション情報の作成
            $subscription_id = 'sub_' . uniqid();
            $now = current_time('mysql', false);
            $current_period_start = $now;

            // 次回請求日の計算
            $next_billing_date = new DateTime($now);
            $trial_end = null;

            // トライアル期間がある場合
            if ($has_trial) {
                $next_billing_date->modify('+' . $plan['trial_period_days'] . ' days');
                $trial_end = clone $next_billing_date;
                $trial_end = $trial_end->format('Y-m-d H:i:s');
            } else {
                // トライアルなしの場合、billing_cycleに応じて次回請求日を設定
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
            }

            $current_period_end = $next_billing_date->format('Y-m-d H:i:s');

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "サブスクリプション情報: ID={$subscription_id}, 開始={$current_period_start}, 次回請求={$current_period_end}\n",
                FILE_APPEND
            );

            // メタデータをJSON形式で保存
            $metadata = json_encode(array(
                'card_brand' => $card_data['card_brand'],
                'last_4' => $card_data['last_4'],
                'exp_month' => $card_data['exp_month'],
                'exp_year' => $card_data['exp_year'],
                'email' => $email,
            ));

            // サブスクリプション情報をDBに保存
            $subscription_data = array(
                'subscription_id' => $subscription_id,
                'user_id' => $user_id,
                'customer_id' => $customer_id,
                'plan_id' => $plan_id,
                'card_id' => $card_data['card_id'],
                'status' => 'ACTIVE',
                'amount' => $plan['amount'],
                'currency' => isset($plan['currency']) ? $plan['currency'] : 'JPY',
                'current_period_start' => $current_period_start,
                'current_period_end' => $current_period_end,
                'next_billing_date' => $current_period_end,
                'trial_end' => $trial_end,
                'created_at' => $now,
                'updated_at' => $now,
                'metadata' => $metadata
            );

            $subscription_result = EdelSquarePaymentProDB::save_subscription($subscription_data);

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "サブスクリプション保存結果: " . json_encode($subscription_result) . "\n",
                FILE_APPEND
            );

            // トライアル期間がない場合は初回決済が成功した場合のみ支払い情報を保存
            if (!$has_trial && !empty($payment_id)) {
                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "初回決済成功: 支払いID={$payment_id}\n",
                    FILE_APPEND
                );

                // 支払い情報をDBに保存
                $payment_data = array(
                    'subscription_id' => $subscription_id,
                    'payment_id' => $payment_id,
                    'amount' => $plan['amount'],
                    'currency' => isset($plan['currency']) ? $plan['currency'] : 'JPY',
                    'status' => 'SUCCESS',
                    'created_at' => $now,
                    'billing_period_start' => $current_period_start,
                    'billing_period_end' => $current_period_end
                );

                $payment_save_result = EdelSquarePaymentProDB::save_subscription_payment($payment_data);

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "決済履歴保存結果: " . json_encode($payment_save_result) . "\n",
                    FILE_APPEND
                );
            }

            // ユーザーログイン情報メール送信処理の詳細な検証
            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "ログイン情報メール送信判定: is_new_user=" . ($is_new_user ? 'true' : 'false') .
                    ", password='" . $password . "'" .
                    ", 条件結果=" . (($is_new_user && !empty($password)) ? '送信する' : '送信しない') . "\n",
                FILE_APPEND
            );

            // 新規ユーザーの場合はログイン情報をメールで通知
            if ($is_new_user && !empty($password)) {
                try {
                    $mail_result = $this->send_new_user_notification($user_id, $password);
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "ログイン情報メール送信結果: " . ($mail_result ? '成功' : '失敗') . "\n",
                        FILE_APPEND
                    );
                } catch (Exception $e) {
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "ログイン情報メール送信例外: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n",
                        FILE_APPEND
                    );
                }
            } else {
                // 条件を満たさない場合、send_new_user_notificationメソッドの存在確認
                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "send_new_user_notificationメソッド存在確認: " . (method_exists($this, 'send_new_user_notification') ? '存在する' : '存在しない') . "\n",
                    FILE_APPEND
                );

                // 強制的にメール送信を試みる（デバッグ用）
                if ($user_id > 0) {
                    try {
                        // メソッドの存在を確認
                        if (method_exists($this, 'send_new_user_notification')) {
                            $debug_mail_result = $this->send_new_user_notification($user_id, $password ?: 'デバッグ用パスワード');
                            file_put_contents(
                                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                                "デバッグ用ログイン情報メール送信結果: " . ($debug_mail_result ? '成功' : '失敗') . "\n",
                                FILE_APPEND
                            );
                        } else {
                            file_put_contents(
                                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                                "send_new_user_notificationメソッドが存在しません\n",
                                FILE_APPEND
                            );
                        }
                    } catch (Exception $e) {
                        file_put_contents(
                            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                            "デバッグ用メール送信例外: " . $e->getMessage() . "\n",
                            FILE_APPEND
                        );
                    }
                }
            }

            // サブスクリプション通知メールの送信
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-subscriptions.php';
            $subscriptions = new EdelSquarePaymentProSubscriptions();

            // メソッドのアクセス修飾子をチェック
            $reflection = new ReflectionClass($subscriptions);
            $method = null;
            try {
                $method = $reflection->getMethod('send_subscription_notification_emails');
                $is_public = $method->isPublic();

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "send_subscription_notification_emailsメソッドアクセス: " . ($is_public ? 'public' : 'private/protected') . "\n",
                    FILE_APPEND
                );

                // privateメソッドの場合はReflectionを使用してアクセス
                if (!$is_public) {
                    $method->setAccessible(true);
                    $method->invoke($subscriptions, $subscription_id, $email, $plan, $user_id);
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "Reflectionによるsend_subscription_notification_emails呼び出し完了\n",
                        FILE_APPEND
                    );
                } else {
                    // publicメソッドの場合は通常通り呼び出し
                    $subscriptions->send_subscription_notification_emails($subscription_id, $email, $plan, $user_id);
                }
            } catch (Exception $e) {
                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "サブスクリプション通知メール送信例外: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n",
                    FILE_APPEND
                );

                // 代替として通知メールを送信
                try {
                    // サブスクリプション登録通知メール（シンプルバージョン）
                    $site_name = get_bloginfo('name');
                    $customer_subject = sprintf('[%s] サブスクリプション登録完了のお知らせ', $site_name);
                    $customer_body = sprintf(
                        "%sのサブスクリプション「%s」へのご登録ありがとうございます。\n\n" .
                            "金額: %s円\n" .
                            "サブスクリプションID: %s\n\n" .
                            "マイアカウントページからご確認いただけます。\n%s",
                        $site_name,
                        $plan['name'],
                        $plan['amount'],
                        $subscription_id,
                        home_url()
                    );

                    wp_mail($email, $customer_subject, $customer_body, array(
                        'Content-Type: text/plain; charset=UTF-8',
                        'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
                    ));

                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "代替サブスクリプション通知メール送信完了\n",
                        FILE_APPEND
                    );
                } catch (Exception $mail_ex) {
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "代替メール送信例外: " . $mail_ex->getMessage() . "\n",
                        FILE_APPEND
                    );
                }
            }

            // 成功メッセージを取得
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();

            // リダイレクト先の決定
            $redirect_url = home_url('/');

            // 新規ユーザーか、既存ユーザーでログイン済みの場合はマイアカウントページへ
            if ($is_new_user || $is_logged_in) {
                if (!empty($settings['myaccount_page'])) {
                    $redirect_url = get_permalink($settings['myaccount_page']);
                }
            }
            // 既存ユーザーでログインしていない場合はログインページへ
            else {
                if (!empty($settings['login_redirect'])) {
                    $redirect_url = get_permalink($settings['login_redirect']);
                }
            }

            $response = array(
                'success' => true,
                'message' => nl2br(esc_html($settings['subscription_success_message'] ?? 'サブスクリプションの登録が完了しました。<br />マイアカウントページからご確認いただけます。')),
                'subscription_id' => $subscription_id,
                'redirect_url' => $redirect_url,
            );

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "処理成功: " . json_encode($response) . "\n",
                FILE_APPEND
            );
        } catch (Exception $e) {
            $error_message = $e->getMessage();

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "エラー発生: " . $error_message . "\n" .
                    "トレース: " . $e->getTraceAsString() . "\n",
                FILE_APPEND
            );

            $response = array(
                'success' => false,
                'message' => $error_message,
            );
        }

        wp_send_json($response);
    }

    /**
     * ユーザー登録またはログイン処理
     *
     * @param string $email ユーザーメールアドレス
     * @param string $first_name ユーザー名（名）
     * @param string $last_name ユーザー名（姓）
     * @return array ユーザー情報配列 (user_id, is_new_user, is_logged_in, password)
     */
    private function process_user_registration($email, $first_name = '', $last_name = '') {
        // 結果配列の初期化
        $result = array(
            'user_id' => 0,
            'is_new_user' => false,
            'is_logged_in' => false,
            'password' => ''
        );

        // メールアドレスが空または無効な場合
        if (empty($email) || !is_email($email)) {
            return $result;
        }

        // 現在のユーザーIDを取得
        $user_id = get_current_user_id();

        // ログイン状態の確認
        $is_logged_in = ($user_id > 0);

        // ログインしていない場合
        if (!$is_logged_in) {
            // メールアドレスで既存ユーザー検索
            $user = get_user_by('email', $email);

            if ($user) {
                // 既存ユーザーの場合
                $result['user_id'] = $user->ID;
                $result['is_logged_in'] = false;
            } else {
                // 新規ユーザー作成
                $username = $this->generate_username($email);
                $password = wp_generate_password(12, true, false);

                $user_id = wp_create_user($username, $password, $email);

                if (!is_wp_error($user_id)) {
                    // ユーザーメタ情報を更新
                    if (!empty($first_name)) {
                        update_user_meta($user_id, 'first_name', $first_name);
                    }
                    if (!empty($last_name)) {
                        update_user_meta($user_id, 'last_name', $last_name);
                    }

                    // パスワードを一時的にtransientとして保存（1時間有効）
                    set_transient('edel_square_initial_password_' . $user_id, $password, HOUR_IN_SECONDS);

                    // 表示名を設定
                    $display_name = trim($first_name . ' ' . $last_name);
                    if (!empty($display_name)) {
                        wp_update_user(array(
                            'ID' => $user_id,
                            'display_name' => $display_name
                        ));
                    }

                    // ユーザーを自動ログイン
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);

                    $result['user_id'] = $user_id;
                    $result['is_new_user'] = true;
                    $result['is_logged_in'] = true;
                    $result['password'] = $password;
                } else {
                    // エラーログ
                    error_log('Square Payment Pro - ユーザー作成エラー: ' . $user_id->get_error_message());
                }
            }
        } else {
            // 既にログイン中の場合
            $result['user_id'] = $user_id;
            $result['is_logged_in'] = true;
        }

        return $result;
    }

    /**
     * メールアドレスからユーザー名を生成
     *
     * @param string $email メールアドレス
     * @return string ユーザー名
     */
    private function generate_username($email) {
        // メールアドレスの@前の部分を取得
        $parts = explode('@', $email);
        $username = $parts[0];

        // ユニークなユーザー名にするためにランダム文字列を追加
        $username = sanitize_user($username . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 5), true);

        // 既存のユーザー名と重複しないように確認
        $i = 1;
        $original_username = $username;
        while (username_exists($username)) {
            $username = $original_username . '_' . $i;
            $i++;
        }

        return $username;
    }

    /**
     * reCAPTCHAトークンを検証（シンプル版）
     *
     * @param string $token reCAPTCHAトークン
     * @return array 検証結果
     */
    private function verify_recaptcha($token) {
        try {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();
            $secret_key = $settings['recaptcha_secret_key'];

            // WordPress HTTP APIを使用
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $secret_key,
                    'response' => $token
                ]
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message()
                ];
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            return $result ?: ['success' => false, 'error' => 'Invalid response'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ログアウト時のリダイレクト
     */
    public function logout_redirect() {
        wp_redirect(home_url());
        exit;
    }
}
