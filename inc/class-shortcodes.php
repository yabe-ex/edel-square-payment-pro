<?php

/**
 * ショートコード関連のクラス
 */
class EdelSquarePaymentProShortcodes {
    /**
     * コンストラクタ
     */
    public function __construct() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-license-manager.php';

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

        // フォーム処理のフック
        add_action('template_redirect', array($this, 'handle_form_submissions'));
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
                has_shortcode($post->post_content, 'edel_square_subscription') ||
                has_shortcode($post->post_content, 'edel_square_myaccount')
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

        if (EdelSquarePaymentProLicense::is_license_valid()) {
            return "<p>ライセンスが無効です。</p>";
        }

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
        var_dump($atts);
        echo '<div style="background: red; color: white; padding: 10px;">デバッグ: このコードが実行されています</div>';
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
                            <th>金額</th>
                            <td><?php echo esc_html($subscription->amount) . ' ' . esc_html($subscription->currency); ?></td>
                        </tr>
                        <tr>
                            <th>ステータス</th>
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
                            <th>次回請求日</th>
                            <td><?php echo esc_html(date_i18n('Y年m月d日', strtotime($subscription->next_billing_date))); ?></td>
                        </tr>
                        <?php if (!empty($card_info)) : ?>
                            <tr>
                                <th>支払い方法</th>
                                <td><?php echo esc_html($card_info); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                    <?php
                    // ===== デバッグ用コード（確認後削除） =====
                    // echo '<div style="background: #ffffcc; border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
                    // echo '<strong>デバッグ情報:</strong><br>';
                    // echo 'show_card_form: ' . ($atts['show_card_form'] ?? 'not set') . '<br>';
                    // echo 'subscription status: ' . $subscription->status . '<br>';
                    // echo 'card_id: ' . ($subscription->card_id ?? 'empty') . '<br>';
                    // echo 'card_id empty?: ' . (empty($subscription->card_id) ? 'YES' : 'NO') . '<br>';

                    $show_form_condition = (
                        $atts['show_card_form'] === 'yes' &&
                        ($subscription->status === 'PAUSED' || empty($subscription->card_id))
                    );
                    // echo 'フォーム表示条件: ' . ($show_form_condition ? 'TRUE' : 'FALSE') . '<br>';
                    // echo '</div>';
                    ?>

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

                            <!-- カード更新トリガーボタン -->
                            <div class="card-update-button-container">
                                <button type="button" class="button show-card-form-button"
                                    data-subscription-id="<?php echo esc_attr($subscription->subscription_id); ?>">
                                    カード情報を更新する
                                </button>
                            </div>

                            <!-- カード更新フォーム（最初は非表示） -->
                            <div id="card-update-form-container-<?php echo esc_attr($subscription->subscription_id); ?>"
                                class="card-update-form-container" style="display: none;">

                                <form method="post" id="card-update-form-<?php echo esc_attr($subscription->subscription_id); ?>" class="card-update-form">
                                    <div class="edel-square-form-group">
                                        <label for="card-container-<?php echo esc_attr($subscription->subscription_id); ?>">クレジットカード情報</label>
                                        <div id="card-container-<?php echo esc_attr($subscription->subscription_id); ?>" class="square-card-container"></div>
                                    </div>

                                    <div id="card-errors-<?php echo esc_attr($subscription->subscription_id); ?>" class="card-errors" role="alert"></div>

