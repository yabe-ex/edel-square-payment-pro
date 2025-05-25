<?php

/**
 * Square API連携クラス
 */
class EdelSquarePaymentProAPI {
    private $client;
    private $application_id;
    private $location_id;
    private $environment;
    private $is_sandbox;
    private $access_token;
    private $api_client;

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 設定の取得
        require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
        $settings = EdelSquarePaymentProSettings::get_settings();

        // 環境設定
        $this->environment = isset($settings['environment']) && $settings['environment'] === 'production' ? 'production' : 'sandbox';
        $this->is_sandbox = ($this->environment === 'sandbox'); // is_sandboxフラグを設定


        // 環境に応じたIDの設定
        if ($this->environment === 'production') {
            $this->application_id = isset($settings['production_application_id']) ? $settings['production_application_id'] : '';
            $this->location_id = isset($settings['production_location_id']) ? $settings['production_location_id'] : '';
            $access_token = isset($settings['production_access_token']) ? $settings['production_access_token'] : '';
        } else {
            $this->application_id = isset($settings['sandbox_application_id']) ? $settings['sandbox_application_id'] : '';
            $this->location_id = isset($settings['sandbox_location_id']) ? $settings['sandbox_location_id'] : '';
            $access_token = isset($settings['sandbox_access_token']) ? $settings['sandbox_access_token'] : '';
        }

