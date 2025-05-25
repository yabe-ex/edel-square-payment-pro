<?php

/**
 * サブスクリプション管理クラス
 */
class EdelSquarePaymentProSubscriptions {
    /**
     * コンストラクタ
     */
    public function __construct() {
        // AJAXハンドラー
        add_action('wp_ajax_edel_square_process_subscription', array($this, 'process_subscription'));
        add_action('wp_ajax_nopriv_edel_square_process_subscription', array($this, 'process_subscription'));

        add_action('wp_ajax_edel_square_cancel_subscription', array($this, 'cancel_subscription'));
        add_action('admin_post_edel_square_cancel_subscription', array($this, 'cancel_subscription'));

        // 定期実行アクション
        add_action('edel_square_subscription_scheduler', array($this, 'process_scheduled_payments'));
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

            // ユーザー登録/ログイン処理
            $user_id = $this->process_user_registration($email);

            if (!$user_id) {
                throw new Exception('ユーザー登録に失敗しました。');
            }

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "ユーザーID: " . $user_id . "\n",
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
            $card_saved = false;

            // トライアル期間がある場合とない場合で異なる処理
            if ($has_trial) {
                // トライアル期間がある場合のみカード作成を試みる
                try {
                    error_log('サブスクリプション処理 - トライアル期間あり、カード作成を試行');
                    $card_result = $square_api->create_card(
                        $customer_id,
                        $payment_token,
                        ($user->first_name ?? '') . ' ' . ($user->last_name ?? '')
                    );

                    if (is_object($card_result) && method_exists($card_result, 'getId')) {
                        $card_data['card_id'] = $card_result->getId();
                        $card_data['card_brand'] = method_exists($card_result, 'getCardBrand') ? $card_result->getCardBrand() : '';
                        $card_data['last_4'] = method_exists($card_result, 'getLast4') ? $card_result->getLast4() : '';
                        $card_data['exp_month'] = method_exists($card_result, 'getExpMonth') ? $card_result->getExpMonth() : '';
                        $card_data['exp_year'] = method_exists($card_result, 'getExpYear') ? $card_result->getExpYear() : '';
                        $card_saved = true;
                        error_log('サブスクリプション処理 - カード作成成功: ID=' . $card_data['card_id']);
                    } elseif (is_array($card_result) && isset($card_result['id'])) {
                        $card_data['card_id'] = $card_result['id'];
                        $card_data['card_brand'] = isset($card_result['card_brand']) ? $card_result['card_brand'] : '';
                        $card_data['last_4'] = isset($card_result['last_4']) ? $card_result['last_4'] : '';
                        $card_data['exp_month'] = isset($card_result['exp_month']) ? $card_result['exp_month'] : '';
                        $card_data['exp_year'] = isset($card_result['exp_year']) ? $card_result['exp_year'] : '';
                        $card_saved = true;
                        error_log('サブスクリプション処理 - カード作成成功: ID=' . $card_data['card_id']);
                    } elseif (is_string($card_result)) {
                        $card_data['card_id'] = $card_result;
                        $card_saved = true;
                        error_log('サブスクリプション処理 - カード作成成功: ID=' . $card_data['card_id']);
                    }

                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "カード保存結果: " . json_encode($card_data) . "\n",
                        FILE_APPEND
                    );
                } catch (Exception $e) {
                    error_log('サブスクリプション処理 - カード作成エラー: ' . $e->getMessage());
                    // エラーが発生しても処理は継続
                }

                // カード作成に失敗した場合、顧客の既存カードを確認
                if (!$card_saved) {
                    try {
                        error_log('サブスクリプション処理 - 顧客カード取得を試行');
                        $cards = $square_api->get_customer_cards($customer_id);

                        if (is_array($cards) && !empty($cards)) {
                            foreach ($cards as $card) {
                                if (is_object($card) && method_exists($card, 'getId')) {
                                    $card_data['card_id'] = $card->getId();
                                    $card_data['card_brand'] = method_exists($card, 'getCardBrand') ? $card->getCardBrand() : '';
                                    $card_data['last_4'] = method_exists($card, 'getLast4') ? $card->getLast4() : '';
                                    $card_data['exp_month'] = method_exists($card, 'getExpMonth') ? $card->getExpMonth() : '';
                                    $card_data['exp_year'] = method_exists($card, 'getExpYear') ? $card->getExpYear() : '';
                                    $card_saved = true;
                                    error_log('サブスクリプション処理 - 顧客カードから取得: ' . $card_data['card_id']);
                                    break;
                                } elseif (is_array($card) && isset($card['id'])) {
                                    $card_data['card_id'] = $card['id'];
                                    $card_data['card_brand'] = isset($card['card_brand']) ? $card['card_brand'] : '';
                                    $card_data['last_4'] = isset($card['last_4']) ? $card['last_4'] : '';
                                    $card_data['exp_month'] = isset($card['exp_month']) ? $card['exp_month'] : '';
                                    $card_data['exp_year'] = isset($card['exp_year']) ? $card['exp_year'] : '';
                                    $card_saved = true;
                                    error_log('サブスクリプション処理 - 顧客カードから取得: ' . $card_data['card_id']);
                                    break;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('サブスクリプション処理 - 顧客カード取得エラー: ' . $e->getMessage());
                    }
                }
            } else {
                // トライアルなしの場合は直接決済を行う
                error_log('サブスクリプション処理 - トライアルなし、決済処理を実行');
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

                    // カード情報の取得
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

                            if (!empty($card_data['card_id'])) {
                                $card_saved = true;
                                error_log('サブスクリプション処理 - 決済結果からカードID取得: ' . $card_data['card_id']);
                            }
                        }
                    } catch (Exception $e) {
                        error_log('サブスクリプション処理 - 決済結果からのカード情報取得エラー: ' . $e->getMessage());
                    }
                } elseif (is_array($payment_result)) {
                    $payment_id = isset($payment_result['id']) ? $payment_result['id'] : '';

                    // カード情報の取得
                    if (isset($payment_result['card_details']) && isset($payment_result['card_details']['card'])) {
                        $card = $payment_result['card_details']['card'];
                        $card_data['card_id'] = isset($card['id']) ? $card['id'] : '';
                        $card_data['card_brand'] = isset($card['card_brand']) ? $card['card_brand'] : '';
                        $card_data['last_4'] = isset($card['last_4']) ? $card['last_4'] : '';
                        $card_data['exp_month'] = isset($card['exp_month']) ? $card['exp_month'] : '';
                        $card_data['exp_year'] = isset($card['exp_year']) ? $card['exp_year'] : '';

                        if (!empty($card_data['card_id'])) {
                            $card_saved = true;
                            error_log('サブスクリプション処理 - 決済結果からカードID取得: ' . $card_data['card_id']);
                        }
                    }
                }

                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "初回決済結果: " . (is_string($payment_result) ? $payment_result : json_encode($payment_result)) . "\n",
                    FILE_APPEND
                );