                                    <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->subscription_id); ?>">
                                    <input type="hidden" name="action" value="edel_square_update_card">
                                    <input type="hidden" name="payment_token" id="payment-token-<?php echo esc_attr($subscription->subscription_id); ?>" value="">
                                    <?php wp_nonce_field('edel_square_update_card_nonce', 'card_update_nonce'); ?>

                                    <div class="card-update-form-actions">
                                        <button type="submit" class="button button-primary update-card-submit-button"
                                            data-subscription-id="<?php echo esc_attr($subscription->subscription_id); ?>">
                                            更新
                                        </button>
                                        <button type="button" class="button cancel-card-form-button"
                                            data-subscription-id="<?php echo esc_attr($subscription->subscription_id); ?>">
                                            キャンセル
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($subscription->status === 'ACTIVE') : ?>
                        <div class="subscription-actions">
                            <form method="post" class="cancel-subscription-form">
                                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->subscription_id); ?>">
                                <input type="hidden" name="action" value="edel_square_cancel_subscription">
                                <?php wp_nonce_field('cancel_subscription_' . $subscription->subscription_id, 'cancel_nonce'); ?>
                                <button type="submit" class="button cancel-subscription-button" onclick="return confirm('このサブスクリプションをキャンセルしてもよろしいですか？');">サブスクリプションをキャンセルする</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
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

                $this->send_onetime_payment_notification(array(
                    'item_name' => $item_name,
                    'amount' => $amount,
                    'customer_email' => $email,
                    'payment_id' => $payment_id, // 既存のコードから取得
                    'transaction_date' => current_time('mysql'),
                    'user_name' => trim(($first_name ?? '') . ' ' . ($last_name ?? '')),
                    'user_id' => $user_id ?? 0
                ));

                // 決済成功後の処理
                $redirect_url = $this->handle_payment_success($payment_data, $user_id, $is_new_user, $is_logged_in, $password);

                // 成功レスポンス
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
                $settings = EdelSquarePaymentProSettings::get_settings();

                // 成功レスポンス（サブスクリプション決済と同じ構造）
                wp_send_json_success(array(
                    'message' => nl2br(esc_html($settings['success_message'] ?? 'ご購入ありがとうございます。決済が完了しました。<br />マイアカウントページでご確認いただけます。')),
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
     * 買い切り決済完了メール通知を送信
     */
    private function send_onetime_payment_notification($payment_data) {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        // メール通知が無効の場合は送信しない
        if (empty($settings['enable_onetime_payment_notification']) || $settings['enable_onetime_payment_notification'] !== '1') {
            return false;
        }

        $mail_results = array();

        // 管理者向けメール送信
        $mail_results['admin'] = $this->send_admin_onetime_payment_email($payment_data, $settings);

        // 購入者向けメール送信
        $mail_results['customer'] = $this->send_customer_onetime_payment_email($payment_data, $settings);

        return in_array(true, $mail_results, true);
    }

    /**
     * 管理者向け買い切り決済完了メール送信
     */
    private function send_admin_onetime_payment_email($payment_data, $settings) {
        $subject = EdelSquarePaymentProSettings::replace_placeholders($settings['admin_email_subject'], $payment_data);
        $message = EdelSquarePaymentProSettings::replace_placeholders($settings['admin_email_body'], $payment_data);

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        $admin_email = get_option('admin_email');
        $mail_sent = wp_mail($admin_email, $subject, $message, $headers);

        if ($mail_sent) {
            error_log('Square Payment Pro - 管理者向け買い切り決済メール送信成功: ' . $admin_email);
        } else {
            error_log('Square Payment Pro - 管理者向け買い切り決済メール送信失敗: ' . $admin_email);
        }

        return $mail_sent;
    }

    /**
     * 購入者向け買い切り決済完了メール送信
     */
    private function send_customer_onetime_payment_email($payment_data, $settings) {
        $customer_email = $payment_data['customer_email'] ?? '';
        if (empty($customer_email)) {
            error_log('Square Payment Pro - 購入者メールアドレスが取得できません');
            return false;
        }

        $subject = EdelSquarePaymentProSettings::replace_placeholders($settings['customer_email_subject'], $payment_data);
        $message = EdelSquarePaymentProSettings::replace_placeholders($settings['customer_email_body'], $payment_data);

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        $mail_sent = wp_mail($customer_email, $subject, $message, $headers);

        if ($mail_sent) {
            error_log('Square Payment Pro - 購入者向け買い切り決済メール送信成功: ' . $customer_email);
        } else {
            error_log('Square Payment Pro - 購入者向け買い切り決済メール送信失敗: ' . $customer_email);
        }

        return $mail_sent;
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
                    require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
                    $settings = EdelSquarePaymentProSettings::get_settings();

                    // 成功レスポンス（サブスクリプション決済と同じ構造）
                    wp_send_json_success(array(
                        'message' => nl2br(esc_html($settings['success_message'] ?? 'ご購入ありがとうございます。決済が完了しました。<br />マイアカウントページでご確認いただけます。')),
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
     * サブスクリプションの概要表示（概要タブ用）
     */
    private function render_subscription_summary($subscription) {
        // 配列とオブジェクトの両方に対応
        $plan_id = is_object($subscription) ? $subscription->plan_id : $subscription['plan_id'];
        $status = is_object($subscription) ? $subscription->status : $subscription['status'];
        $amount = is_object($subscription) ? $subscription->amount : $subscription['amount'];
        $next_billing_date = is_object($subscription) ? $subscription->next_billing_date : $subscription['next_billing_date'];
        $metadata = is_object($subscription) ? $subscription->metadata : $subscription['metadata'];

        $plan = EdelSquarePaymentProDB::get_plan($plan_id);
        $plan_name = $plan ? $plan['name'] : '不明なプラン';

        // メタデータがあれば取得（文字列の場合はデコード、配列の場合はそのまま使用）
        $metadata_array = is_array($metadata) ? $metadata : json_decode($metadata, true);
        $card_info = '';
        if (is_array($metadata_array) && !empty($metadata_array['card_brand']) && !empty($metadata_array['last_4'])) {
            $card_info = $metadata_array['card_brand'] . ' **** ' . $metadata_array['last_4'];
        }
    ?>
        <div class="subscription-summary-item">
            <div class="subscription-summary-header">
                <h5><?php echo esc_html($plan_name); ?></h5>
                <span class="subscription-status status-<?php echo strtolower($status ?? ''); ?>">
                    <?php
                    switch ($status) {
                        case 'ACTIVE':
                            echo '有効';
                            break;
                        case 'PAUSED':
                            echo '一時停止';
                            break;
                        case 'CANCELED':
                            echo 'キャンセル済み';
                            break;
                        default:
                            echo esc_html($status ?? '不明');
                    }
                    ?>
                </span>
            </div>
            <div class="subscription-summary-details">
                <span class="amount">￥<?php echo number_format($amount ?? 0); ?>/月</span>
                <span class="next-billing">次回請求: <?php echo date_i18n('m/d', strtotime($next_billing_date ?? 'now')); ?></span>
                <?php if (!empty($card_info)): ?>
                    <span class="card-info"><?php echo esc_html($card_info); ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * サブスクリプションタブの表示
     */
    private function render_subscriptions_tab($subscriptions, $atts) {
        if (empty($subscriptions)) {
            echo '<div class="no-subscriptions"><p>有効なサブスクリプションはありません。</p></div>';
            return;
        }
    ?>
        <div class="subscriptions-tab-content">
            <h3>マイサブスクリプション</h3>

            <div class="edel-square-subscriptions">
                <?php foreach ($subscriptions as $subscription) :
                    // 配列とオブジェクトの両方に対応
                    $plan_id = is_object($subscription) ? $subscription->plan_id : $subscription['plan_id'];
                    $status = is_object($subscription) ? $subscription->status : $subscription['status'];
                    $amount = is_object($subscription) ? $subscription->amount : $subscription['amount'];
                    $currency = is_object($subscription) ? $subscription->currency : $subscription['currency'];
                    $next_billing_date = is_object($subscription) ? $subscription->next_billing_date : $subscription['next_billing_date'];
                    $metadata = is_object($subscription) ? $subscription->metadata : $subscription['metadata'];
                    $subscription_id = is_object($subscription) ? $subscription->subscription_id : $subscription['subscription_id'];
                    $card_id = is_object($subscription) ? ($subscription->card_id ?? null) : ($subscription['card_id'] ?? null);

                    $plan = EdelSquarePaymentProDB::get_plan($plan_id);
                    $plan_name = $plan ? $plan['name'] : '不明なプラン';

                    // メタデータがあれば取得（文字列の場合はデコード、配列の場合はそのまま使用）
                    $metadata_array = is_array($metadata) ? $metadata : json_decode($metadata, true);
                    $card_info = '';
                    if (is_array($metadata_array) && !empty($metadata_array['card_brand']) && !empty($metadata_array['last_4'])) {
                        $card_info = $metadata_array['card_brand'] . ' **** **** **** ' . $metadata_array['last_4'];
                        if (!empty($metadata_array['exp_month']) && !empty($metadata_array['exp_year'])) {
                            $card_info .= ' (' . $metadata_array['exp_month'] . '/' . $metadata_array['exp_year'] . ')';
                        }
                    }
                ?>
                    <div class="subscription-item">
                        <h4><?php echo esc_html($plan_name); ?></h4>
                        <table class="subscription-details">
                            <tr>
                                <th>金額:</th>
                                <td><?php echo esc_html($amount ?? 0) . ' ' . esc_html($currency ?? 'JPY'); ?></td>
                            </tr>
                            <tr>
                                <th>ステータス:</th>
                                <td><?php
                                    switch ($status) {
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
                                            echo esc_html($status ?? '不明');
                                    }
                                    ?></td>
                            </tr>
                            <tr>
                                <th>次回請求日:</th>
                                <td><?php echo esc_html(date_i18n('Y年m月d日', strtotime($next_billing_date ?? 'now'))); ?></td>
                            </tr>
                            <?php if (!empty($card_info)) : ?>
                                <tr>
                                    <th>支払い方法:</th>
                                    <td><?php echo esc_html($card_info); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <!-- カード更新フォーム -->
                        <?php if (
                            $atts['show_card_form'] === 'yes' &&
                            $status !== 'CANCELED' && $status !== 'CANCELLED'
                        ): ?>
                            <div class="card-update-section">
                                <h4>カード情報の更新</h4>
                                <?php
                                // ステータスに応じて文言を変更
                                if ($status === 'PAUSED') {
                                    $message = 'サブスクリプションを再開するために、カード情報を更新してください。';
                                } elseif (empty($card_id)) {
                                    $message = '決済を続行するために、カード情報を登録してください。';
                                } else {
                                    $message = 'カード情報を変更・更新できます。';
                                }
                                ?>
                                <p><?php echo esc_html($message); ?></p>

                                <!-- カード更新トリガーボタン -->
                                <div class="card-update-button-container">
                                    <button type="button" class="button show-card-form-button"
                                        data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                                        カード情報を更新する
                                    </button>
                                </div>

                                <!-- カード更新フォーム（最初は非表示） -->
                                <div id="card-update-form-container-<?php echo esc_attr($subscription_id); ?>"
                                    class="card-update-form-container" style="display: none;">

                                    <form method="post" id="card-update-form-<?php echo esc_attr($subscription_id); ?>" class="card-update-form">
                                        <div class="edel-square-form-group">
                                            <label for="card-container-<?php echo esc_attr($subscription_id); ?>">クレジットカード情報</label>
                                            <div id="card-container-<?php echo esc_attr($subscription_id); ?>" class="square-card-container"></div>
                                        </div>

                                        <div id="card-errors-<?php echo esc_attr($subscription_id); ?>" class="card-errors" role="alert"></div>

                                        <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription_id); ?>">
                                        <input type="hidden" name="action" value="edel_square_update_card">
                                        <input type="hidden" name="payment_token" id="payment-token-<?php echo esc_attr($subscription_id); ?>" value="">
                                        <?php wp_nonce_field('edel_square_update_card_nonce', 'card_update_nonce'); ?>

                                        <div class="card-update-form-actions">
                                            <button type="submit" class="button button-primary update-card-submit-button"
                                                data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                                                更新
                                            </button>
                                            <button type="button" class="button cancel-card-form-button"
                                                data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
                                                キャンセル
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- サブスクリプション操作 -->
                        <?php if ($status === 'ACTIVE') : ?>
                            <div class="subscription-actions">
                                <form method="post" class="cancel-subscription-form">
                                    <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription_id); ?>">
                                    <input type="hidden" name="action" value="edel_square_cancel_subscription">
                                    <?php wp_nonce_field('cancel_subscription_' . $subscription_id, 'cancel_nonce'); ?>
                                    <button type="submit" class="button cancel-subscription-button" onclick="return confirm('このサブスクリプションをキャンセルしてもよろしいですか？');">サブスクリプションをキャンセルする</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    /**
     * 決済履歴タブの表示
     */
    private function render_payments_tab($payments, $atts) {
        if (empty($payments)) {
            echo '<div class="no-payments"><p>決済履歴はありません。</p></div>';
            return;
        }
    ?>
        <div class="payments-tab-content">
            <h3>決済履歴</h3>

            <div class="edel-square-payments">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>内容</th>
                            <th>金額</th>
                            <th>ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment) :
                            // 配列とオブジェクトの両方に対応
                            $created_at = is_object($payment) ? $payment->created_at : $payment['created_at'];
                            $item_name = is_object($payment) ? $payment->item_name : $payment['item_name'];
                            $amount = is_object($payment) ? $payment->amount : $payment['amount'];
                            $status = is_object($payment) ? $payment->status : $payment['status'];
                            $payment_type = is_object($payment) ? ($payment->payment_type ?? 'onetime') : ($payment['payment_type'] ?? 'onetime');
                        ?>
                            <tr>
                                <td><?php echo date_i18n('Y/m/d H:i', strtotime($created_at ?? 'now')); ?></td>
                                <td>
                                    <?php echo esc_html($item_name ?? '不明な商品'); ?>
                                    <?php if ($payment_type === 'subscription'): ?>
                                        <span class="payment-type-badge">サブスク</span>
                                    <?php endif; ?>
                                </td>
                                <td>￥<?php echo number_format($amount ?? 0); ?></td>
                                <td>
                                    <span class="payment-status status-<?php echo strtolower($status ?? 'unknown'); ?>">
                                        <?php
                                        switch ($status) {
                                            case 'COMPLETED':
                                                echo '完了';
                                                break;
                                            case 'PENDING':
                                                echo '処理中';
                                                break;
                                            case 'FAILED':
                                                echo '失敗';
                                                break;
                                            default:
                                                echo esc_html($status ?? '不明');
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php
    }

    /**
     * 設定タブの表示
     */
    private function render_settings_tab($user) {
    ?>
        <div class="settings-tab-content">
            <h3>アカウント設定</h3>

            <!-- アカウント情報 -->
            <div class="account-info-section">
                <h4>アカウント情報</h4>
                <table class="account-info-table">
                    <tr>
                        <th>メールアドレス</th>
                        <td><?php echo esc_html($user->user_email); ?></td>
                    </tr>
                    <tr>
                        <th>登録日</th>
                        <td><?php echo date_i18n('Y年m月d日', strtotime($user->user_registered)); ?></td>
                    </tr>
                </table>
            </div>

            <div class="edel-square-myaccount-user-info">
                <?php $this->display_password_change_messages(); ?>
            </div>

            <!-- パスワード変更 -->
            <div class="password-change-section">
                <h4>パスワード変更</h4>
                <form method="post" class="password-change-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="current_password">現在のパスワード</label></th>
                            <td>
                                <input type="password" id="current_password" name="current_password" class="regular-text" autocomplete="current-password" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="new_password">新しいパスワード</label></th>
                            <td>
                                <input type="password" id="new_password" name="new_password" class="regular-text" autocomplete="new-password" />
                                <p class="description">8文字以上で入力してください</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="confirm_password">新しいパスワード（確認）</label></th>
                            <td>
                                <input type="password" id="confirm_password" name="confirm_password" class="regular-text" autocomplete="new-password" />
                            </td>
                        </tr>
                    </table>

                    <input type="hidden" name="action" value="edel_square_change_password">
                    <?php wp_nonce_field('edel_square_change_password_nonce', 'password_nonce'); ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary">パスワードを変更</button>
                    </p>
                </form>
            </div>
        </div>
    <?php
    }

    /**
     * パスワード変更処理
     */
    private function process_password_change() {
        // ログイン確認
        if (!is_user_logged_in()) {
            wp_redirect(add_query_arg('error', 'not_logged_in', wp_get_referer()));
            exit;
        }

        // nonce検証
        if (!isset($_POST['password_nonce']) || !wp_verify_nonce($_POST['password_nonce'], 'edel_square_change_password_nonce')) {
            wp_redirect(add_query_arg('error', 'nonce_failed', wp_get_referer()));
            exit;
        }

        $user_id = get_current_user_id();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 入力値検証
        $validation_error = $this->validate_password_change($current_password, $new_password, $confirm_password);
        if ($validation_error) {
            wp_redirect(add_query_arg('error', $validation_error, wp_get_referer()));
            exit;
        }

        // 現在のパスワード確認
        if (!wp_check_password($current_password, get_userdata($user_id)->user_pass, $user_id)) {
            wp_redirect(add_query_arg('error', 'current_password_incorrect', wp_get_referer()));
            exit;
        }

        // パスワード更新
        // $result = wp_set_password($new_password, $user_id);
        $result = wp_update_user(array(
            'ID' => $user_id,
            'user_pass' => $new_password
        ));

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('error', 'password_update_failed', wp_get_referer()));
            exit;
        }

        // 成功時のリダイレクト
        wp_redirect(add_query_arg('success', 'password_changed', wp_get_referer()));
        exit;
    }

    /**
     * パスワード変更のバリデーション
     *
     * @param string $current_password 現在のパスワード
     * @param string $new_password 新しいパスワード
     * @param string $confirm_password 確認パスワード
     * @return string|null エラーメッセージまたはnull
     */
    private function validate_password_change($current_password, $new_password, $confirm_password) {
        // 現在のパスワードが空
        if (empty($current_password)) {
            return 'current_password_empty';
        }

        // 新しいパスワードが空
        if (empty($new_password)) {
            return 'new_password_empty';
        }

        // 確認パスワードが空
        if (empty($confirm_password)) {
            return 'confirm_password_empty';
        }

        // パスワードの長さチェック（8文字以上）
        if (strlen($new_password) < 8) {
            return 'password_too_short';
        }

        // 新しいパスワードと確認パスワードの一致確認
        if ($new_password !== $confirm_password) {
            return 'password_mismatch';
        }

        // 現在のパスワードと新しいパスワードが同じ
        if ($current_password === $new_password) {
            return 'same_password';
        }

        return null;
    }

    /**
     * パスワード変更のエラーメッセージと成功メッセージの表示
     */
    private function display_password_change_messages() {
        // エラーメッセージの処理
        if (isset($_GET['error'])) {
            $error_messages = array(
                'not_logged_in' => 'ログインが必要です。',
                'nonce_failed' => 'セキュリティチェックに失敗しました。再度お試しください。',
                'current_password_empty' => '現在のパスワードを入力してください。',
                'new_password_empty' => '新しいパスワードを入力してください。',
                'confirm_password_empty' => 'パスワード確認を入力してください。',
                'password_too_short' => 'パスワードは8文字以上で入力してください。',
                'password_mismatch' => '新しいパスワードと確認パスワードが一致しません。',
                'same_password' => '現在のパスワードと同じパスワードは使用できません。',
                'current_password_incorrect' => '現在のパスワードが正しくありません。',
                'password_update_failed' => 'パスワードの更新に失敗しました。しばらくしてから再度お試しください。',
            );

            $error_code = sanitize_text_field($_GET['error']);
            if (isset($error_messages[$error_code])) {
                echo '<div class="notice notice-error"><p>' . esc_html($error_messages[$error_code]) . '</p></div>';
            }
        }

        // 成功メッセージの処理
        if (isset($_GET['success']) && $_GET['success'] === 'password_changed') {
            echo '<div class="notice notice-success"><p>パスワードが正常に変更されました。</p></div>';
        }
    }

    /**
     * マイアカウントページの表示（改善版）
     *
     * @param array $atts ショートコード属性
     * @return string HTML出力
     */
    public function render_myaccount_page($atts) {
        // ショートコード属性のデフォルト値を設定
        $atts = shortcode_atts(array(
            'show_user_info' => 'yes',
            'show_subscriptions' => 'yes',
            'show_payment_history' => 'yes',
            'items_per_page' => 10,
            'enable_ajax' => 'yes',
            'show_card_form' => 'yes'
        ), $atts, 'edel_square_myaccount');

        // ログイン確認
        if (!is_user_logged_in()) {
            return $this->handle_not_logged_in($atts);
        }

        // セキュリティ確認
        if (!$this->verify_user_access()) {
            return $this->render_access_denied();
        }

        // GETパラメータで画面切り替えをチェック
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $subscription_id = isset($_GET['subscription_id']) ? sanitize_text_field($_GET['subscription_id']) : '';

        // カード更新画面の表示
        if ($action === 'update_card' && !empty($subscription_id)) {
            return $this->render_card_update_page($subscription_id);
        }

        // AJAX リクエストの処理
        if ($this->is_ajax_request() && $atts['enable_ajax'] === 'yes') {
            return $this->handle_ajax_request($atts);
        }

        // 通常のマイアカウント画面
        return $this->render_standard_myaccount_page($atts);
    }

    /**
     * ログインしていない場合の処理
     */
    private function handle_not_logged_in($atts) {
        // 設定を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        // カスタムログインページへのリダイレクト
        if (!empty($settings['login_redirect'])) {
            $login_url = get_permalink((int)$settings['login_redirect']);
            if ($login_url && !is_admin()) {
                // フロントエンドの場合のみリダイレクト
                wp_redirect($login_url);
                exit;
            }
        }

        // リダイレクトできない場合はログインフォームを表示
        return $this->render_login_form($atts);
    }

    /**
     * ユーザーアクセス権限の確認
     */
    private function verify_user_access() {
        $current_user = wp_get_current_user();

        // ユーザーが存在し、適切な権限を持っているかチェック
        if (!$current_user || !$current_user->exists()) {
            return false;
        }

        // 必要に応じて追加のセキュリティチェック
        return true;
    }

    /**
     * アクセス拒否画面の表示
     */
    private function render_access_denied() {
        ob_start();
    ?>
        <div id="edel-square-myaccount" class="edel-square-myaccount">
            <div class="edel-square-message error">
                <h3>アクセスが拒否されました</h3>
                <p>このページにアクセスする権限がありません。</p>
                <p><a href="<?php echo home_url(); ?>">ホームページに戻る</a></p>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * AJAX リクエストかどうかを判定
     */
    private function is_ajax_request() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    /**
     * AJAX リクエストの処理
     */
    private function handle_ajax_request($atts) {
        $action = isset($_POST['myaccount_action']) ? sanitize_text_field($_POST['myaccount_action']) : '';

        switch ($action) {
            case 'load_more_payments':
                return $this->load_more_payment_history($atts);
            case 'refresh_subscriptions':
                return $this->refresh_subscription_data($atts);
            default:
                wp_send_json_error('無効なアクションです。');
        }
    }

    /**
     * 改善された標準マイアカウントページ（タブ形式）
     */
    private function render_standard_myaccount_page($atts) {
        // 現在のユーザー情報
        $user = wp_get_current_user();

        // データの事前読み込みとキャッシュ
        $subscriptions = $this->get_cached_user_subscriptions($user->ID);
        $payments = $this->get_cached_user_payments($user->ID, $atts['items_per_page']);

        // 統計情報の計算
        $stats = $this->calculate_user_stats($user->ID, $subscriptions, $payments);

        ob_start();
    ?>
        <div id="edel-square-myaccount" class="edel-square-myaccount" data-user-id="<?php echo esc_attr($user->ID); ?>">
            <h2>マイアカウント</h2>

            <?php $this->render_status_messages(); ?>

            <?php if ($atts['show_user_info'] === 'yes'): ?>
                <?php $this->render_user_dashboard($user, $stats); ?>
            <?php endif; ?>

            <!-- タブナビゲーション -->
            <div class="edel-square-tabs">
                <div class="edel-square-tab edel-square-tab-active" data-tab="overview">
                    <span>概要</span>
                </div>
                <?php if ($atts['show_subscriptions'] === 'yes'): ?>
                    <div class="edel-square-tab" data-tab="subscriptions">
                        <i class="icon-subscription"></i>
                        サブスクリプション
                        <?php if ($stats['active_subscriptions'] > 0): ?>
                            <span class="tab-badge"><?php echo $stats['active_subscriptions']; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="edel-square-tab" data-tab="payments">
                    <i class="icon-payments"></i>
                    決済履歴
                    <?php if ($stats['total_payments'] > 0): ?>
                        <span class="tab-badge"><?php echo $stats['total_payments']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="edel-square-tab" data-tab="settings">
                    <i class="icon-settings"></i>
                    設定
                </div>

            </div>

            <!-- タブコンテンツ -->
            <div class="edel-square-tab-content edel-square-tab-content-active" id="tab-overview">
                <?php $this->render_overview_tab($user, $subscriptions, $payments, $stats); ?>
            </div>

            <?php if ($atts['show_subscriptions'] === 'yes'): ?>
                <div class="edel-square-tab-content" id="tab-subscriptions">
                    <?php $this->render_subscriptions_tab($subscriptions, $atts); ?>
                </div>
            <?php endif; ?>

            <?php if ($atts['show_payment_history'] === 'yes'): ?>
                <div class="edel-square-tab-content" id="tab-payments">
                    <?php $this->render_payments_tab($payments, $atts); ?>
                </div>
            <?php endif; ?>

            <div class="edel-square-tab-content" id="tab-settings">
                <?php $this->render_settings_tab($user); ?>
            </div>

            <div class="edel-square-logout">
                <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="edel-square-logout-link">
                    ログアウト
                </a>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * ユーザーダッシュボードの表示
     */
    private function render_user_dashboard($user, $stats) {
    ?>
        <div class="edel-square-user-dashboard">
            <div class="edel-square-stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_subscriptions']; ?></div>
                    <div class="stat-label">アクティブなサブスクリプション</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">￥<?php echo number_format($stats['total_spent']); ?></div>
                    <div class="stat-label">総決済金額</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_payments']; ?></div>
                    <div class="stat-label">決済回数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">￥<?php echo number_format($stats['monthly_total']); ?></div>
                    <div class="stat-label">今月の支払い</div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * 概要タブの表示
     */
    private function render_overview_tab($user, $subscriptions, $payments, $stats) {
    ?>
        <div class="overview-content">
            <h3>最近のアクティビティ</h3>

            <?php if (!empty($subscriptions)): ?>
                <div class="recent-subscriptions">
                    <h4>アクティブなサブスクリプション</h4>
                    <?php foreach (array_slice($subscriptions, 0, 3) as $subscription): ?>
                        <?php $this->render_subscription_summary($subscription); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($payments)): ?>
                <div class="recent-payments">
                    <h4>最近の決済</h4>
                    <div class="payments-list">
                        <?php foreach (array_slice($payments, 0, 5) as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-info">
                                    <strong><?php echo esc_html($payment['item_name']); ?></strong>
                                    <span class="payment-date"><?php echo date_i18n('Y/m/d', strtotime($payment['created_at'])); ?></span>
                                </div>
                                <div class="payment-amount">
                                    ￥<?php echo number_format($payment['amount']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * キャッシュされたユーザーサブスクリプション取得
     */
    private function get_cached_user_subscriptions($user_id) {
        $cache_key = 'edel_square_user_subscriptions_' . $user_id;
        $subscriptions = wp_cache_get($cache_key);

        if ($subscriptions === false) {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
            $subscriptions = EdelSquarePaymentProDB::get_user_subscriptions($user_id);
            wp_cache_set($cache_key, $subscriptions, '', 300); // 5分間キャッシュ
        }

        return $subscriptions;
    }

    /**
     * キャッシュされたユーザー決済履歴取得
     */
    private function get_cached_user_payments($user_id, $limit = 10) {
        $cache_key = 'edel_square_user_payments_' . $user_id . '_' . $limit;
        $payments = wp_cache_get($cache_key);

        if ($payments === false) {
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
            $all_payments = EdelSquarePaymentProDB::get_user_payments($user_id);
            $payments = array_slice($all_payments, 0, $limit);
            wp_cache_set($cache_key, $payments, '', 300); // 5分間キャッシュ
        }

        return $payments;
    }

    /**
     * ユーザー統計情報の計算
     */
    private function calculate_user_stats($user_id, $subscriptions, $payments) {
        $stats = array(
            'active_subscriptions' => 0,
            'total_spent' => 0,
            'total_payments' => count($payments),
            'monthly_total' => 0
        );

        // アクティブなサブスクリプションIDのリストを作成
        $active_subscription_ids = array();
        foreach ($subscriptions as $subscription) {
            if (isset($subscription['status']) && $subscription['status'] === 'ACTIVE') {
                $stats['active_subscriptions']++;
                $subscription_id = is_object($subscription) ? $subscription->subscription_id : $subscription['subscription_id'];
                $active_subscription_ids[] = $subscription_id;
            }
        }

        // 決済統計
        $current_month = date('Y-m');
        foreach ($payments as $payment) {
            // 総決済金額：全決済の合計
            $stats['total_spent'] += $payment['amount'];

            // 今月の支払い：今月かつアクティブなサブスクリプションの決済のみ
            if (date('Y-m', strtotime($payment['created_at'])) === $current_month) {
                // payment_typeまたはsubscription_idで判定
                $is_active_subscription = false;

                // サブスクリプション決済の場合
                if (isset($payment['payment_type']) && $payment['payment_type'] === 'subscription') {
                    $payment_subscription_id = $payment['subscription_id'] ?? '';
                    $is_active_subscription = in_array($payment_subscription_id, $active_subscription_ids);
                } else {
                    // 一般決済の場合は今月の支払いに含める
                    $is_active_subscription = true;
                }

                // または、subscription_idが存在する場合はサブスクリプション決済として判定
                if (!isset($payment['payment_type']) && isset($payment['subscription_id'])) {
                    $payment_subscription_id = $payment['subscription_id'];
                    $is_active_subscription = in_array($payment_subscription_id, $active_subscription_ids);
                }

                if ($is_active_subscription) {
                    $stats['monthly_total'] += $payment['amount'];
                }
            }
        }

        return $stats;
    }

    /**
     * ステータスメッセージの表示
     */
    private function render_status_messages() {
        // メッセージ表示機能
        if (isset($_GET['result']) && $_GET['result'] === 'cancel_success') {
            echo '<div class="edel-square-message success">サブスクリプションのキャンセルが完了しました。</div>';
        } elseif (isset($_GET['result']) && $_GET['result'] === 'card_updated') {
            echo '<div class="edel-square-message success">支払い方法が正常に更新されました。</div>';
        } elseif (isset($_GET['error'])) {
            $error_messages = array(
                'not_logged_in' => 'ログインが必要です。',
                'no_subscription_id' => 'サブスクリプションIDが指定されていません。',
                'subscription_not_found' => 'サブスクリプションが見つかりません。',
                'permission_denied' => 'このサブスクリプションにアクセスする権限がありません。',
                'card_update_failed' => '支払い方法の更新に失敗しました。'
            );

            $error_code = sanitize_text_field($_GET['error']);
            $error_message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'エラーが発生しました。';
            echo '<div class="edel-square-message error">' . esc_html($error_message) . '</div>';
        }
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

            // サブスクリプションキャンセル完了フック
            do_action(
                'edel_square_subscription_cancelled',
                $subscription->user_id,      // ユーザーID
                $subscription_id,            // サブスクリプションID
                $subscription->plan_id       // プランID
            );
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
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-license-manager.php';
        if (!EdelSquarePaymentProLicense::is_license_valid()) {
            return;
        }

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

        // 設定の読み込み
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

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
                    <?php if ($is_logged_in): ?>
                        <input type="email" id="edel-square-sub-email" name="email" value="<?php echo esc_attr($user_email); ?>" readonly class="readonly-field">
                        <input type="hidden" id="edel-square-sub-email-hidden" name="email_hidden" value="<?php echo esc_attr($user_email); ?>">
                    <?php else: ?>
                        <input type="email" id="edel-square-sub-email" name="email" required>
                    <?php endif; ?>
                </div>

                <div class="edel-square-form-group">
                    <label for="card-container">クレジットカード情報</label>
                    <div id="card-container"></div>
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
     * 新規ユーザーにログイン情報をメールで通知（フィルターフック対応版）
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

        // メール送信用のデータを準備
        $email_data = array(
            'user_id' => $user_id,
            'user' => $user,
            'password' => $password,
            'site_name' => $site_name,
            'site_url' => $site_url,
            'login_url' => $login_url,
            'settings' => $settings
        );

        // デフォルトの件名
        $default_subject = sprintf(__('[%s] アカウント登録完了のお知らせ', 'edel-square-payment-pro'), $site_name);

        // デフォルトの本文
        $default_message = sprintf(
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

        // デフォルトのヘッダー
        $default_headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );

        /**
         * 新規ユーザー登録完了メールの件名をフィルタリング
         *
         * @param string $subject メールの件名
         * @param array $email_data メール送信用データ
         * @return string フィルタリング後の件名
         */
        $subject = apply_filters('edel_square_new_user_email_subject', $default_subject, $email_data);

        /**
         * 新規ユーザー登録完了メールの本文をフィルタリング
         *
         * @param string $message メールの本文
         * @param array $email_data メール送信用データ
         * @return string フィルタリング後の本文
         */
        $message = apply_filters('edel_square_new_user_email_message', $default_message, $email_data);

        /**
         * 新規ユーザー登録完了メールのヘッダーをフィルタリング
         *
         * @param array $headers メールのヘッダー
         * @param array $email_data メール送信用データ
         * @return array フィルタリング後のヘッダー
         */
        $headers = apply_filters('edel_square_new_user_email_headers', $default_headers, $email_data);

        /**
         * 新規ユーザー登録完了メール送信前の最終フィルタリング
         * メール送信を無効化したい場合は false を返す
         *
         * @param bool $send_email メール送信するかどうか
         * @param array $email_data メール送信用データ
         * @return bool メール送信するかどうか
         */
        $send_email = apply_filters('edel_square_send_new_user_email', true, $email_data);

        // メール送信が無効化されている場合
        if (!$send_email) {
            error_log('Square Payment Pro - 新規ユーザーメール送信がフィルターで無効化されました: ' . $user->user_email);
            return true; // フィルターで意図的に無効化された場合は成功として扱う
        }

        // メール送信
        $mail_sent = wp_mail($user->user_email, $subject, $message, $headers);

        /**
         * 新規ユーザー登録完了メール送信後のアクション
         *
         * @param bool $mail_sent メール送信結果
         * @param array $email_data メール送信用データ
         * @param string $subject 送信された件名
         * @param string $message 送信された本文
         */
        do_action('edel_square_after_new_user_email_sent', $mail_sent, $email_data, $subject, $message);

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

            do_action(
                'edel_square_subscription_completed',
                $user_id,                    // ユーザーID
                $subscription_data,          // サブスクリプションデータ (変数名要確認)
                $plan_id,                    // プランID (変数名要確認)
                $response                    // レスポンス情報
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
     * フォーム送信処理（マイアカウントページ内）
     */
    public function handle_form_submissions() {
        // POSTデータが送信されていない場合は処理しない
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // キャンセル処理
        if (isset($_POST['action']) && $_POST['action'] === 'edel_square_cancel_subscription') {
            $this->process_subscription_cancellation();
        }

        // パスワード変更処理
        if (isset($_POST['action']) && $_POST['action'] === 'edel_square_change_password') {
            error_log("change password fired. " . implode(",", $_POST));
            $this->process_password_change();
        }
    }

    /**
     * サブスクリプションキャンセル処理
     */
    private function process_subscription_cancellation() {
        // ログイン確認
        if (!is_user_logged_in()) {
            wp_redirect(add_query_arg('error', 'not_logged_in', wp_get_referer()));
            exit;
        }

        // POSTデータ確認
        if (!isset($_POST['subscription_id']) || !isset($_POST['cancel_nonce'])) {
            wp_redirect(add_query_arg('error', 'invalid_request', wp_get_referer()));
            exit;
        }

        $subscription_id = sanitize_text_field($_POST['subscription_id']);

        // nonce確認
        if (!wp_verify_nonce($_POST['cancel_nonce'], 'cancel_subscription_' . $subscription_id)) {
            wp_redirect(add_query_arg('error', 'security_check_failed', wp_get_referer()));
            exit;
        }

        error_log('Edel Square Payment Pro: キャンセル処理開始 - サブスクリプションID: ' . $subscription_id);

        // サブスクリプション情報を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

        if (!$subscription) {
            error_log('Edel Square Payment Pro: サブスクリプションが見つかりません: ' . $subscription_id);
            wp_redirect(add_query_arg('error', 'subscription_not_found', wp_get_referer()));
            exit;
        }

        // ユーザー権限確認
        $user_id = get_current_user_id();
        if ($subscription->user_id != $user_id && !current_user_can('manage_options')) {
            error_log('Edel Square Payment Pro: 権限エラー - ユーザーID: ' . $user_id . ', サブスクリプション所有者: ' . $subscription->user_id);
            wp_redirect(add_query_arg('error', 'permission_denied', wp_get_referer()));
            exit;
        }

        // キャンセル処理実行
        $now = current_time('mysql');
        $result = EdelSquarePaymentProDB::update_subscription($subscription_id, array(
            'status' => 'CANCELED',
            'cancel_at' => $now,
            'updated_at' => $now
        ));

        if ($result) {
            error_log('Edel Square Payment Pro: キャンセル処理成功 - サブスクリプションID: ' . $subscription_id);

            // メール通知
            $this->send_subscription_cancel_email($subscription_id);

            // 成功リダイレクト
            wp_redirect(add_query_arg('success', 'subscription_cancelled', wp_get_referer()));
            exit;
        } else {
            error_log('Edel Square Payment Pro: キャンセル処理失敗 - サブスクリプションID: ' . $subscription_id);
            wp_redirect(add_query_arg('error', 'cancellation_failed', wp_get_referer()));
            exit;
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