        // デバッグ情報
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Square API初期化 - application_id: ' . $this->application_id . ', location_id: ' . $this->location_id);
        }

        // クライアントの初期化
        $this->init_client($access_token);
    }

    /**
     * クライアントの初期化
     */
    private function init_client($access_token) {
        if (empty($access_token)) {
            error_log('Square API - アクセストークンが設定されていません');
            return;
        }

        try {
            // Square SDKのオートロード
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/vendor/autoload.php';

            // 環境に応じた設定
            $environment = $this->environment === 'production' ?
                \Square\Environment::PRODUCTION :
                \Square\Environment::SANDBOX;

            // クライアント設定
            $client_config = [
                'accessToken' => $access_token,
                'environment' => $environment
            ];

            // クライアント初期化
            $this->client = new \Square\SquareClient($client_config);
        } catch (\Exception $e) {
            error_log('Square API - クライアント初期化エラー: ' . $e->getMessage());
        }
    }

    /**
     * アクセストークンを取得する
     *
     * @return string アクセストークン
     */
    private function get_access_token() {
        // プラグイン設定からアクセストークンを取得
        $options = get_option('edel_square_payment_settings', []);

        // 環境に応じたトークンを返す
        $environment = $this->get_environment();
        if ($environment === 'sandbox') {
            return isset($options['sandbox_access_token']) ? $options['sandbox_access_token'] : '';
        } else {
            return isset($options['production_access_token']) ? $options['production_access_token'] : '';
        }
    }

    /**
     * 環境設定を取得する
     *
     * @return string 環境設定（'sandbox' または 'production'）
     */
    private function get_environment() {
        // プラグイン設定から環境設定を取得
        $options = get_option('edel_square_payment_settings', []);
        return isset($options['environment']) && $options['environment'] === 'production' ? 'production' : 'sandbox';
    }

    /**
     * 初期化
     */
    private function init() {
        // 設定を取得
        $options = get_option(EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'settings', array());

        // Sandbox/Productionモードの設定
        $this->is_sandbox = isset($options['sandbox_mode']) && $options['sandbox_mode'] === '1';

        // 適切なAPIキーを選択
        if ($this->is_sandbox) {
            $this->access_token = isset($options['sandbox_access_token']) ? $options['sandbox_access_token'] : '';
            $this->application_id = isset($options['sandbox_application_id']) ? $options['sandbox_application_id'] : '';
            $this->location_id = isset($options['sandbox_location_id']) ? $options['sandbox_location_id'] : '';
        } else {
            $this->access_token = isset($options['production_access_token']) ? $options['production_access_token'] : '';
            $this->application_id = isset($options['production_application_id']) ? $options['production_application_id'] : '';
            $this->location_id = isset($options['production_location_id']) ? $options['production_location_id'] : '';
        }

        // Square APIクライアントのロード
        // コンポーザーでSquare SDKをインストールしている前提
        if (!class_exists('\Square\SquareClient')) {
            // Square SDKがインストールされていない場合のエラー処理
            error_log('Square SDK is not installed. Please run composer require square/square');
            return;
        }

        // APIクライアントの初期化
        $this->api_client = new \Square\SquareClient([
            'accessToken' => $this->access_token,
            'environment' => $this->is_sandbox ? \Square\Environment::SANDBOX : \Square\Environment::PRODUCTION,
        ]);
    }

    /**
     * APIクライアントが正しく設定されているか確認
     */
    public function is_connected() {
        if (empty($this->access_token) || empty($this->location_id) || empty($this->application_id)) {
            return false;
        }

        try {
            // ロケーション情報を取得してテスト
            $locations_api = $this->api_client->getLocationsApi();
            $result = $locations_api->retrieveLocation($this->location_id);

            if ($result->isSuccess()) {
                return true;
            } else {
                error_log('Square API connection test failed: ' . json_encode($result->getErrors()));
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API connection test error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 支払い処理を作成
     */
    public function create_payment($nonce, $amount, $item_name = '', $metadata = array()) {
        try {
            // 支払い処理を作成
            $payments_api = $this->api_client->getPaymentsApi();

            // 金額はセント単位なので、円からセントに変換
            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount($amount);
            $amount_money->setCurrency('JPY');

            // 支払い情報の作成
            $body = new \Square\Models\CreatePaymentRequest(
                $nonce,
                uniqid('sq-'),
                $amount_money
            );

            // 商品名をメモとして追加
            if (!empty($item_name)) {
                $body->setNote($item_name);
            }

            // メタデータは使用せず、必要な情報はメモ欄に追加
            if (!empty($metadata) && isset($metadata['email'])) {
                $note = $body->getNote() ?? '';
                $note .= ' Email: ' . $metadata['email'];
                $body->setNote($note);
            }

            // 支払いの実行
            $result = $payments_api->createPayment($body);

            if ($result->isSuccess()) {
                $payment = $result->getResult()->getPayment();

                // 支払いデータをデータベースに保存
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';

                $payment_data = array(
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'amount' => $amount,
                    'currency' => 'JPY',
                    'item_name' => $item_name,
                    'email' => isset($metadata['email']) ? $metadata['email'] : '',
                    'created_at' => current_time('mysql', false),
                    'metadata' => json_encode($metadata) // メタデータをローカルDBには保存
                );

                // Customer IDがあれば保存
                if (method_exists($payment, 'getCustomerId') && $payment->getCustomerId()) {
                    $payment_data['customer_id'] = $payment->getCustomerId();
                }

                // 現在のユーザーIDがあれば紐付け
                if (is_user_logged_in()) {
                    $payment_data['user_id'] = get_current_user_id();
                }

                // Receipt URLがあれば保存
                if (method_exists($payment, 'getReceiptUrl') && $payment->getReceiptUrl()) {
                    $payment_data['receipt_url'] = $payment->getReceiptUrl();
                }

                // データベースに保存
                EdelSquarePaymentProDB::save_payment($payment_data);

                return array(
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'receipt_url' => method_exists($payment, 'getReceiptUrl') ? $payment->getReceiptUrl() : ''
                );
            } else {
                $errors = $result->getErrors();
                $error_message = '';

                if (is_array($errors) && count($errors) > 0) {
                    $error_message = $errors[0]->getDetail();
                    error_log('Square payment error: ' . $error_message);
                } else {
                    $error_message = '決済処理に失敗しました。';
                    error_log('Square payment failed with unknown error');
                }

                return array(
                    'success' => false,
                    'error' => $error_message,
                );
            }
        } catch (\Exception $e) {
            error_log('Square payment exception: ' . $e->getMessage());

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * 支払い情報を取得
     */
    public function get_payment($payment_id) {
        try {
            $payments_api = $this->api_client->getPaymentsApi();
            $result = $payments_api->getPayment($payment_id);

            if ($result->isSuccess()) {
                return $result->getResult()->getPayment();
            } else {
                $errors = $result->getErrors();
                error_log('Failed to get Square payment: ' . json_encode($errors));
                return null;
            }
        } catch (\Exception $e) {
            error_log('Square get payment error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 返金処理
     */
    public function refund_payment($payment_id, $amount, $reason = '') {
        try {
            $refunds_api = $this->api_client->getRefundsApi();

            // 金額を設定
            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount($amount);
            $amount_money->setCurrency('JPY');

            // 返金リクエストを作成
            $body = new \Square\Models\RefundPaymentRequest(
                uniqid('sq-refund-'),
                $amount_money
            );

            // 返金理由を設定
            if (!empty($reason)) {
                $body->setReason($reason);
            }

            // 支払いIDを設定
            $body->setPaymentId($payment_id);

            // 返金を実行
            $result = $refunds_api->refundPayment($body);

            if ($result->isSuccess()) {
                $refund = $result->getResult()->getRefund();

                // 支払いステータスを更新
                require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';

                // 関連する支払いを取得
                $payment = EdelSquarePaymentProDB::get_payment($payment_id);

                if ($payment) {
                    // 返金情報をメタデータに追加
                    $metadata = !empty($payment['metadata']) ? $payment['metadata'] : array();
                    $metadata['refunds'] = isset($metadata['refunds']) ? $metadata['refunds'] : array();
                    $metadata['refunds'][] = array(
                        'refund_id' => $refund->getId(),
                        'amount' => $amount,
                        'status' => $refund->getStatus(),
                        'reason' => $reason,
                        'date' => current_time('mysql', false),
                    );

                    // 支払いステータスを更新
                    EdelSquarePaymentProDB::save_payment(array(
                        'payment_id' => $payment_id,
                        'status' => 'REFUNDED',
                        'metadata' => $metadata,
                    ));
                }

                return array(
                    'success' => true,
                    'refund_id' => $refund->getId(),
                    'status' => $refund->getStatus(),
                );
            } else {
                $errors = $result->getErrors();
                error_log('Square refund failed: ' . json_encode($errors));

                return array(
                    'success' => false,
                    'error' => $errors[0]->getDetail(),
                );
            }
        } catch (\Exception $e) {
            error_log('Square refund error: ' . $e->getMessage());

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * アプリケーションIDを取得
     */
    public function get_application_id() {
        return $this->application_id;
    }

    /**
     * ロケーションIDを取得
     */
    public function get_location_id() {
        return $this->location_id;
    }

    /**
     * サンドボックスモードかどうか
     */
    public function is_sandbox_mode() {
        return $this->is_sandbox;
    }

    /**
     * 顧客情報を作成・取得
     */
    /**
     * 顧客情報を取得または作成する
     *
     * @param int $user_id WordPressユーザーID
     * @param string $email ユーザーメールアドレス
     * @param string $name ユーザー名
     * @return mixed 顧客オブジェクト、失敗時はfalse
     */
    public function get_or_create_customer($user_id, $email, $name) {
        // メールアドレスのバリデーション
        if (empty($email) || !$this->is_valid_email($email)) {
            error_log('Square API - 無効なメールアドレス形式: ' . $email);
            return false;
        }

        // 既存の顧客IDをメタデータから取得
        $customer_id = get_user_meta($user_id, '_square_customer_id', true);

        // 顧客IDがある場合は顧客情報を取得
        if (!empty($customer_id)) {
            try {
                $response = $this->client->getCustomersApi()->retrieveCustomer($customer_id);
                if ($response->isSuccess()) {
                    return $response->getResult()->getCustomer();
                }
            } catch (Exception $e) {
                // エラーログ記録
                error_log('Square API - 既存顧客取得エラー: ' . $e->getMessage());
            }
        }

        // 顧客IDがない、または取得に失敗した場合はメールアドレスで検索
        try {
            // 検索条件の構築
            $email_filter = new \Square\Models\CustomerFilter();
            $email_exact_filter = new \Square\Models\CustomerTextFilter();
            $email_exact_filter->setExact($email);
            $email_filter->setEmailAddress($email_exact_filter);

            $query = new \Square\Models\CustomerQuery();
            $query->setFilter($email_filter);

            $search_request = new \Square\Models\SearchCustomersRequest();
            $search_request->setQuery($query);
            $search_request->setLimit(1);

            $api_response = $this->client->getCustomersApi()->searchCustomers($search_request);

            if ($api_response->isSuccess()) {
                $customers = $api_response->getResult()->getCustomers();

                if (!empty($customers)) {
                    // 顧客情報をメタデータに保存
                    update_user_meta($user_id, '_square_customer_id', $customers[0]->getId());
                    return $customers[0];
                }
            }
        } catch (Exception $e) {
            error_log('Square API - 顧客検索エラー: ' . $e->getMessage());
        }

        // 顧客が見つからない場合は新規作成
        try {
            $idempotency_key = uniqid('customer_', true);
            $request = new \Square\Models\CreateCustomerRequest();
            $request->setIdempotencyKey($idempotency_key);
            $request->setGivenName($name);
            $request->setEmailAddress($email);

            $response = $this->client->getCustomersApi()->createCustomer($request);

            if ($response->isSuccess()) {
                $customer = $response->getResult()->getCustomer();
                // 顧客情報をメタデータに保存
                update_user_meta($user_id, '_square_customer_id', $customer->getId());
                return $customer;
            } else {
                $errors = $response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - 顧客作成エラー: ' . $error_message);
            }
        } catch (Exception $e) {
            error_log('Square API - 顧客作成例外: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * メールアドレスで顧客を検索
     */
    public function find_customer_by_email($email) {
        try {
            $customers_api = $this->api_client->getCustomersApi();

            // 検索条件を設定
            $query = new \Square\Models\CustomerQuery();

            $filter = new \Square\Models\CustomerFilter();
            $email_filter = new \Square\Models\CustomerTextFilter();
            $email_filter->setExact($email);
            $filter->setEmailAddress($email_filter);
            $query->setFilter($filter);

            $search_request = new \Square\Models\SearchCustomersRequest();
            $search_request->setQuery($query);

            // 検索実行
            $result = $customers_api->searchCustomers($search_request);

            if ($result->isSuccess()) {
                $customers = $result->getResult()->getCustomers();
                if (!empty($customers)) {
                    return $customers[0]->getId();
                }
            } else {
                $errors = $result->getErrors();
                error_log('Square API - 顧客検索エラー: ' . json_encode($errors));
            }

            return null;
        } catch (\Exception $e) {
            error_log('Square API - 顧客検索例外: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * カード情報を作成・保存するメソッド
     *
     * @param string $customer_id    顧客ID
     * @param string $source_id      カードトークン（nonceなど）
     * @param string $cardholder_name カード所有者名
     * @return mixed 成功時はカードオブジェクト、失敗時はfalse
     */
    public function create_card($customer_id, $source_id, $cardholder_name) {
        try {
            // クライアントが初期化されているか確認
            if (!$this->client) {
                error_log('Square API - クライアントが初期化されていません');
                $this->init_client($this->get_access_token(), $this->get_environment());

                // 再度確認
                if (!$this->client) {
                    error_log('Square API - クライアントの再初期化に失敗しました');
                    return false;
                }
            }

            // バリデーション
            if (empty($customer_id) || empty($source_id)) {
                error_log('Square API - カード作成パラメータが不足しています');
                return false;
            }

            // 文字列がUTF-8でない場合、変換
            if (!mb_check_encoding($cardholder_name, 'UTF-8')) {
                $cardholder_name = mb_convert_encoding($cardholder_name, 'UTF-8');
            }

            // idempotency_keyを生成 - 同じリクエストが重複処理されないための一意キー
            $idempotency_key = uniqid('card_', true);

            // カードデータの準備
            $card = new \Square\Models\Card();

            // 顧客IDの型をチェックして適切に処理
            if (is_object($customer_id) && method_exists($customer_id, 'getId')) {
                // 顧客オブジェクトからIDを取得
                $card->setCustomerId($customer_id->getId());
            } else {
                // 文字列の場合はそのまま使用
                $card->setCustomerId($customer_id);
            }

            // カード所有者名が指定されている場合のみセット
            if (!empty($cardholder_name)) {
                $card->setCardholderName($cardholder_name);
            }

            // CreateCardRequestの作成 - 必須の3つの引数を渡す
            $request = new \Square\Models\CreateCardRequest(
                $idempotency_key,  // 必須: ユニークなリクエスト識別子
                $source_id,        // 必須: カード情報のソースID（nonce）
                $card              // 必須: カード関連データオブジェクト
            );

            // APIリクエスト送信
            $api_response = $this->client->getCardsApi()->createCard($request);

            // レスポンス処理
            if ($api_response->isSuccess()) {
                $result_card = $api_response->getResult()->getCard();
                error_log('Square API - カード作成成功: カードID=' . $result_card->getId());
                return $result_card;
            } else {
                $errors = $api_response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - カード作成エラー: ' . $error_message);
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API - カード作成例外: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * メールアドレスによる顧客検索
     *
     * @param string $email 検索対象のメールアドレス
     * @return mixed 見つかった場合は顧客オブジェクト、見つからない場合はnull、エラー時はfalse
     */
    public function search_customer_by_email($email) {
        try {
            // メールアドレスのバリデーション
            if (empty($email) || !$this->is_valid_email($email)) {
                error_log('Square API - 無効なメールアドレス形式: ' . $email);
                return false;
            }

            // 検索条件の構築
            $email_filter = new \Square\Models\CustomerFilter();
            $email_exact_filter = new \Square\Models\CustomerTextFilter();
            $email_exact_filter->setExact($email);
            $email_filter->setEmailAddress($email_exact_filter);

            $query = new \Square\Models\CustomerQuery();
            $query->setFilter($email_filter);

            // リクエストの作成
            $search_request = new \Square\Models\SearchCustomersRequest();
            $search_request->setQuery($query);
            $search_request->setLimit(1); // 一致する最初の顧客のみ取得

            // APIリクエスト送信
            $api_response = $this->client->getCustomersApi()->searchCustomers($search_request);

            // レスポンス処理
            if ($api_response->isSuccess()) {
                $customers = $api_response->getResult()->getCustomers();

                // 顧客が見つかった場合
                if (!empty($customers) && count($customers) > 0) {
                    error_log('Square API - 顧客検索成功: メール=' . $email . ', 顧客ID=' . $customers[0]->getId());
                    return $customers[0]; // 最初の一致する顧客を返す
                } else {
                    error_log('Square API - 顧客検索結果なし: メール=' . $email);
                    return null; // 顧客が見つからない場合
                }
            } else {
                $errors = $api_response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - 顧客検索エラー: ' . $error_message);
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API - 顧客検索例外: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * メールアドレスが有効かチェックする
     *
     * @param string $email チェック対象のメールアドレス
     * @return boolean 有効な場合はtrue、無効な場合はfalse
     */
    private function is_valid_email($email) {
        // PHPの標準関数でメールアドレスを検証
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // 追加のバリデーション（必要に応じて）
        // 例: 特定のドメインを許可しない、文字数制限など

        // MXレコードの確認（オプション、より厳格なチェックが必要な場合）
        /*
    $domain = substr(strrchr($email, "@"), 1);
    if (!checkdnsrr($domain, 'MX')) {
        return false;
    }
    */

        return true;
    }


    /**
     * 買い切り決済処理
     *
     * @param string $payment_token 支払いトークン（SQNonce）
     * @param string $customer_id 顧客ID（オプション）
     * @param float $amount 金額
     * @param string $currency 通貨コード
     * @param string $note 備考
     * @param array $metadata メタデータ
     * @return mixed 成功時は支払いオブジェクト、失敗時はfalse
     */
    public function process_onetime_payment($payment_token, $amount, $currency = 'JPY', $customer_id = '', $note = '', $metadata = array()) {
        try {
            // クライアントの初期化チェック
            if (!$this->client) {
                error_log('Square API - process_onetime_payment: クライアントが初期化されていません');
                $this->init_client($this->get_access_token(), $this->get_environment());

                if (!$this->client) {
                    error_log('Square API - process_onetime_payment: クライアントの再初期化に失敗しました');
                    return false;
                }
            }

            // 必須パラメータのチェック
            if (empty($payment_token) || empty($amount)) {
                error_log('Square API - process_onetime_payment: 必須パラメータが不足しています');
                return false;
            }

            // 顧客IDの整形（オブジェクトの場合）
            if (is_object($customer_id) && method_exists($customer_id, 'getId')) {
                $customer_id = $customer_id->getId();
            }

            // 金額の整形
            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount(intval($amount));
            $amount_money->setCurrency($currency);

            // idempotency_keyの生成
            $idempotency_key = uniqid('payment_', true);

            // デバッグ情報
            error_log('Square API - process_onetime_payment: トークン=' . substr($payment_token, 0, 10) . '..., 金額=' . $amount . $currency);

            // 支払いリクエストの作成
            $payment_body = new \Square\Models\CreatePaymentRequest(
                $payment_token,    // 第1引数: 支払いトークン
                $idempotency_key,  // 第2引数: 一意のキー
                $amount_money      // 第3引数: 金額情報
            );

            // 顧客IDが指定されている場合
            if (!empty($customer_id)) {
                $payment_body->setCustomerId($customer_id);
            }

            // 備考と参照IDの設定
            $payment_body->setNote($note);
            if (!empty($metadata) && isset($metadata['reference_id'])) {
                $payment_body->setReferenceId($metadata['reference_id']);
            }

            // 即時決済の設定
            $payment_body->setAutocomplete(true);

            // APIリクエスト送信
            $api_response = $this->client->getPaymentsApi()->createPayment($payment_body);

            // レスポンス処理
            if ($api_response->isSuccess()) {
                $payment = $api_response->getResult()->getPayment();
                error_log('Square API - 買い切り決済成功: 金額=' . $amount . $currency . ', ID=' . $payment->getId());
                return $payment;
            } else {
                $errors = $api_response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - 買い切り決済エラー: ' . $error_message);
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API - 買い切り決済例外: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * 買い切り決済専用の支払い処理メソッド
     * パラメータ順序の問題を解決する新しいメソッド
     */

    /**
     * 買い切り決済専用の支払い処理メソッド
     */
    public function process_single_payment($token, $amount, $currency = 'JPY', $email = '', $first_name = '', $last_name = '', $item_name = '', $metadata = array()) {
        // デバッグログ
        error_log('単発決済処理開始 - トークン=' . substr($token, 0, 10) . '..., 金額=' . $amount . ', 商品名=' . $item_name);

        // 必須パラメータのチェック
        if (empty($token) || empty($amount)) {
            error_log('Square API - 単発決済: 必須パラメータが不足しています');
            return false;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Square API - 無効なメールアドレス形式（修正済み）: ' . $email);
            // 無効なメールアドレスでもエラーにはせず処理を続行
        }

        // 顧客ID取得またはログイン中ユーザー用に顧客作成
        $customer_id = '';
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            // ログイン済みユーザーの場合
            $user = get_userdata($user_id);
            if ($user) {
                $customer = $this->get_or_create_customer($user->user_email, $user->first_name, $user->last_name);
                if ($customer) {
                    $customer_id = is_object($customer) && method_exists($customer, 'getId') ? $customer->getId() : $customer;
                }
            }
        } else if (!empty($email)) {
            // 非ログインユーザーの場合、メールアドレスから顧客を検索/作成
            $customer = $this->get_or_create_customer($email, $first_name, $last_name);
            if ($customer) {
                $customer_id = is_object($customer) && method_exists($customer, 'getId') ? $customer->getId() : $customer;
            }
        }

        // 支払い処理の実行
        // idempotency_keyの生成（重複決済防止）
        $idempotency_key = 'payment_' . uniqid();

        // 金額オブジェクトの作成
        $money = new \Square\Models\Money();
        $money->setAmount($amount);  // 金額（JPYの場合は整数）
        $money->setCurrency($currency);

        // 支払いリクエストの作成
        $payment_request = new \Square\Models\CreatePaymentRequest(
            $token,  // ソースID（カードトークン）
            $idempotency_key,  // 冪等性キー
            $money  // 金額オブジェクト
        );

        // 顧客IDが存在する場合は設定
        if (!empty($customer_id)) {
            $payment_request->setCustomerId($customer_id);
        }

        // メモの設定
        if (!empty($item_name)) {
            $payment_request->setNote($item_name . ' - ' . date_i18n('Y-m-d H:i:s'));
        }

        // リファレンスIDの設定（メタデータ）
        if (!empty($metadata)) {
            $reference_id = isset($metadata['reference_id']) ? $metadata['reference_id'] : 'order_' . uniqid();
            $payment_request->setReferenceId($reference_id);
        }

        // 支払いAPIの呼び出し
        try {
            // get_client() の代わりに、APIクラスの $client プロパティを直接使用
            $payments_api = $this->client->getPaymentsApi();
            $api_response = $payments_api->createPayment($payment_request);

            if ($api_response->isSuccess()) {
                $payment = $api_response->getResult();
                error_log('Square API - 単発決済成功: 決済ID=' . $payment->getPayment()->getId());
                return $payment->getPayment();
            } else {
                $errors = $api_response->getErrors();
                $error_messages = array();
                foreach ($errors as $error) {
                    $error_messages[] = $error->getDetail();
                }
                error_log('Square API - 単発決済失敗: ' . implode(', ', $error_messages));
                return false;
            }
        } catch (Exception $e) {
            error_log('Square API - 単発決済例外: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * サブスクリプション初回決済処理
     *
     * @param string $customer_id 顧客ID
     * @param string $payment_token 支払いトークン（SQNonce）
     * @param float $amount 金額
     * @param string $currency 通貨コード
     * @param string $note 備考
     * @param array $metadata メタデータ
     * @return mixed 成功時は支払いオブジェクト、失敗時はfalse
     */
    public function process_subscription_payment($customer_id, $payment_token, $amount, $currency = 'JPY', $note = '', $metadata = array()) {
        try {
            // クライアントの初期化チェック
            if (!$this->client) {
                error_log('Square API - process_subscription_payment: クライアントが初期化されていません');
                $this->init_client($this->get_access_token(), $this->get_environment());

                if (!$this->client) {
                    error_log('Square API - process_subscription_payment: クライアントの再初期化に失敗しました');
                    return false;
                }
            }

            // 必須パラメータのチェック
            if (empty($customer_id) || empty($payment_token) || empty($amount)) {
                error_log('Square API - process_subscription_payment: 必須パラメータが不足しています');
                return false;
            }

            // 顧客IDの整形（オブジェクトの場合）
            if (is_object($customer_id) && method_exists($customer_id, 'getId')) {
                $customer_id = $customer_id->getId();
            }

            // 金額の整形
            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount(intval($amount));
            $amount_money->setCurrency($currency);

            // idempotency_keyの生成
            $idempotency_key = uniqid('sub_payment_', true);

            // デバッグ情報
            error_log('Square API - process_subscription_payment: 顧客ID=' . $customer_id . ', トークン=' . substr($payment_token, 0, 10) . '..., 金額=' . $amount . $currency);

            // 支払いリクエストの作成
            $payment_body = new \Square\Models\CreatePaymentRequest(
                $payment_token,    // 第1引数: 支払いトークン
                $idempotency_key,  // 第2引数: 一意のキー
                $amount_money      // 第3引数: 金額情報
            );

            // 顧客IDの設定
            $payment_body->setCustomerId($customer_id);

            // 備考と参照IDの設定
            $payment_body->setNote($note);
            if (!empty($metadata) && isset($metadata['subscription_id'])) {
                $payment_body->setReferenceId($metadata['subscription_id']);
            }

            // 即時決済の設定
            $payment_body->setAutocomplete(true);

            // APIリクエスト送信
            $api_response = $this->client->getPaymentsApi()->createPayment($payment_body);

            error_log('Square API - process_subscription_payment: リクエスト送信前 (params: ' . json_encode([
                'payment_token' => substr($payment_token, 0, 10) . '...',
                'customer_id' => $customer_id,
                'amount' => $amount,
                'currency' => $currency
            ]) . ')');

            // レスポンス処理
            if ($api_response->isSuccess()) {
                $payment = $api_response->getResult()->getPayment();
                error_log('Square API - サブスクリプション初回決済成功: 金額=' . $amount . $currency . ', ID=' . $payment->getId());
                return $payment;
            } else {
                $errors = $api_response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - サブスクリプション初回決済エラー: ' . $error_message);
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API - サブスクリプション初回決済例外: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * 保存したカードで決済処理
     */
    /**
     * カードで決済を行う
     */
    public function charge_card($customer_id, $card_id, $amount, $currency = 'JPY', $note = '', $metadata = array()) {
        if (empty($customer_id) || empty($card_id) || empty($amount) || empty($currency)) {
            error_log('Square API - charge_card: 必須パラメータが不足しています');
            return array(
                'success' => false,
                'error' => '必須パラメータが不足しています',
                'status' => 'ERROR'
            );
        }

        try {
            // クライアントのチェックと初期化
            if (!$this->client) {
                error_log('Square API - charge_card: クライアントが初期化されていません');
                $this->init_client($this->get_access_token(), $this->get_environment());

                if (!$this->client) {
                    error_log('Square API - charge_card: クライアントの再初期化に失敗しました');
                    return false;
                }
            }

            // 顧客IDの取得（オブジェクトまたは文字列）
            if (is_object($customer_id) && method_exists($customer_id, 'getId')) {
                $customer_id = $customer_id->getId();
            }

            // カードIDの取得（オブジェクトまたは文字列）
            if (is_object($card_id) && method_exists($card_id, 'getId')) {
                $card_id = $card_id->getId();
            }

            // 必須パラメータのチェック
            if (empty($customer_id) || empty($card_id) || empty($amount)) {
                error_log('Square API - charge_card: 必須パラメータが不足しています');
                return false;
            }

            // 金額の整形（整数に変換）
            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount(intval($amount));
            $amount_money->setCurrency($currency);

            // idempotency_keyの生成
            $idempotency_key = uniqid('payment_', true);

            // 支払い情報の作成
            $payment_body = new \Square\Models\CreatePaymentRequest(
                $idempotency_key,
                uniqid('src_', true),
                $amount_money
            );

            // 顧客IDと支払いメモの設定
            $payment_body->setCustomerId($customer_id);
            $payment_body->setSourceId($card_id);
            $payment_body->setNote($note);

            // メタデータの設定
            if (!empty($metadata) && is_array($metadata)) {
                $payment_body->setReferenceId(isset($metadata['subscription_id']) ? $metadata['subscription_id'] : uniqid('ref_', true));
            }

            // APIリクエスト送信
            $api_response = $this->client->getPaymentsApi()->createPayment($payment_body);

            // レスポンス処理
            if ($api_response->isSuccess()) {
                $payment = $api_response->getResult()->getPayment();
                error_log('Square API - 決済成功: 金額=' . $amount . $currency . ', ID=' . $payment->getId());
                return $payment;
            } else {
                $errors = $api_response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - 決済エラー: ' . $error_message);
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API - 決済例外: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * カード情報を取得
     */
    public function get_card($card_id) {
        try {
            $cards_api = $this->api_client->getCardsApi();
            $result = $cards_api->retrieveCard($card_id);

            if ($result->isSuccess()) {
                $card = $result->getResult()->getCard();
                return $card;
            } else {
                $errors = $result->getErrors();
                error_log('Square API - カード取得エラー: ' . json_encode($errors));
                return null;
            }
        } catch (\Exception $e) {
            error_log('Square API - カード取得例外: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * APIクライアントを初期化する
     */
    private function init_api() {
        try {
            // 設定の取得
            require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-settings.php';
            $settings = EdelSquarePaymentProSettings::get_settings();

            // APIキーの取得
            $sandbox_mode = isset($settings['sandbox_mode']) && $settings['sandbox_mode'] === 'yes';
            $access_token = $sandbox_mode ? $settings['sandbox_access_token'] : $settings['production_access_token'];

            if (empty($access_token)) {
                error_log('Square API - init_api: アクセストークンが設定されていません。');
                return false;
            }

            // 環境設定
            $environment = $sandbox_mode ? \Square\Environment::SANDBOX : \Square\Environment::PRODUCTION;

            // Square APIクライアントの初期化
            $this->client = new \Square\SquareClient([
                'accessToken' => $access_token,
                'environment' => $environment,
                'userAgentDetail' => 'Edel Square Payment Pro',
            ]);

            // アプリケーションIDとロケーションIDを取得
            $this->application_id = $sandbox_mode ? $settings['sandbox_application_id'] : $settings['production_application_id'];
            $this->location_id = $settings['location_id'];

            error_log('Square API初期化 - application_id: ' . $this->application_id . ', location_id: ' . $this->location_id);

            return true;
        } catch (Exception $e) {
            error_log('Square API - init_api: 例外が発生しました - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 顧客のカード情報を取得する
     *
     * @param string $customer_id 顧客ID
     * @return array カード情報の配列
     */
    public function get_customer_cards($customer_id) {
        error_log('Square API - get_customer_cards: 顧客ID=' . $customer_id);

        // APIクライアントの初期化
        if ($this->client === null) {
            $this->init_api();
            error_log('Square API - get_customer_cards: APIクライアント初期化実行');
        }

        // クライアントが初期化されたか確認
        if ($this->client === null) {
            error_log('Square API - get_customer_cards: APIクライアントが初期化されていません');
            return array();
        }

        try {
            error_log('Square API - get_customer_cards: カード情報取得処理開始');

            // APIリクエストパラメータのログ
            error_log('Square API - get_customer_cards: リクエストパラメータ - customerId=' . $customer_id);

            // カード一覧取得API呼び出し
            $result = $this->client->getCardsApi()->listCards(null, $customer_id);

            // API応答のログ
            if ($result->isSuccess()) {
                $cards_result = $result->getResult();
                $cards = $cards_result ? $cards_result->getCards() : null;

                // nullチェックを追加
                if ($cards === null) {
                    error_log('Square API - get_customer_cards: カード一覧がnullです');
                    return array();
                }

                // 配列に変換して返す
                $card_array = array();
                foreach ($cards as $card) {
                    $card_array[] = $card;
                }

                error_log('Square API - get_customer_cards: 取得成功 - カード数=' . count($card_array));

                // 各カードの詳細をログに出力
                foreach ($card_array as $index => $card) {
                    if (method_exists($card, 'getId')) {
                        error_log("Square API - get_customer_cards: カード[$index] ID=" . $card->getId());
                    }
                }

                return $card_array;
            } else {
                $errors = $result->getErrors();
                $error_message = isset($errors[0]) ? $errors[0]->getDetail() : '不明なエラー';
                error_log('Square API - get_customer_cards: 取得失敗 - ' . $error_message);

                // エラー詳細をログに出力
                foreach ($errors as $index => $error) {
                    error_log("Square API - get_customer_cards: エラー[$index] " . $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail());
                }

                return array();
            }
        } catch (Exception $e) {
            error_log('Square API - get_customer_cards: 例外発生 - ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return array();
        }
    }

    /**
     * 既存のカードIDで課金処理を行う
     *
     * @param string $customer_id 顧客ID
     * @param string $card_id カードID
     * @param float $amount 金額
     * @param string $currency 通貨
     * @param string $note メモ
     * @param array $metadata メタデータ
     * @return array 処理結果
     */
    public function charge_card_id($customer_id, $card_id, $amount, $currency, $note = '', $metadata = array()) {
        // パラメータチェック
        if (empty($customer_id) || empty($card_id) || empty($amount) || empty($currency)) {
            error_log('Square API - charge_card_id: 必須パラメータが不足しています');
            return array(
                'success' => false,
                'error' => '必須パラメータが不足しています',
                'status' => 'ERROR'
            );
        }

        error_log("Square API - charge_card_id: 顧客ID={$customer_id}, カードID={$card_id}, 金額={$amount}{$currency}");

        try {
            // APIクライアントが初期化されていない場合
            if ($this->client === null) {
                $this->init_api();
            }

            if ($this->client === null) {
                error_log('Square API - charge_card_id: APIクライアントが初期化されていません');
                return array(
                    'success' => false,
                    'error' => 'APIクライアントが初期化されていません',
                    'status' => 'ERROR'
                );
            }

            $amount_in_smallest_unit = $amount;
            if ($currency !== 'JPY') {
                // JPY以外の通貨は小数点以下2桁まで扱うことが多い
                $amount_in_smallest_unit = (int)($amount * 100);
            }

            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount(intval($amount_in_smallest_unit)); // 金額を整数に変換（セント単位）
            $amount_money->setCurrency($currency);

            // 支払い情報の作成
            $idempotency_key = uniqid('sub_payment_');
            $payment_body = new \Square\Models\CreatePaymentRequest(
                $card_id,
                $idempotency_key,
                $amount_money
            );

            // 顧客ID、カードIDを設定
            $payment_body->setCustomerId($customer_id);
            $payment_body->setSourceId($card_id);

            // メモを設定
            if (!empty($note)) {
                $payment_body->setNote($note);
            }

            // メタデータを設定
            if (!empty($metadata) && isset($metadata['subscription_id'])) {
                $payment_body->setReferenceId($metadata['subscription_id']);
            }

            // 自動補完を有効化
            $payment_body->setAutocomplete(true);

            error_log('Square API - charge_card_id: 決済リクエスト送信');

            // 支払いAPIの呼び出し
            $result = $this->client->getPaymentsApi()->createPayment($payment_body);

            if ($result->isSuccess()) {
                $payment = $result->getResult()->getPayment();

                error_log('Square API - charge_card_id: 決済成功 - ID=' . $payment->getId());

                return array(
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'receipt_url' => $payment->getReceiptUrl()
                );
            } else {
                $errors = $result->getErrors();
                $error_message = isset($errors[0]) ? $errors[0]->getDetail() : '不明なエラー';
                error_log('Square API - charge_card_id: 決済失敗 - ' . $error_message);

                // エラー詳細をログに出力
                foreach ($errors as $index => $error) {
                    if (method_exists($error, 'getCategory') && method_exists($error, 'getCode') && method_exists($error, 'getDetail')) {
                        error_log("Square API - charge_card_id: エラー[$index] " . $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail());
                    } else {
                        error_log("Square API - charge_card_id: エラー[$index] " . json_encode($error));
                    }
                }

                return array(
                    'success' => false,
                    'error' => $error_message,
                    'status' => 'FAILED'
                );
            }
        } catch (Exception $e) {
            error_log('Square API - charge_card_id: 例外発生 - ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'ERROR'
            );
        }
    }

    /**
     * 保存済みカードで決済を行う
     *
     * @param string $customer_id 顧客ID
     * @param string $card_id カードID
     * @param float $amount 金額
     * @param string $currency 通貨コード
     * @param string $note 備考
     * @param array $metadata メタデータ
     * @return mixed 成功時は支払いオブジェクト、失敗時はfalse
     */
    public function charge_saved_card($customer_id, $card_id, $amount, $currency = 'JPY', $note = '', $metadata = array()) {
        try {
            // クライアントのチェックと初期化
            if (!$this->client) {
                error_log('Square API - charge_saved_card: クライアントが初期化されていません');
                $this->init_client($this->get_access_token());

                if (!$this->client) {
                    error_log('Square API - charge_saved_card: クライアントの再初期化に失敗しました');
                    return false;
                }
            }

            // 必須パラメータのチェック
            if (empty($customer_id) || empty($card_id) || empty($amount)) {
                error_log('Square API - charge_saved_card: 必須パラメータが不足しています');
                return false;
            }

            // 金額の整形（整数に変換）
            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount(intval($amount));
            $amount_money->setCurrency($currency);

            // idempotency_keyの生成
            $idempotency_key = uniqid('card_payment_', true);

            // 支払いリクエストの作成
            $payment_body = new \Square\Models\CreatePaymentRequest(
                $card_id,         // 保存済みカードID
                $idempotency_key, // 一意のキー
                $amount_money     // 金額情報
            );

            $payment_body->setCustomerId($customer_id);
            $payment_body->setNote($note);
            $payment_body->setAutocomplete(true);

            if (!empty($metadata) && isset($metadata['subscription_id'])) {
                $payment_body->setReferenceId($metadata['subscription_id']);
            }

            // APIリクエスト送信
            $api_response = $this->client->getPaymentsApi()->createPayment($payment_body);

            // レスポンス処理
            if ($api_response->isSuccess()) {
                $payment = $api_response->getResult()->getPayment();
                error_log('Square API - 保存済みカード決済成功: 金額=' . $amount . $currency . ', ID=' . $payment->getId());
                return $payment;
            } else {
                $errors = $api_response->getErrors();
                $error_message = '';
                foreach ($errors as $error) {
                    $error_message .= $error->getCategory() . ': ' . $error->getCode() . ' - ' . $error->getDetail() . '; ';
                }
                error_log('Square API - 保存済みカード決済エラー: ' . $error_message);
                return false;
            }
        } catch (\Exception $e) {
            error_log('Square API - 保存済みカード決済例外: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
}