                // 決済後にカードIDが取得できなかった場合、顧客の既存カードを確認
                if (!$card_saved && empty($card_data['card_id'])) {
                    try {
                        error_log('サブスクリプション処理 - 決済後にカードIDがないため顧客カード取得を試行');
                        $cards = $square_api->get_customer_cards($customer_id);

                        if (is_array($cards) && !empty($cards)) {
                            foreach ($cards as $card) {
                                if (is_object($card) && method_exists($card, 'getId')) {
                                    $card_data['card_id'] = $card->getId();
                                    $card_data['card_brand'] = method_exists($card, 'getCardBrand') ? $card->getCardBrand() : '';
                                    $card_data['last_4'] = method_exists($card, 'getLast4') ? $card->getLast4() : '';
                                    $card_data['exp_month'] = method_exists($card, 'getExpMonth') ? $card->getExpMonth() : '';
                                    $card_data['exp_year'] = method_exists($card, 'getExpYear') ? $card->getExpYear() : '';
                                    $card_saved = true;
                                    error_log('サブスクリプション処理 - 顧客カードから取得: ' . $card_data['card_id']);
                                    break;
                                } elseif (is_array($card) && isset($card['id'])) {
                                    $card_data['card_id'] = $card['id'];
                                    $card_data['card_brand'] = isset($card['card_brand']) ? $card['card_brand'] : '';
                                    $card_data['last_4'] = isset($card['last_4']) ? $card['last_4'] : '';
                                    $card_data['exp_month'] = isset($card['exp_month']) ? $card['exp_month'] : '';
                                    $card_data['exp_year'] = isset($card['exp_year']) ? $card['exp_year'] : '';
                                    $card_saved = true;
                                    error_log('サブスクリプション処理 - 顧客カードから取得: ' . $card_data['card_id']);
                                    break;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('サブスクリプション処理 - 顧客カード取得エラー: ' . $e->getMessage());
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

            // メール通知
            $this->send_subscription_notification_emails($subscription_id, $email, $plan, $user_id);

            // 成功メッセージを取得
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();

            // マイアカウントページのURL
            $myaccount_url = '';
            if (!empty($settings['myaccount_page'])) {
                $myaccount_url = get_permalink((int)$settings['myaccount_page']);
            }

            // リダイレクト先URLがなければホームURLを使用
            if (empty($myaccount_url)) {
                $myaccount_url = home_url();
            }

            $response = array(
                'success' => true,
                'message' => nl2br(esc_html($settings['subscription_success_message'] ?? 'サブスクリプションの登録が完了しました。')),
                'subscription_id' => $subscription_id,
                'redirect_url' => $myaccount_url,
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
     * ユーザー登録・ログイン処理
     */
    private function process_user_registration($email) {
        file_put_contents(
            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
            "ユーザー登録処理開始: メール={$email}\n",
            FILE_APPEND
        );

        // すでにログイン中なら現在のユーザーIDを返す
        if (is_user_logged_in()) {
            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "既にログイン中: ユーザーID=" . get_current_user_id() . "\n",
                FILE_APPEND
            );
            return get_current_user_id();
        }

        // メールアドレスでユーザーを検索
        $user = get_user_by('email', $email);

        if ($user) {
            // 既存ユーザーならログイン
            $user_id = $user->ID;
            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "既存ユーザー: ID={$user_id}\n",
                FILE_APPEND
            );
        } else {
            // 新規ユーザー登録
            $username = $this->generate_username_from_email($email);
            $password = wp_generate_password(12, true, true);

            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "ユーザー作成エラー: " . $user_id->get_error_message() . "\n",
                    FILE_APPEND
                );
                return false;
            }

            // ユーザーロールを設定
            $user = new WP_User($user_id);
            $user->set_role('subscriber');

            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "新規ユーザー作成: ID={$user_id}, メール={$email}\n",
                FILE_APPEND
            );

            // ログイン情報メールを送信
            try {
                if (class_exists('EdelSquarePaymentProShortcodes')) {
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "ログイン情報メール送信開始: ID={$user_id}, パスワード=" . substr($password, 0, 3) . "***\n",
                        FILE_APPEND
                    );

                    $mail_result = EdelSquarePaymentProShortcodes::send_new_user_notification($user_id, $password);

                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "ログイン情報メール送信結果: " . ($mail_result ? '成功' : '失敗') . "\n",
                        FILE_APPEND
                    );
                } else {
                    file_put_contents(
                        EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                        "ログイン情報メール送信エラー: EdelSquarePaymentProShortcodesクラスが見つかりません\n",
                        FILE_APPEND
                    );
                }
            } catch (Exception $e) {
                file_put_contents(
                    EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                    "ログイン情報メール送信例外: " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
                // メール送信エラーでも処理を続行
            }
        }

        // ユーザーを自動ログイン
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return $user_id;
    }

