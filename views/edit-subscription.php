<?php
// 権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

// メッセージ表示
if (isset($_GET['updated'])) {
    if ($_GET['updated'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>サブスクリプションが更新されました。</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>更新に失敗しました。もう一度お試しください。</p></div>';
    }
}

if (isset($_GET['canceled'])) {
    if ($_GET['canceled'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>サブスクリプションがキャンセルされました。</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>キャンセル処理に失敗しました。</p></div>';
    }
}

if (isset($_GET['manual_payment'])) {
    if ($_GET['manual_payment'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>手動決済が完了しました。</p></div>';
    } else {
        $error_message = isset($_GET['error_message']) ? urldecode($_GET['error_message']) : '手動決済に失敗しました。';
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
    }
}

// サブスクリプションIDの取得
$subscription_id = '';
if (isset($_GET['subscription_id']) && !empty($_GET['subscription_id'])) {
    $subscription_id = sanitize_text_field($_GET['subscription_id']);
} elseif (isset($_REQUEST['subscription_id']) && !empty($_REQUEST['subscription_id'])) {
    $subscription_id = sanitize_text_field($_REQUEST['subscription_id']);
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $subscription_id = sanitize_text_field($_GET['id']);
}

// サブスクリプションIDが空の場合
if (empty($subscription_id)) {
    wp_die('サブスクリプションIDが指定されていません。');
}

// サブスクリプション情報の取得
$subscription = EdelSquarePaymentProDB::get_subscription($subscription_id);

// サブスクリプションが存在しない場合
if (empty($subscription)) {
    wp_die('サブスクリプションが見つかりません。');
}

// ユーザー情報の取得
$user_info = '';
if (!empty($subscription->user_id)) {
    $user = get_userdata($subscription->user_id);
    if ($user) {
        $user_info = $user->user_email . ' (' . $user->display_name . ')';
    }
} elseif (!empty($subscription->metadata)) {
    // metadataからユーザー情報を取得
    $metadata = json_decode($subscription->metadata, true);
    if (isset($metadata['email'])) {
        $user_info = $metadata['email'];
    }
}

// プラン情報の取得
$plan_name = '';
if (!empty($subscription->plan_id)) {
    // プランIDからプラン名を取得する処理
    $plan = EdelSquarePaymentProDB::get_plan_object($subscription->plan_id);
    if ($plan && !empty($plan->name)) {
        $plan_name = $plan->name;
    }
}

?>

<div class="wrap">
    <h1>サブスクリプション詳細</h1>

    <div class="notice notice-info">
        <p>サブスクリプションID: <?php echo esc_html($subscription->subscription_id); ?></p>
    </div>

    <div class="card">
        <h2>サブスクリプション詳細</h2>

        <table class="form-table">
            <tr>
                <th>顧客</th>
                <td><?php echo esc_html($user_info); ?></td>
            </tr>
            <tr>
                <th>プラン名</th>
                <td>
                    <?php
                    if (!empty($plan_name)) {
                        echo esc_html($plan_name);
                    } elseif (!empty($subscription->plan_id)) {
                        echo '不明なプラン（ID: ' . esc_html($subscription->plan_id) . '）';
                    } else {
                        echo '情報なし';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th>金額</th>
                <td><?php echo esc_html($subscription->amount) . ' ' . esc_html($subscription->currency); ?></td>
            </tr>
            <tr>
                <th>ステータス</th>
                <td>
                    <?php
                    $status_text = '';
                    $status_color = '';
                    switch ($subscription->status) {
                        case 'ACTIVE':
                            $status_text = '有効';
                            $status_color = 'green';
                            break;
                        case 'CANCELED':
                            $status_text = 'キャンセル済み';
                            $status_color = 'red';
                            break;
                        case 'PAUSED':
                            $status_text = '一時停止';
                            $status_color = 'orange';
                            break;
                        default:
                            $status_text = $subscription->status;
                            $status_color = 'black';
                    }
                    echo '<span style="color: ' . $status_color . '; font-weight: bold;">' . esc_html($status_text) . '</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <th>作成日時</th>
                <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($subscription->created_at))); ?></td>
            </tr>
            <tr>
                <th>次回請求日</th>
                <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($subscription->next_billing_date))); ?></td>
            </tr>
            <?php if (!empty($subscription->canceled_at)): ?>
                <tr>
                    <th>キャンセル日時</th>
                    <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($subscription->canceled_at))); ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <form method="post" action="">
            <?php wp_nonce_field('update_subscription_nonce', 'subscription_nonce'); ?>
            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription_id); ?>">

            <h3>サブスクリプション更新</h3>

            <table class="form-table">
                <tr>
                    <th>ステータス</th>
                    <td>
                        <select name="subscription_status">
                            <option value="ACTIVE" <?php selected($subscription->status, 'ACTIVE'); ?>>有効</option>
                            <option value="CANCELED" <?php selected($subscription->status, 'CANCELED'); ?>>キャンセル済み</option>
                            <option value="PAUSED" <?php selected($subscription->status, 'PAUSED'); ?>>一時停止</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>次回請求日</th>
                    <td>
                        <?php
                        $next_billing_date = !empty($subscription->next_billing_date) ?
                            date('Y-m-d\TH:i', strtotime($subscription->next_billing_date)) :
                            date('Y-m-d\TH:i');
                        ?>
                        <input type="datetime-local" name="next_billing_date" value="<?php echo esc_attr($next_billing_date); ?>">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="update_subscription" class="button button-primary" value="更新する">
                <a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-subscriptions'); ?>" class="button">サブスクリプション一覧に戻る</a>
            </p>
        </form>
    </div>

    <!-- 決済履歴 -->
    <div class="card">
        <h2>決済履歴</h2>
        <?php
        // 関連する決済履歴の取得と表示（修正版）
        global $wpdb;

        // サブスクリプション決済テーブルから取得
        $subscription_payments_table = $wpdb->prefix . 'edel_square_payment_pro_subscription_payments';
        $payments_table = $wpdb->prefix . 'edel_square_payment_pro_payments';

        $payments = array();

        // サブスクリプション決済履歴を取得
        if ($wpdb->get_var("SHOW TABLES LIKE '$subscription_payments_table'") === $subscription_payments_table) {
            $subscription_payments = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $subscription_payments_table WHERE subscription_id = %s ORDER BY created_at DESC",
                    $subscription_id
                )
            );
            if ($subscription_payments) {
                $payments = array_merge($payments, $subscription_payments);
            }
        }

        // 一般決済テーブルからも取得（念のため）
        if ($wpdb->get_var("SHOW TABLES LIKE '$payments_table'") === $payments_table) {
            $general_payments = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $payments_table WHERE subscription_id = %s ORDER BY created_at DESC",
                    $subscription_id
                )
            );
            if ($general_payments) {
                $payments = array_merge($payments, $general_payments);
            }
        }

        if (!empty($payments)) {
            // 日付でソート
            usort($payments, function ($a, $b) {
                return strtotime($b->created_at) - strtotime($a->created_at);
            });

            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>決済ID</th>';
            echo '<th>金額</th>';
            echo '<th>通貨</th>';
            echo '<th>日時</th>';
            echo '<th>ステータス</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($payments as $payment) {
                echo '<tr>';
                echo '<td><code>' . esc_html($payment->payment_id) . '</code></td>';
                echo '<td>' . esc_html(number_format($payment->amount)) . '</td>';
                echo '<td>' . esc_html($payment->currency) . '</td>';
                echo '<td>' . esc_html(date_i18n('Y-m-d H:i:s', strtotime($payment->created_at))) . '</td>';
                echo '<td>';
                switch ($payment->status) {
                    case 'SUCCESS':
                    case 'COMPLETED':
                        echo '<span style="color: green; font-weight: bold;">✓ 成功</span>';
                        break;
                    case 'FAILED':
                        echo '<span style="color: red; font-weight: bold;">✗ 失敗</span>';
                        break;
                    case 'PENDING':
                        echo '<span style="color: orange; font-weight: bold;">⏳ 処理中</span>';
                        break;
                    default:
                        echo esc_html($payment->status);
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>決済履歴はありません。</p>';
        }
        ?>
    </div>

    <!-- サブスクリプション操作 -->
    <div class="card">
        <h2>サブスクリプション操作</h2>

        <?php if ($subscription->status !== 'CANCELED'): ?>
            <div style="margin-bottom: 20px;">
                <form method="post" action="" onsubmit="return confirm('このサブスクリプションをキャンセルしてもよろしいですか？この操作は取り消せません。');" style="display: inline-block; margin-right: 20px;">
                    <?php wp_nonce_field('cancel_subscription_nonce', 'cancel_nonce'); ?>
                    <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription_id); ?>">
                    <input type="submit" name="cancel_subscription" class="button button-secondary" value="サブスクリプションをキャンセルする" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                </form>
                <p class="description">※ キャンセル後は復元できません。現在の請求期間終了後にサブスクリプションが停止されます。</p>
            </div>

            <hr style="margin: 20px 0;">

            <div>
                <h3>手動決済実行</h3>
                <p>テスト用: 手動でこのサブスクリプションの決済を実行します。</p>
                <form method="post" action="" onsubmit="return confirm('手動で決済を実行しますか？');">
                    <?php wp_nonce_field('manual_payment_nonce', 'manual_payment_nonce'); ?>
                    <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription_id); ?>">
                    <input type="submit" name="manual_payment" class="button" value="手動決済実行">
                    <p class="description">※ 通常の請求日を待たずに、今すぐ決済を実行します。</p>
                </form>
            </div>
        <?php else: ?>
            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px;">
                <strong>このサブスクリプションはキャンセル済みです。</strong>
                <p>キャンセル日時: <?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($subscription->canceled_at))); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>