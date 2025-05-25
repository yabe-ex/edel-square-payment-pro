<?php

/**
 * データベース関連のクラス
 */
class EdelSquarePaymentProDB {
    /**
     * テーブルを作成
     */
    public static function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;

        // 既存のLite版テーブル
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'main';
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            payment_id VARCHAR(255) NOT NULL,
            customer_id VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            amount BIGINT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'JPY',
            item_name TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            metadata LONGTEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_payment_id (payment_id),
            KEY idx_customer_id (customer_id),
            KEY idx_status (status),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $result = dbDelta($sql);
        // error_log("dbDelta1:" . implode("、", $result));

        // サブスクリプションプランテーブル
        $plans_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'plans';
        $plans_sql = "CREATE TABLE {$plans_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_id VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            amount BIGINT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'JPY',
            billing_cycle ENUM('DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY') NOT NULL DEFAULT 'MONTHLY',
            billing_interval INT NOT NULL DEFAULT 1,
            trial_period_days INT DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_plan_id (plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        dbDelta($plans_sql);

        // サブスクリプションテーブル
        $subscriptions_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscriptions';
        $subscriptions_sql = "CREATE TABLE {$subscriptions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            customer_id varchar(255) NOT NULL,
            plan_id varchar(255) NOT NULL,
            subscription_id varchar(255) NOT NULL,
            card_id varchar(255) DEFAULT NULL,
            status varchar(50) NOT NULL,
            amount bigint(20) NOT NULL,
            currency varchar(3) NOT NULL,
            current_period_start datetime NOT NULL,
            current_period_end datetime NOT NULL,
            next_billing_date datetime NOT NULL,
            trial_end datetime DEFAULT NULL,
            cancel_at datetime DEFAULT NULL,
            canceled_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            metadata text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY subscription_id (subscription_id),
            KEY user_id (user_id),
            KEY plan_id (plan_id)
        ) $charset_collate;";
        dbDelta($subscriptions_sql);

        // サブスクリプション支払い履歴テーブル
        $payments_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscription_payments';
        $payments_sql = "CREATE TABLE {$payments_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id VARCHAR(255) NOT NULL,
            payment_id VARCHAR(255) NOT NULL,
            amount BIGINT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'JPY',
            status VARCHAR(50) NOT NULL,
            billing_period_start DATETIME DEFAULT NULL,
            billing_period_end DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            metadata LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_payment_id (payment_id),
            KEY idx_subscription_id (subscription_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        dbDelta($payments_sql);

        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX  . 'payments';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_id varchar(255) NOT NULL,
            subscription_id varchar(255) DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            amount bigint(20) NOT NULL,
            currency varchar(3) NOT NULL,
            customer_id varchar(255) NOT NULL,
            item_name varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            metadata text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY payment_id (payment_id),
            KEY subscription_id (subscription_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 決済の総数を取得
     *
     * @return int 決済の総数
     */
    public static function count_payments() {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscription_payments';

        // テーブルが存在するか確認
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

        if (!$table_exists) {
            return 0; // テーブルが存在しない場合は0を返す
        }

        // 決済数を取得
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return $count;
    }

    /**
     * プランの総数を取得
     *
     * @return int プランの総数
     */
    public static function count_plans() {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'plans';

        // テーブルが存在するか確認
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

        if (!$table_exists) {
            return 0;
        }

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    /**
     * サブスクリプションの総数を取得
     *
     * @return int サブスクリプションの総数
     */
    public static function count_subscriptions() {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscriptions';

        // テーブルが存在するか確認
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

        if (!$table_exists) {
            return 0;
        }

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    /**
     * サブスクリプションに関連する決済履歴を取得する
     *
     * @param string $subscription_id サブスクリプションID
     * @return array 決済履歴の配列
     */
    public static function get_payments_by_subscription($subscription_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'edel_square_payment_pro_payments';

        // テーブルが存在するか確認
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            return array();
        }

        $payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE subscription_id = %s ORDER BY created_at DESC",
                $subscription_id
            )
        );

        return $payments;
    }

    /**
     * サブスクリプションプランを保存
     */
    public static function save_plan($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'plans';

        // 既存プランの確認
        if (isset($data['plan_id'])) {
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE plan_id = %s", $data['plan_id'])
            );
        }

        $now = current_time('mysql', false);

        if (!empty($existing)) {
            // 既存プランを更新
            $update_data = array(
                'updated_at' => $now
            );

            // 許可されたフィールドのみ更新
            $allowed_fields = array(
                'name',
                'description',
                'amount',
                'currency',
                'billing_cycle',
                'billing_interval',
                'trial_period_days',
                'status'
            );

            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_data[$field] = $data[$field];
                }
            }