    /**
     * メールアドレスからユーザー名を生成
     */
    private function generate_username_from_email($email) {
        $username = sanitize_user(current(explode('@', $email)), true);

        // ユーザー名の重複をチェック
        $suffix = 1;
        $original_username = $username;

        while (username_exists($username)) {
            $username = $original_username . $suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * サブスクリプション通知メールを送信
     */
    private function send_subscription_notification_emails($subscription_id, $email, $plan, $user_id) {
        file_put_contents(
            EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
            "メール通知処理開始: サブスクリプションID={$subscription_id}, メール={$email}\n",
            FILE_APPEND
        );

        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        // 共通の置換データ
        $user = get_userdata($user_id);

        // 請求周期のテキスト
        $cycle_text = '';
        switch ($plan['billing_cycle']) {
            case 'DAILY':
                $cycle_text = $plan['billing_interval'] > 1 ? $plan['billing_interval'] . '日ごと' : '毎日';
                break;
            case 'WEEKLY':
                $cycle_text = $plan['billing_interval'] > 1 ? $plan['billing_interval'] . '週間ごと' : '毎週';
                break;
            case 'YEARLY':
                $cycle_text = $plan['billing_interval'] > 1 ? $plan['billing_interval'] . '年ごと' : '毎年';
                break;
            case 'MONTHLY':
            default:
                $cycle_text = $plan['billing_interval'] > 1 ? $plan['billing_interval'] . 'ヶ月ごと' : '毎月';
                break;
        }

        $replace_data = array(
            'subscription_id' => $subscription_id,
            'customer_email' => $email,
            'amount' => $plan['amount'],
            'item_name' => $plan['name'],
            'billing_cycle' => $cycle_text,
            'transaction_date' => current_time('mysql', false),
            'user_id' => $user_id,
            'user_name' => $user ? $user->display_name : '',
            'plan_id' => $plan['plan_id'],
            'trial_days' => isset($plan['trial_period_days']) ? $plan['trial_period_days'] : 0,
        );

        // 管理者向け通知メール
        if (!empty($settings['admin_email'])) {
            $admin_subject = isset($settings['subscription_admin_email_subject']) ?
                EdelSquarePaymentProSettings::replace_placeholders($settings['subscription_admin_email_subject'], $replace_data) :
                '【' . get_bloginfo('name') . '】新しいサブスクリプション登録がありました';

            $admin_body = isset($settings['subscription_admin_email_body']) ?
                EdelSquarePaymentProSettings::replace_placeholders($settings['subscription_admin_email_body'], $replace_data) :
                "以下の内容でサブスクリプション登録がありました。\n\nプラン名: {$plan['name']}\n金額: {$plan['amount']}円\n課金周期: {$cycle_text}\n購入者メール: {$email}\nサブスクリプションID: {$subscription_id}\n日時: " . current_time('mysql', false);

            $admin_headers = array(
                'From: ' . $settings['sender_name'] . ' <' . $settings['sender_email'] . '>',
                'Content-Type: text/plain; charset=UTF-8',
            );

            $mail_result = wp_mail($settings['admin_email'], $admin_subject, $admin_body, $admin_headers);
            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "管理者向けメール送信結果: " . ($mail_result ? '成功' : '失敗') . "\n",
                FILE_APPEND
            );
        }

        // 購入者向け通知メール
        if (!empty($email)) {
            $customer_subject = isset($settings['subscription_customer_email_subject']) ?
                EdelSquarePaymentProSettings::replace_placeholders($settings['subscription_customer_email_subject'], $replace_data) :
                '【' . get_bloginfo('name') . '】サブスクリプション登録ありがとうございます';

            $customer_body = isset($settings['subscription_customer_email_body']) ?
                EdelSquarePaymentProSettings::replace_placeholders($settings['subscription_customer_email_body'], $replace_data) : ($user ? $user->display_name : '') . " 様\n\nサブスクリプションへのご登録ありがとうございます。以下の内容で登録されました。\n\nプラン名: {$plan['name']}\n金額: {$plan['amount']}円\n課金周期: {$cycle_text}\nサブスクリプションID: {$subscription_id}\n日時: " . current_time('mysql', false) . "\n\nマイアカウントページからもご確認いただけます。\n" . home_url();

            $customer_headers = array(
                'From: ' . $settings['sender_name'] . ' <' . $settings['sender_email'] . '>',
                'Content-Type: text/plain; charset=UTF-8',
            );

            $mail_result = wp_mail($email, $customer_subject, $customer_body, $customer_headers);
            file_put_contents(
                EDEL_SQUARE_PAYMENT_PRO_PATH . '/subscription-debug.log',
                "購入者向けメール送信結果: " . ($mail_result ? '成功' : '失敗') . "\n",
                FILE_APPEND
            );
        }
    }

    public function cancel_subscription() {
        // セキュリティチェック
        check_admin_referer('cancel_subscription_' . $_POST['subscription_id'], 'cancel_nonce');

        // ログインチェック
        if (!is_user_logged_in()) {
            wp_redirect(add_query_arg('error', 'not_logged_in', home_url('/my-account/')));
            exit;
        }

        $subscription_id = isset($_POST['subscription_id']) ? sanitize_text_field($_POST['subscription_id']) : '';

        error_log('Edel Square Payment Pro: キャンセル処理開始 - サブスクリプションID: ' . $subscription_id);

        if (empty($subscription_id)) {
            wp_redirect(add_query_arg('error', 'no_subscription_id', home_url('/my-account/')));
            exit;
        }

        // サブスクリプション情報を取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

        if (!$subscription) {
            wp_redirect(add_query_arg('error', 'subscription_not_found', home_url('/my-account/')));
            exit;
        }

        // ユーザー権限チェック - オブジェクトとして扱う
        $user_id = get_current_user_id();
        if ($subscription->user_id != $user_id && !current_user_can('manage_options')) {
            wp_redirect(add_query_arg('error', 'permission_denied', home_url('/my-account/')));
            exit;
        }

        $now = current_time('mysql', false);
        $update_data = array(
            'subscription_id' => $subscription_id,
            'updated_at' => $now,
            'status' => 'CANCELING',
            'cancel_at' => $subscription->current_period_end
        );

        // サブスクリプション情報を更新
        if (EdelSquarePaymentProDB::save_subscription($update_data)) {
            // メール通知
            $this->send_subscription_cancellation_email($subscription_id);
            $message = 'cancel_success';
        } else {
            $message = 'update_failed';
        }

        // クエリパラメータを使ってメッセージを渡す
        // 注意: マイアカウントページのURLを適切に指定する必要があります
        $myaccount_page_id = get_option('edel_square_myaccount_page');
        if ($myaccount_page_id) {
            $redirect_url = get_permalink($myaccount_page_id);
        } else {
            $redirect_url = home_url('/my-account/');
        }

        wp_redirect(add_query_arg('result', $message, $redirect_url));
        exit;
    }

    public function send_subscription_cancellation_email($subscription_id) {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        $subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

        if (!$subscription) {
            error_log('Edel Square Payment Pro: キャンセルメール送信失敗 - サブスクリプションが見つかりません: ' . $subscription_id);
            return false;
        }

        // ユーザー情報を取得
        $user = get_user_by('id', $subscription->user_id);
        if (!$user) {
            error_log('Edel Square Payment Pro: キャンセルメール送信失敗 - ユーザーが見つかりません: ' . $subscription->user_id);
            return false;
        }

        // プラン情報を取得
        $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);
        $plan_name = $plan ? $plan['name'] : '不明なプラン';

        // 管理者メールアドレス
        $admin_email = get_option('admin_email');

        // メール件名
        $subject = 'サブスクリプションがキャンセルされました';

        // メール本文
        $message = sprintf(
            "こんにちは、%sさん\n\n" .
                "以下のサブスクリプションがキャンセルされました：\n\n" .
                "プラン: %s\n" .
                "金額: %s %s\n" .
                "キャンセル日: %s\n\n" .
                "ご質問がありましたら、お気軽にお問い合わせください。\n\n" .
                "ありがとうございました。",
            $user->display_name,
            $plan_name,
            number_format($subscription->amount),
            $subscription->currency,
            date_i18n('Y年m月d日', strtotime('now'))
        );

        // ヘッダー
        $headers = array(
            'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>',
            'Content-Type: text/plain; charset=UTF-8'
        );

        // メール送信
        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if ($sent) {
            error_log('Edel Square Payment Pro: キャンセルメール送信成功 - サブスクリプションID: ' . $subscription_id);
        } else {
            error_log('Edel Square Payment Pro: キャンセルメール送信失敗 - サブスクリプションID: ' . $subscription_id);
        }

        return $sent;
    }

