<?php
// 直接アクセス禁止
defined('ABSPATH') || exit;

// プランIDの取得
$plan_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

if (empty($plan_id)) {
    wp_die('プランIDが指定されていません。');
}

// プラン情報の取得
require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
$plan = EdelSquarePaymentProDB::get_plan($plan_id);

if (!$plan) {
    wp_die('指定されたプランが見つかりません。');
}

// フォーム送信処理
if (isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'update_plan_' . $plan_id)) {
    // 送信データの検証
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'JPY';
    $billing_cycle = isset($_POST['billing_cycle']) ? sanitize_text_field($_POST['billing_cycle']) : 'MONTHLY';
    $billing_interval = 1;
    $trial_period_days = isset($_POST['trial_period_days']) ? intval($_POST['trial_period_days']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'ACTIVE';
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

    // データの更新
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

    // リダイレクト
    wp_redirect(add_query_arg('updated', 'true', admin_url('admin.php?page=edel-square-payment-pro-plans')));
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">プラン編集</h1>
    <a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-plans'); ?>" class="page-title-action">プラン一覧に戻る</a>
    <hr class="wp-header-end">

    <form method="post" action="">
        <?php wp_nonce_field('update_plan_' . $plan_id); ?>
        <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
        <input type="hidden" name="edel_square_update_plan" value="1">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name">プラン名</label></th>
                <td>
                    <input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr($plan['name']); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="amount">金額</label></th>
                <td>
                    <input type="number" name="amount" id="amount" class="regular-text" value="<?php echo esc_attr($plan['amount']); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="currency">通貨</label></th>
                <td>
                    <select name="currency" id="currency">
                        <option value="JPY" <?php selected($plan['currency'], 'JPY'); ?>>JPY（日本円）</option>
                        <option value="USD" <?php selected($plan['currency'], 'USD'); ?>>USD（米ドル）</option>
                        <option value="EUR" <?php selected($plan['currency'], 'EUR'); ?>>EUR（ユーロ）</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="billing_cycle">請求サイクル</label></th>
                <td>
                    <select name="billing_cycle" id="billing_cycle">
                        <option value="DAILY" <?php selected($plan['billing_cycle'], 'DAILY'); ?>>毎日</option>
                        <option value="WEEKLY" <?php selected($plan['billing_cycle'], 'WEEKLY'); ?>>毎週</option>
                        <option value="MONTHLY" <?php selected($plan['billing_cycle'], 'MONTHLY'); ?>>毎月</option>
                        <option value="YEARLY" <?php selected($plan['billing_cycle'], 'YEARLY'); ?>>毎年</option>
                    </select>
                    <p class="description">請求の周期を選択してください。</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="trial_period_days">トライアル期間（日）</label></th>
                <td>
                    <input type="number" name="trial_period_days" id="trial_period_days" class="small-text" value="<?php echo esc_attr($plan['trial_period_days']); ?>" min="0">
                    <p class="description">初回の無料トライアル期間（日数）。0を設定するとトライアルなし。</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="status">ステータス</label></th>
                <td>
                    <select name="status" id="status">
                        <option value="ACTIVE" <?php selected($plan['status'], 'ACTIVE'); ?>>有効</option>
                        <option value="INACTIVE" <?php selected($plan['status'], 'INACTIVE'); ?>>無効</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="description">説明</label></th>
                <td>
                    <textarea name="description" id="description" class="large-text" rows="5"><?php echo esc_textarea($plan['description']); ?></textarea>
                    <p class="description">このプランの説明（オプション）。</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="更新">
        </p>
    </form>
</div>