            $wpdb->update(
                $table_name,
                $update_data,
                array('plan_id' => $data['plan_id']),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s'),
                array('%s')
            );

            return $existing->id;
        } else {
            // 新規プラン作成
            if (!isset($data['plan_id'])) {
                $data['plan_id'] = 'plan_' . uniqid();
            }

            $insert_data = array(
                'plan_id' => $data['plan_id'],
                'name' => $data['name'],
                'amount' => $data['amount'],
                'status' => isset($data['status']) ? $data['status'] : 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now
            );

            // 任意フィールドの処理
            $optional_fields = array(
                'description',
                'currency',
                'billing_cycle',
                'billing_interval',
                'trial_period_days'
            );

            foreach ($optional_fields as $field) {
                if (isset($data[$field])) {
                    $insert_data[$field] = $data[$field];
                }
            }

            $wpdb->insert(
                $table_name,
                $insert_data,
                array('%s', '%s', '%d', '%s', '%s', '%s')
            );

            return $wpdb->insert_id;
        }
    }

    /**
     * サブスクリプションを保存
     */
    public static function save_subscription($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscriptions';

        // 既存サブスクリプションの確認
        if (isset($data['subscription_id'])) {
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE subscription_id = %s", $data['subscription_id'])
            );
        }

        $now = current_time('mysql', false);

        if (!empty($existing)) {
            // 更新
            $update_data = array(
                'updated_at' => $now
            );

            // 許可されたフィールドのみ更新
            $allowed_fields = array(
                'user_id',
                'customer_id',
                'plan_id',
                'card_id',
                'status',
                'amount',
                'currency',
                'current_period_start',
                'current_period_end',
                'next_billing_date',
                'trial_end',
                'cancel_at',
                'canceled_at',
                'metadata'
            );

            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'metadata' && is_array($data[$field])) {
                        $update_data[$field] = json_encode($data[$field]);
                    } else {
                        $update_data[$field] = $data[$field];
                    }
                }
            }

            $wpdb->update(
                $table_name,
                $update_data,
                array('subscription_id' => $data['subscription_id']),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%s')
            );

            return $existing->id;
        } else {
            // 新規作成
            if (!isset($data['subscription_id'])) {
                $data['subscription_id'] = 'sub_' . uniqid();
            }

            $insert_data = array(
                'subscription_id' => $data['subscription_id'],
                'user_id' => $data['user_id'],
                'customer_id' => $data['customer_id'],
                'plan_id' => $data['plan_id'],
                'status' => $data['status'],
                'amount' => $data['amount'],
                'created_at' => $now,
                'updated_at' => $now
            );

            // 任意フィールドの処理
            $optional_fields = array(
                'card_id',
                'currency',
                'current_period_start',
                'current_period_end',
                'next_billing_date',
                'trial_end',
                'metadata'
            );

            foreach ($optional_fields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'metadata' && is_array($data[$field])) {
                        $insert_data[$field] = json_encode($data[$field]);
                    } else {
                        $insert_data[$field] = $data[$field];
                    }
                }
            }

            $wpdb->insert(
                $table_name,
                $insert_data,
                array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );

            return $wpdb->insert_id;
        }
    }

    /**
     * サブスクリプション支払い情報を保存
     */
    public static function save_subscription_payment($data) {
        global $wpdb;

        // データの検証
        if (empty($data['subscription_id']) || empty($data['payment_id'])) {
            error_log('サブスクリプション支払い保存エラー: 必須パラメータが不足');
            return false;
        }

        // 文字列でないフィールドを文字列に変換（wpdb::prepareのエラー対策）
        foreach ($data as $key => $value) {
            if (is_object($value) || is_array($value)) {
                $data[$key] = is_object($value) ? json_encode($value) : json_encode($value);
            }
        }

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscription_payments';

        // データ
        $insert_data = array(
            'subscription_id' => $data['subscription_id'],
            'payment_id' => $data['payment_id'],
            'amount' => $data['amount'],
            'currency' => isset($data['currency']) ? $data['currency'] : 'JPY',
            'status' => isset($data['status']) ? $data['status'] : 'SUCCESS',
            // payment_dateカラムは削除
            'created_at' => date_i18n('Y-m-d H:i:s'),
        );

        // メタデータの追加
        if (isset($data['metadata'])) {
            $insert_data['metadata'] = is_array($data['metadata']) ? json_encode($data['metadata']) : $data['metadata'];
        }

        // フォーマット
        $format = array(
            '%s', // subscription_id
            '%s', // payment_id
            '%d', // amount
            '%s', // currency
            '%s', // status
            '%s'  // created_at
        );

        // メタデータがある場合はフォーマットに追加
        if (isset($insert_data['metadata'])) {
            $format[] = '%s'; // metadata
        }

        // テーブル構造を確認（デバッグ）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $table_structure = $wpdb->get_results("DESCRIBE {$table_name}");
            error_log('テーブル構造確認: ' . print_r($table_structure, true));
            error_log('挿入データ: ' . print_r($insert_data, true));
        }

        // 挿入
        $result = $wpdb->insert($table_name, $insert_data, $format);

        if ($result === false) {
            error_log('サブスクリプション支払い保存エラー: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 支払い情報を保存
     *
     * @param array $data 支払いデータ
     * @return int|false 成功時は挿入ID、失敗時はfalse
     */
    public static function save_payment($data) {
        global $wpdb;

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_payments';

        // 必須項目のチェック
        if (empty($data['payment_id'])) {
            error_log('支払い情報保存エラー: 必須パラメータが不足');
            return false;
        }

        // メタデータの処理
        $metadata = isset($data['metadata']) ? $data['metadata'] : array();
        $metadata_json = !empty($metadata) ? (is_string($metadata) ? $metadata : json_encode($metadata)) : '';

        // 現在日時
        $now = date_i18n('Y-m-d H:i:s');

        // 挿入データの準備
        $insert_data = array(
            'payment_id' => $data['payment_id'],
            'user_id' => isset($data['user_id']) ? $data['user_id'] : 0,
            'amount' => isset($data['amount']) ? $data['amount'] : 0,
            'currency' => isset($data['currency']) ? $data['currency'] : 'JPY',
            'item_name' => isset($data['item_name']) ? $data['item_name'] : '商品',
            'status' => isset($data['status']) ? $data['status'] : 'COMPLETED',
            'customer_id' => isset($data['customer_id']) ? $data['customer_id'] : '',
            'metadata' => $metadata_json,
            'created_at' => isset($data['created_at']) ? $data['created_at'] : $now,
            'updated_at' => $now
        );

        // サブスクリプションIDが設定されている場合は追加
        if (!empty($data['subscription_id'])) {
            $insert_data['subscription_id'] = $data['subscription_id'];
        }

        // データ挿入
        $result = $wpdb->insert($table_name, $insert_data);

        if ($result === false) {
            error_log('支払い情報保存エラー: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * プラン情報を取得する（配列として）
     *
     * @param string $plan_id プランID
     * @return array|false プラン情報、または取得失敗時はフォールバック情報
     */
    public static function get_plan($plan_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'edel_square_payment_pro_plans';

        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE plan_id = %s",
                $plan_id
            ),
            ARRAY_A  // これを追加して配列として取得
        );

        // プランが見つからない場合のフォールバック処理
        if (empty($plan)) {
            // 代替情報として空の配列を作成
            $plan = array(
                'name' => '不明なプラン（ID: ' . $plan_id . '）',
                'plan_id' => $plan_id
            );

            // ログに記録（オプション）
            error_log('プランが見つかりません：' . $plan_id);
        }

        return $plan;
    }

    /**
     * プラン情報をオブジェクトとして取得する
     *
     * @param string $plan_id プランID
     * @return object|false プラン情報のオブジェクト、または取得失敗時はfalse
     */
    public static function get_plan_object($plan_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'edel_square_payment_pro_plans';

        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE plan_id = %s",
                $plan_id
            )
        );

        // プランが見つからない場合のフォールバック処理
        if (empty($plan)) {
            // 代替情報として空のオブジェクトを作成
            $plan = new stdClass();
            $plan->name = '不明なプラン（ID: ' . $plan_id . '）';
            $plan->plan_id = $plan_id;

            // ログに記録（オプション）
            error_log('プランが見つかりません：' . $plan_id);
        }

        return $plan;
    }

    /**
     * サブスクリプション情報を取得する
     *
     * @param string $subscription_id サブスクリプションID
     * @return object|false サブスクリプション情報、または取得失敗時はfalse
     */
    public static function get_subscription($subscription_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';

        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE subscription_id = %s",
                $subscription_id
            )
        );

        return $subscription;
    }

    /**
     * ユーザーのサブスクリプションを取得
     */
    public static function get_user_subscriptions($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscriptions';

        $subscriptions = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC", $user_id),
            ARRAY_A
        );

        // メタデータをデコード
        foreach ($subscriptions as &$subscription) {
            if (!empty($subscription['metadata'])) {
                $subscription['metadata'] = json_decode($subscription['metadata'], true);
            }
        }

        return $subscriptions;
    }

    /**
     * プラン一覧を取得
     *
     * @param int $limit 取得する件数（デフォルト: 0=全件）
     * @param int $offset 開始位置（デフォルト: 0）
     * @return array プラン一覧
     */
    public static function get_plans($limit = 0, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'plans';

        $sql = "SELECT * FROM {$table_name} ORDER BY name ASC";

        // LIMIT句の追加
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * サブスクリプション一覧を取得
     *
     * @param int $limit 取得する件数（デフォルト: 0=全件）
     * @param int $offset 開始位置（デフォルト: 0）
     * @return array サブスクリプション一覧
     */
    public static function get_subscriptions($limit = 0, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscriptions';

        $sql = "SELECT * FROM {$table_name} ORDER BY created_at DESC";

        // LIMIT句の追加
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * 決済一覧を取得
     *
     * @param int $limit 取得する件数（デフォルト: 0=全件）
     * @param int $offset 開始位置（デフォルト: 0）
     * @return array 決済一覧
     */
    public static function get_payments($limit = 0, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscription_payments';

        $sql = "SELECT * FROM {$table_name} ORDER BY created_at DESC";

        // LIMIT句の追加
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * ユーザーの決済履歴を取得（サブスクリプション決済を含む）
     *
     * @param int $user_id ユーザーID
     * @return array 決済履歴
     */
    public static function get_user_payments($user_id) {
        global $wpdb;

        // テーブル名を定数を使って統一
        $payment_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'payments';
        $subscription_payment_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscription_payments';
        $subscription_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'subscriptions';
        $plans_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'plans';

        error_log('=== 決済履歴取得開始: ユーザーID=' . $user_id . ' ===');
        error_log('テーブル: payment=' . $payment_table . ', sub_payment=' . $subscription_payment_table);

        // テーブル存在チェック
        $subscription_payment_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$subscription_payment_table'") === $subscription_payment_table;
        $subscription_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$subscription_table'") === $subscription_table;
        $payment_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$payment_table'") === $payment_table;

        error_log('テーブル存在確認: subscription_payments=' . ($subscription_payment_table_exists ? 'あり' : 'なし') .
            ', subscriptions=' . ($subscription_table_exists ? 'あり' : 'なし') .
            ', payments=' . ($payment_table_exists ? 'あり' : 'なし'));

        // 結果配列
        $payments = array();

        // サブスクリプション決済の取得
        if ($subscription_payment_table_exists && $subscription_table_exists) {
            try {
                // ユーザーのサブスクリプションを取得
                $subscriptions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $subscription_table WHERE user_id = %d",
                        $user_id
                    ),
                    ARRAY_A
                );

                error_log('ユーザーのサブスクリプション数: ' . count($subscriptions));

                if (!empty($subscriptions)) {
                    // サブスクリプションIDを抽出
                    $subscription_ids = array();
                    foreach ($subscriptions as $sub) {
                        $subscription_ids[] = $sub['subscription_id'];
                    }

                    error_log('サブスクリプションID: ' . implode(', ', $subscription_ids));

                    if (!empty($subscription_ids)) {
                        // 各サブスクリプションIDの決済を個別に取得（IN句の問題を回避）
                        foreach ($subscription_ids as $sub_id) {
                            $sub_payments = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT * FROM $subscription_payment_table WHERE subscription_id = %s ORDER BY created_at DESC",
                                    $sub_id
                                ),
                                ARRAY_A
                            );

                            error_log('サブスクリプション ' . $sub_id . ' の決済数: ' . ($sub_payments ? count($sub_payments) : 0));

                            if ($sub_payments && is_array($sub_payments)) {
                                // 情報を追加して結果配列に追加
                                foreach ($sub_payments as &$payment) {
                                    // サブスクリプション情報から関連情報を取得
                                    $current_sub = null;
                                    foreach ($subscriptions as $sub) {
                                        if ($sub['subscription_id'] === $payment['subscription_id']) {
                                            $current_sub = $sub;
                                            break;
                                        }
                                    }

                                    if ($current_sub && isset($current_sub['plan_id'])) {
                                        // プラン情報を取得
                                        $plan = $wpdb->get_row(
                                            $wpdb->prepare(
                                                "SELECT * FROM $plans_table WHERE plan_id = %s",
                                                $current_sub['plan_id']
                                            ),
                                            ARRAY_A
                                        );

                                        $payment['item_name'] = $plan && isset($plan['name']) ? $plan['name'] . ' (定期決済)' : 'サブスクリプション決済';
                                        $payment['amount'] = isset($payment['amount']) ? $payment['amount'] : $current_sub['amount'];
                                        $payment['currency'] = isset($payment['currency']) ? $payment['currency'] : $current_sub['currency'];
                                    } else {
                                        $payment['item_name'] = 'サブスクリプション決済';
                                    }

                                    // フォーマットを統一（通常決済と一致させる）
                                    if (!isset($payment['status'])) {
                                        $payment['status'] = 'SUCCESS';
                                    }
                                }

                                $payments = array_merge($payments, $sub_payments);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('サブスクリプション決済取得エラー: ' . $e->getMessage());
                error_log('エラー詳細: ' . $e->getTraceAsString());
            }
        }

        // 一般決済の取得
        if ($payment_table_exists) {
            try {
                $regular_payments = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $payment_table WHERE user_id = %d ORDER BY created_at DESC",
                        $user_id
                    ),
                    ARRAY_A
                );

                error_log('一般決済数: ' . ($regular_payments ? count($regular_payments) : 0));

                if ($regular_payments && is_array($regular_payments)) {
                    $payments = array_merge($payments, $regular_payments);
                }
            } catch (Exception $e) {
                error_log('一般決済取得エラー: ' . $e->getMessage());
            }
        }

        // ソート
        if (!empty($payments)) {
            usort($payments, function ($a, $b) {
                $a_date = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                $b_date = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                return $b_date - $a_date;
            });
        }

        error_log('最終的な決済履歴件数: ' . count($payments) . '件');
        error_log('=== 決済履歴取得終了 ===');

        return $payments;
    }

    /**
     * サブスクリプション情報を更新する
     *
     * @param string $subscription_id サブスクリプションID
     * @param array $data 更新データ
     * @return bool 更新成功時はtrue、失敗時はfalse
     */
    public static function update_subscription($subscription_id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';

        // 更新日時を追加
        $data['updated_at'] = current_time('mysql');

        // 更新処理
        $result = $wpdb->update(
            $table_name,
            $data,
            array('subscription_id' => $subscription_id)
        );

        return $result !== false;
    }

    /**
     * サブスクリプションのステータスを更新
     *
     * @param string $subscription_id サブスクリプションID
     * @param string $status 新しいステータス
     * @return bool 成功時はtrue、失敗時はfalse
     */
    public static function update_subscription_status($subscription_id, $status) {
        global $wpdb;

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';

        // 更新データ
        $update_data = array(
            'status' => $status,
            'updated_at' => date_i18n('Y-m-d H:i:s')
        );

        // 更新条件
        $where = array(
            'subscription_id' => $subscription_id
        );

        // データ更新
        $result = $wpdb->update(
            $table_name,
            $update_data,
            $where,
            array('%s', '%s'),
            array('%s')
        );

        if ($result === false) {
            error_log('サブスクリプションステータス更新エラー: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * サブスクリプションの次回請求日を更新
     *
     * @param string $subscription_id サブスクリプションID
     * @param string $next_billing_date 次回請求日（Y-m-d H:i:s形式）
     * @param string $current_period_start 現在の課金期間開始日（Y-m-d H:i:s形式）
     * @param string $current_period_end 現在の課金期間終了日（Y-m-d H:i:s形式）
     * @return bool 成功時はtrue、失敗時はfalse
     */
    public static function update_subscription_billing_date($subscription_id, $next_billing_date, $current_period_start, $current_period_end) {
        global $wpdb;

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';

        // 更新データ
        $update_data = array(
            'next_billing_date' => $next_billing_date,
            'current_period_start' => $current_period_start,
            'current_period_end' => $current_period_end,
            'updated_at' => date_i18n('Y-m-d H:i:s')
        );

        // 更新条件
        $where = array(
            'subscription_id' => $subscription_id
        );

        // データ更新
        $result = $wpdb->update(
            $table_name,
            $update_data,
            $where,
            array('%s', '%s', '%s', '%s'),
            array('%s')
        );

        if ($result === false) {
            error_log('サブスクリプション請求日更新エラー: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 決済予定のサブスクリプションを取得
     */
    public static function get_due_subscriptions() {
        global $wpdb;

        // テーブル名
        $table_name = $wpdb->prefix . 'edel_square_payment_pro_subscriptions';

        // 本日の日付
        $today = date_i18n('Y-m-d');

        // クエリ
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE status = 'ACTIVE'
            AND DATE(next_billing_date) <= %s
            ORDER BY next_billing_date ASC",
            $today
        );

        // 結果取得
        $results = $wpdb->get_results($query);

        return $results;
    }

    /**
     * プラン情報を更新する
     *
     * @param array $plan_data プランデータ
     * @return bool 更新成功時はtrue、失敗時はfalse
     */
    public static function update_plan($plan_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'plans';

        // 必須項目のチェック
        if (empty($plan_data['plan_id'])) {
            return false;
        }

        // 更新データの準備
        $data = array();
        $format = array();

        // 名前
        if (isset($plan_data['name'])) {
            $data['name'] = $plan_data['name'];
            $format[] = '%s';
        }

        // 金額
        if (isset($plan_data['amount'])) {
            $data['amount'] = $plan_data['amount'];
            $format[] = '%d';
        }

        // 通貨
        if (isset($plan_data['currency'])) {
            $data['currency'] = $plan_data['currency'];
            $format[] = '%s';
        }

        // 請求サイクル
        if (isset($plan_data['billing_cycle'])) {
            $data['billing_cycle'] = $plan_data['billing_cycle'];
            $format[] = '%s';
        }

        // 請求間隔
        if (isset($plan_data['billing_interval'])) {
            $data['billing_interval'] = intval($plan_data['billing_interval']);
            $format[] = '%d';
        }

        // トライアル期間
        if (isset($plan_data['trial_period_days'])) {
            $data['trial_period_days'] = intval($plan_data['trial_period_days']);
            $format[] = '%d';
        }

        // ステータス
        if (isset($plan_data['status'])) {
            $data['status'] = $plan_data['status'];
            $format[] = '%s';
        }

        // 説明
        if (isset($plan_data['description'])) {
            $data['description'] = $plan_data['description'];
            $format[] = '%s';
        }

        // 更新日時
        if (isset($plan_data['updated_at'])) {
            $data['updated_at'] = $plan_data['updated_at'];
            $format[] = '%s';
        } else {
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';
        }

        // データベース更新
        $where = array('plan_id' => $plan_data['plan_id']);
        $where_format = array('%s');

        $result = $wpdb->update($table_name, $data, $where, $format, $where_format);

        return $result !== false;
    }

    /**
     * 決済一覧のデフォルト表示件数を取得
     *
     * @return int 表示件数
     */
    public static function get_payments_per_page() {
        // デフォルト値は50件
        $default = 50;

        // フィルターフックを通して値を変更可能に
        return apply_filters('edel_square_payments_per_page', $default);
    }

    /**
     * サブスクリプション一覧のデフォルト表示件数を取得
     *
     * @return int 表示件数
     */
    public static function get_subscriptions_per_page() {
        // デフォルト値は50件
        $default = 50;

        // フィルターフックを通して値を変更可能に
        return apply_filters('edel_square_subscriptions_per_page', $default);
    }

    /**
     * プラン一覧のデフォルト表示件数を取得
     *
     * @return int 表示件数
     */
    public static function get_plans_per_page() {
        // デフォルト値は50件
        $default = 50;

        // フィルターフックを通して値を変更可能に
        return apply_filters('edel_square_plans_per_page', $default);
    }
}