    /**
     * 予定されたサブスクリプション決済を処理する
     */
    public function process_scheduled_payments() {
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-square-api.php';

        $now = current_time('mysql', false);

        error_log('Edel Square Payment Pro: 予定された決済処理を開始 - ' . $now);

        // 請求対象のサブスクリプションを取得
        $subscriptions = EdelSquarePaymentProDB::get_due_subscriptions($now);

        if (empty($subscriptions)) {
            error_log('Edel Square Payment Pro: 請求予定のサブスクリプションはありません。');
            return;
        }

        error_log('Edel Square Payment Pro: 処理するサブスクリプション数: ' . count($subscriptions));

        $square_api = new EdelSquarePaymentProAPI();

        foreach ($subscriptions as $subscription) {
            // サブスクリプションIDのログ
            error_log('Edel Square Payment Pro: サブスクリプション処理中 - ID: ' . $subscription->subscription_id);

            // ステータスチェック
            if ($subscription->status !== 'ACTIVE') {
                error_log('Edel Square Payment Pro: サブスクリプションがアクティブではありません - ' . $subscription->status);
                continue;
            }

            // カードIDチェックとリカバリー処理
            $card_id = !empty($subscription->card_id) ? $subscription->card_id : '';

            if (empty($card_id)) {
                error_log('Edel Square Payment Pro: カードIDが見つかりません。サブスクリプションID: ' . $subscription->subscription_id);

                // カードIDがない場合はリカバリーを試みる
                $metadata = json_decode($subscription->metadata, true);
                if (!is_array($metadata)) {
                    $metadata = array();
                }

                $card_recovered = false;

                // 1. まずメタデータからペイメントトークンを使ってカードを作成
                if (!empty($metadata['payment_token']) && !empty($subscription->customer_id)) {
                    try {
                        error_log('Edel Square Payment Pro: メタデータのトークンでカード作成を試行');

                        // ユーザー情報を取得
                        $user = get_userdata($subscription->user_id);
                        $user_name = ($user && isset($user->display_name)) ? $user->display_name : 'サブスクリプション';

                        $card_result = $square_api->create_card(
                            $subscription->customer_id,
                            $metadata['payment_token'],
                            $user_name
                        );

                        if (is_object($card_result) && method_exists($card_result, 'getId')) {
                            $card_id = $card_result->getId();
                            error_log('Edel Square Payment Pro: トークンからカードID取得成功: ' . $card_id);
                            $card_recovered = true;
                        } elseif (is_array($card_result) && isset($card_result['id'])) {
                            $card_id = $card_result['id'];
                            error_log('Edel Square Payment Pro: トークンからカードID取得成功: ' . $card_id);
                            $card_recovered = true;
                        } elseif (is_string($card_result)) {
                            $card_id = $card_result;
                            error_log('Edel Square Payment Pro: トークンからカードID取得成功: ' . $card_id);
                            $card_recovered = true;
                        }
                    } catch (Exception $e) {
                        error_log('Edel Square Payment Pro: トークンからのカード作成エラー: ' . $e->getMessage());
                    }
                }

                // 2. 顧客IDから登録済みカードを取得
                if (!$card_recovered && !empty($subscription->customer_id)) {
                    try {
                        error_log('Edel Square Payment Pro: 顧客カード取得を試行');
                        $cards = $square_api->get_customer_cards($subscription->customer_id);

                        if (is_array($cards) && !empty($cards)) {
                            foreach ($cards as $card) {
                                if (is_object($card) && method_exists($card, 'getId')) {
                                    $card_id = $card->getId();

                                    // カード詳細も更新
                                    $metadata['card_brand'] = method_exists($card, 'getCardBrand') ? $card->getCardBrand() : '';
                                    $metadata['last_4'] = method_exists($card, 'getLast4') ? $card->getLast4() : '';
                                    $metadata['exp_month'] = method_exists($card, 'getExpMonth') ? $card->getExpMonth() : '';
                                    $metadata['exp_year'] = method_exists($card, 'getExpYear') ? $card->getExpYear() : '';

                                    error_log('Edel Square Payment Pro: 顧客から新しいカードID取得: ' . $card_id);
                                    $card_recovered = true;
                                    break;
                                } elseif (is_array($card) && isset($card['id'])) {
                                    $card_id = $card['id'];

                                    // カード詳細も更新
                                    $metadata['card_brand'] = isset($card['card_brand']) ? $card['card_brand'] : '';
                                    $metadata['last_4'] = isset($card['last_4']) ? $card['last_4'] : '';
                                    $metadata['exp_month'] = isset($card['exp_month']) ? $card['exp_month'] : '';
                                    $metadata['exp_year'] = isset($card['exp_year']) ? $card['exp_year'] : '';

                                    error_log('Edel Square Payment Pro: 顧客から新しいカードID取得: ' . $card_id);
                                    $card_recovered = true;
                                    break;
                                }
                            }
                        } else {
                            error_log('Edel Square Payment Pro: 顧客カードが見つかりません');
                        }
                    } catch (Exception $e) {
                        error_log('Edel Square Payment Pro: 顧客カード取得エラー: ' . $e->getMessage());
                    }
                }

                // カードID取得成功時にサブスクリプション更新
                if ($card_recovered && !empty($card_id)) {
                    $update_data = array(
                        'card_id' => $card_id,
                        'metadata' => json_encode($metadata),
                        'updated_at' => $now
                    );

                    $update_result = EdelSquarePaymentProDB::update_subscription(
                        $subscription->subscription_id,
                        $update_data
                    );

                    error_log('Edel Square Payment Pro: サブスクリプション更新結果: ' . ($update_result ? '成功' : '失敗'));

                    // 更新されたサブスクリプション情報を再取得
                    $subscription = EdelSquarePaymentProDB::get_subscription($subscription->subscription_id);
                }
            }

            // カードIDがあるかを再確認
            if (empty($subscription->card_id)) {
                // カードIDがない場合は通知して次へ
                $this->send_card_missing_email($subscription->subscription_id);
                continue;
            }

            // プラン情報を取得
            $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);

            if (!$plan) {
                error_log('Edel Square Payment Pro: プラン情報が見つかりません。サブスクリプションID: ' . $subscription->subscription_id);
                continue;
            }

            try {
                // 支払い処理 - カードIDを使用
                $payment_result = $square_api->charge_card_id(
                    $subscription->customer_id,
                    $subscription->card_id,
                    $subscription->amount,
                    $subscription->currency,
                    'サブスクリプション定期支払い: ' . $plan['name'],
                    array('subscription_id' => $subscription->subscription_id)
                );

                if (isset($payment_result['success']) && $payment_result['success']) {
                    // 次回請求日を更新
                    $next_billing_date = new DateTime($subscription->current_period_end);
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

                    $current_period_start = $subscription->current_period_end;
                    $next_billing_date->modify($interval);
                    $current_period_end = $next_billing_date->format('Y-m-d H:i:s');

                    // サブスクリプション情報を更新
                    EdelSquarePaymentProDB::update_subscription(
                        $subscription->subscription_id,
                        array(
                            'current_period_start' => $current_period_start,
                            'current_period_end' => $current_period_end,
                            'next_billing_date' => $current_period_end,
                            'updated_at' => $now
                        )
                    );

                    // 支払い履歴を保存
                    EdelSquarePaymentProDB::save_subscription_payment(array(
                        'subscription_id' => $subscription->subscription_id,
                        'payment_id' => $payment_result['payment_id'],
                        'amount' => $subscription->amount,
                        'currency' => $subscription->currency,
                        'status' => 'SUCCESS',
                        'created_at' => $now,
                        'billing_period_start' => $current_period_start,
                        'billing_period_end' => $current_period_end
                    ));

                    // 支払い成功通知メール
                    $this->send_payment_success_email($subscription->subscription_id, $payment_result['payment_id']);

                    error_log('Edel Square Payment Pro: 定期支払い成功: サブスクリプションID: ' . $subscription->subscription_id . ', 決済ID: ' . $payment_result['payment_id']);
                } else {
                    // 支払い失敗の処理
                    $error_message = isset($payment_result['error']) ? $payment_result['error'] : '不明なエラー';
                    error_log('Edel Square Payment Pro: 定期支払い失敗: サブスクリプションID: ' . $subscription->subscription_id . ', エラー: ' . $error_message);

                    // メタデータを取得
                    $metadata = json_decode($subscription->metadata, true);
                    if (!is_array($metadata)) {
                        $metadata = array();
                    }

                    // 支払い失敗を記録
                    if (!isset($metadata['payment_failures'])) {
                        $metadata['payment_failures'] = 0;
                    }

                    $metadata['payment_failures']++;
                    $metadata['last_failure_date'] = $now;
                    $metadata['last_failure_message'] = $error_message;

                    // 3回連続で失敗した場合はサブスクリプションを一時停止
                    if ($metadata['payment_failures'] >= 3) {
                        EdelSquarePaymentProDB::update_subscription(
                            $subscription->subscription_id,
                            array(
                                'status' => 'PAUSED',
                                'metadata' => json_encode($metadata),
                                'updated_at' => $now
                            )
                        );

                        // 一時停止通知メール
                        $this->send_subscription_pause_email($subscription->subscription_id);
                    } else {
                        // メタデータのみ更新
                        EdelSquarePaymentProDB::update_subscription(
                            $subscription->subscription_id,
                            array(
                                'metadata' => json_encode($metadata),
                                'updated_at' => $now
                            )
                        );

                        // 支払い失敗通知メール
                        $this->send_payment_failure_email($subscription->subscription_id, $error_message);
                    }
                }
            } catch (Exception $e) {
                error_log('Edel Square Payment Pro: 決済処理中にエラーが発生しました: ' . $e->getMessage());
            }
        }
    }

    /**
     * カード情報不足通知メールを送信
     *
     * @param string $subscription_id サブスクリプションID
     * @return bool 送信成功時はtrue、失敗時はfalse
     */
    private function send_card_missing_email($subscription_id) {
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
        $subject = get_bloginfo('name') . ' - サブスクリプションが一時停止されました（カード情報不足）';

        $message = sprintf(
            "こんにちは、%s様\n\n" .
                "あなたの %s のサブスクリプションが一時停止されました。\n\n" .
                "サブスクリプション情報:\n" .
                "- プラン: %s\n" .
                "- 金額: %s %s\n" .
                "- サブスクリプションID: %s\n\n" .
                "これは、お支払いカード情報が不足しているために発生しました。サブスクリプションを再開するには、有効なクレジットカード情報の登録が必要です。\n\n" .
                "以下のリンクからマイアカウントページにアクセスして、カード情報を更新してください。\n" .
                "%s\n\n" .
                "ご不明な点がございましたら、お気軽にお問い合わせください。\n\n" .
                "よろしくお願いいたします。\n" .
                "%s",
            $user->display_name,
            get_bloginfo('name'),
            $plan_name,
            $subscription->amount,
            $subscription->currency,
            $subscription_id,
            site_url('my-account'), // ここは実際のマイアカウントページのURLに変更してください
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
            error_log('Edel Square Payment Pro: カード情報不足通知メールを送信しました - サブスクリプションID: ' . $subscription_id);
        } else {
            error_log('Edel Square Payment Pro: カード情報不足通知メールの送信に失敗しました - サブスクリプションID: ' . $subscription_id);
        }

        return $sent;
    }

    /**
     * サブスクリプション支払い情報を保存
     *
     * @param array $data 支払いデータ
     * @return int|false 成功時は挿入ID、失敗時はfalse
     */
    public static function save_subscription_payment($data) {
        global $wpdb;

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscription_payments';

        // テーブル構造を確認（デバッグ）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $table_structure = $wpdb->get_results("DESCRIBE {$table_name}");
            error_log('支払いテーブル構造: ' . print_r($table_structure, true));
        }

        // 必須項目のチェック
        if (empty($data['subscription_id']) || empty($data['payment_id'])) {
            error_log('サブスクリプション支払い保存エラー: 必須パラメータが不足');
            return false;
        }

        // メタデータの処理
        $metadata = isset($data['metadata']) ? $data['metadata'] : array();
        $metadata_json = !empty($metadata) ? json_encode($metadata) : '';

        // 現在日時
        $now = date_i18n('Y-m-d H:i:s');

        // 挿入データの準備（カラム名をテーブル構造に合わせて調整）
        $insert_data = array(
            'subscription_id' => $data['subscription_id'],
            'payment_id' => $data['payment_id'],
            'amount' => isset($data['amount']) ? $data['amount'] : 0,
            'currency' => isset($data['currency']) ? $data['currency'] : 'JPY',
            'status' => isset($data['status']) ? $data['status'] : 'SUCCESS',
            // 'payment_date' カラムが存在しないため削除 or 代替カラム名を使用
            // 'payment_date' => isset($data['payment_date']) ? $data['payment_date'] : $now,

            // 代替カラム名の候補を使用
            // 'paid_date' => isset($data['payment_date']) ? $data['payment_date'] : $now,
            // 'transaction_date' => isset($data['payment_date']) ? $data['payment_date'] : $now,

            'metadata' => $metadata_json,
            'created_at' => $now
        );

        // フォーマット（カラム名をテーブル構造に合わせて調整）
        $format = array(
            '%s', // subscription_id
            '%s', // payment_id
            '%d', // amount
            '%s', // currency
            '%s', // status
            // '%s', // payment_date カラムが存在しないため削除 or 代替カラム名
            '%s', // metadata
            '%s'  // created_at
        );

        // データ挿入
        $result = $wpdb->insert($table_name, $insert_data, $format);

        if ($result === false) {
            error_log('サブスクリプション支払い保存エラー: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 支払い失敗通知メールを送信
     *
     * @param string $subscription_id サブスクリプションID
     * @param string $error_message エラーメッセージ
     * @return bool 送信成功時はtrue、失敗時はfalse
     */
    private function send_payment_failure_email($subscription_id, $error_message) {
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
        $subject = get_bloginfo('name') . ' - サブスクリプション決済に失敗しました';

        $message = sprintf(
            "こんにちは、%s様\n\n" .
                "あなたの %s のサブスクリプション決済処理に失敗しました。\n\n" .
                "サブスクリプション情報:\n" .
                "- プラン: %s\n" .
                "- 金額: %s %s\n" .
                "- サブスクリプションID: %s\n\n" .
                "失敗理由: %s\n\n" .
                "この問題を解決するために、支払い方法の更新をお願いします。以下のリンクからマイアカウントページにアクセスしてください。\n" .
                "%s\n\n" .
                "ご不明な点がございましたら、お気軽にお問い合わせください。\n\n" .
                "よろしくお願いいたします。\n" .
                "%s",
            $user->display_name,
            get_bloginfo('name'),
            $plan_name,
            $subscription->amount,
            $subscription->currency,
            $subscription_id,
            $error_message,
            site_url('my-account'), // ここは実際のマイアカウントページのURLに変更してください
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
            error_log('Edel Square Payment Pro: 支払い失敗通知メールを送信しました - サブスクリプションID: ' . $subscription_id);
        } else {
            error_log('Edel Square Payment Pro: 支払い失敗通知メールの送信に失敗しました - サブスクリプションID: ' . $subscription_id);
        }

        return $sent;
    }

    /**
     * サブスクリプション一時停止通知メールを送信
     *
     * @param string $subscription_id サブスクリプションID
     * @return bool 送信成功時はtrue、失敗時はfalse
     */
    private function send_subscription_pause_email($subscription_id) {
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
        $subject = get_bloginfo('name') . ' - サブスクリプションが一時停止されました';

        $message = sprintf(
            "こんにちは、%s様\n\n" .
                "あなたの %s のサブスクリプションが一時停止されました。\n\n" .
                "サブスクリプション情報:\n" .
                "- プラン: %s\n" .
                "- 金額: %s %s\n" .
                "- サブスクリプションID: %s\n\n" .
                "これは、複数回の決済処理に失敗したために発生しました。サブスクリプションを再開するには、支払い方法の更新が必要です。\n\n" .
                "以下のリンクからマイアカウントページにアクセスして、支払い方法を更新してください。\n" .
                "%s\n\n" .
                "ご不明な点がございましたら、お気軽にお問い合わせください。\n\n" .
                "よろしくお願いいたします。\n" .
                "%s",
            $user->display_name,
            get_bloginfo('name'),
            $plan_name,
            $subscription->amount,
            $subscription->currency,
            $subscription_id,
            site_url('my-account'), // ここは実際のマイアカウントページのURLに変更してください
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
            error_log('Edel Square Payment Pro: サブスクリプション一時停止通知メールを送信しました - サブスクリプションID: ' . $subscription_id);
        } else {
            error_log('Edel Square Payment Pro: サブスクリプション一時停止通知メールの送信に失敗しました - サブスクリプションID: ' . $subscription_id);
        }

        return $sent;
    }

    /**
     * 支払い成功通知メールを送信
     *
     * @param string $subscription_id サブスクリプションID
     * @param string $payment_id 決済ID
     * @return bool 送信成功時はtrue、失敗時はfalse
     */
    private function send_payment_success_email($subscription_id, $payment_id) {
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

        // 次回請求日をフォーマット
        $next_billing_date = date_i18n('Y年m月d日', strtotime($subscription->next_billing_date));

        // メールの件名と本文を作成
        $subject = get_bloginfo('name') . ' - サブスクリプション決済が完了しました';

        $message = sprintf(
            "こんにちは、%s様\n\n" .
                "あなたの %s のサブスクリプション決済が正常に処理されました。\n\n" .
                "決済情報:\n" .
                "- プラン: %s\n" .
                "- 金額: %s %s\n" .
                "- 決済ID: %s\n" .
                "- サブスクリプションID: %s\n" .
                "- 次回請求日: %s\n\n" .
                "サブスクリプションをご利用いただき、誠にありがとうございます。\n\n" .
                "マイアカウントページでサブスクリプションの詳細を確認できます。\n" .
                "%s\n\n" .
                "ご質問やお問い合わせがございましたら、お気軽にご連絡ください。\n\n" .
                "よろしくお願いいたします。\n" .
                "%s",
            $user->display_name,
            get_bloginfo('name'),
            $plan_name,
            $subscription->amount,
            $subscription->currency,
            $payment_id,
            $subscription_id,
            $next_billing_date,
            site_url('my-account'), // ここは実際のマイアカウントページのURLに変更してください
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
            error_log('Edel Square Payment Pro: 支払い成功通知メールを送信しました - サブスクリプションID: ' . $subscription_id);
        } else {
            error_log('Edel Square Payment Pro: 支払い成功通知メールの送信に失敗しました - サブスクリプションID: ' . $subscription_id);
        }

        return $sent;
    }
}
