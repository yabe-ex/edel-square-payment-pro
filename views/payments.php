<?php
// 直接アクセス禁止
defined('ABSPATH') || exit;

// ページネーション用の設定
$per_page = EdelSquarePaymentProDB::get_payments_per_page(); // フィルターフックから件数を取得
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// 決済一覧を取得
require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';

// 総件数を取得
global $wpdb;
$subscription_payments_count = EdelSquarePaymentProDB::count_payments(); // サブスク決済数
$onetime_table = $wpdb->prefix . EDEL_SQUARE_PAYMENT_PRO_PREFIX . 'payments';
$onetime_payments_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$onetime_table}"); // 買い切り決済数
$total_items = $subscription_payments_count + $onetime_payments_count;

// 全ページ数を計算
$total_pages = ceil($total_items / $per_page);

// サブスク決済を取得（ページネーション対応）
$subscription_payments = EdelSquarePaymentProDB::get_payments($per_page, $offset);

// サブスク決済だけで1ページ分に満たない場合は、残りを買い切り決済で埋める
$remaining_count = $per_page - count($subscription_payments);
if ($remaining_count > 0) {
    // 買い切り決済データの取得に使用するoffsetを計算
    // 例: 2ページ目で、サブスク決済が15件ある場合、買い切り決済は5件取得し、offsetは5件
    $onetime_offset = max(0, ($current_page - 1) * $per_page - $subscription_payments_count);

    $onetime_payments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$onetime_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $remaining_count,
            $onetime_offset
        )
    );
} else {
    $onetime_payments = array();
}

// 結果をマージ
$payments = array_merge($subscription_payments, $onetime_payments);

// 日付でソート
usort($payments, function ($a, $b) {
    return strtotime($b->created_at) - strtotime($a->created_at);
});
?>

<div class="wrap">
    <h1 class="wp-heading-inline">決済一覧</h1>
    <hr class="wp-header-end">

    <div class="tablenav top">
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php echo esc_html($total_items); ?> 件</span>
                <span class="pagination-links">
                    <?php
                    // 最初のページへのリンク
                    if ($current_page > 1) {
                        echo '<a class="first-page button" href="' . esc_url(add_query_arg('paged', 1)) . '"><span class="screen-reader-text">最初のページ</span><span aria-hidden="true">&laquo;</span></a>';
                    } else {
                        echo '<span class="first-page button disabled"><span class="screen-reader-text">最初のページ</span><span aria-hidden="true">&laquo;</span></span>';
                    }

                    // 前のページへのリンク
                    if ($current_page > 1) {
                        echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '"><span class="screen-reader-text">前のページ</span><span aria-hidden="true">&lsaquo;</span></a>';
                    } else {
                        echo '<span class="prev-page button disabled"><span class="screen-reader-text">前のページ</span><span aria-hidden="true">&lsaquo;</span></span>';
                    }

                    // ページ番号の表示
                    echo '<span class="paging-input">' . $current_page . ' / <span class="total-pages">' . $total_pages . '</span></span>';

                    // 次のページへのリンク
                    if ($current_page < $total_pages) {
                        echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '"><span class="screen-reader-text">次のページ</span><span aria-hidden="true">&rsaquo;</span></a>';
                    } else {
                        echo '<span class="next-page button disabled"><span class="screen-reader-text">次のページ</span><span aria-hidden="true">&rsaquo;</span></span>';
                    }

                    // 最後のページへのリンク
                    if ($current_page < $total_pages) {
                        echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages)) . '"><span class="screen-reader-text">最後のページ</span><span aria-hidden="true">&raquo;</span></a>';
                    } else {
                        echo '<span class="last-page button disabled"><span class="screen-reader-text">最後のページ</span><span aria-hidden="true">&raquo;</span></span>';
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <table class="wp-list-table widefat striped posts">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-id">決済ID</th>
                <th scope="col" class="manage-column column-email">メールアドレス</th>
                <th scope="col" class="manage-column column-type">種別</th>
                <th scope="col" class="manage-column column-details">サブスクID／商品名</th>
                <th scope="col" class="manage-column column-amount">金額</th>
                <th scope="col" class="manage-column column-date">決済日</th>
                <th scope="col" class="manage-column column-status">ステータス</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="7">決済が見つかりません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="column-id">
                            <strong><?php echo esc_html($payment->payment_id); ?></strong>
                        </td>
                        <td class="column-email">
                            <?php
                            // メールアドレスを取得
                            $email = '';

                            // 1. サブスクリプションテーブルからメールアドレスを取得（サブスクリプションIDがある場合）
                            if (!empty($payment->subscription_id)) {
                                $subscription = $wpdb->get_row($wpdb->prepare(
                                    "SELECT s.*, u.user_email
                                    FROM {$wpdb->prefix}" . EDEL_SQUARE_PAYMENT_PRO_PREFIX . "subscriptions s
                                    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                                    WHERE s.subscription_id = %s",
                                    $payment->subscription_id
                                ));

                                if ($subscription && !empty($subscription->user_email)) {
                                    $email = $subscription->user_email;
                                } elseif ($subscription && !empty($subscription->metadata)) {
                                    // メタデータからメールアドレスを取得
                                    $metadata = json_decode($subscription->metadata, true);
                                    if (is_array($metadata) && isset($metadata['email'])) {
                                        $email = $metadata['email'];
                                    }
                                }
                            }

                            // 2. user_idからメールアドレスを取得（まだ見つからない場合）
                            if (empty($email) && !empty($payment->user_id)) {
                                $user = get_user_by('id', $payment->user_id);
                                if ($user) {
                                    $email = $user->user_email;
                                }
                            }

                            // 3. メールアドレスが空で、メタデータがある場合
                            if (empty($email) && property_exists($payment, 'metadata') && !empty($payment->metadata)) {
                                $metadata = json_decode($payment->metadata, true);
                                if (is_array($metadata) && isset($metadata['email'])) {
                                    $email = $metadata['email'];
                                }
                            }

                            // メールアドレスを表示
                            if (!empty($email)) {
                                echo esc_html($email);
                            } else {
                                echo '（メールなし）';
                            }
                            ?>
                        </td>
                        <td class="column-type">
                            <?php echo empty($payment->subscription_id) ? '買い切り' : 'サブスク'; ?>
                        </td>
                        <td class="column-details">
                            <?php
                            if (!empty($payment->subscription_id)) {
                                // サブスクリプションの場合
                                echo '<a href="' . admin_url('admin.php?page=edel-square-payment-pro-edit-subscription&subscription_id=' . $payment->subscription_id) . '">' . esc_html($payment->subscription_id) . '</a>';
                            } else {
                                // 買い切りの場合、商品名を表示
                                $item_name = '';
                                if (property_exists($payment, 'item_name') && !empty($payment->item_name)) {
                                    $item_name = $payment->item_name;
                                } else if (property_exists($payment, 'metadata') && !empty($payment->metadata)) {
                                    // メタデータからitem_nameを取得
                                    $metadata = json_decode($payment->metadata, true);
                                    if (is_array($metadata) && isset($metadata['item_name'])) {
                                        $item_name = $metadata['item_name'];
                                    }
                                }
                                echo !empty($item_name) ? esc_html($item_name) : '（商品名なし）';
                            }
                            ?>
                        </td>
                        <td class="column-amount">
                            <?php
                            echo number_format($payment->amount);
                            // 通貨表示を修正
                            if ($payment->currency === 'JPY') {
                                echo ' 円';
                            } else {
                                echo ' ' . esc_html($payment->currency);
                            }
                            ?>
                        </td>
                        <td class="column-date"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->created_at)); ?></td>
                        <td class="column-status">
                            <?php
                            $status_text = '';
                            switch ($payment->status) {
                                case 'SUCCESS':
                                case 'COMPLETED':
                                    $status_text = '成功';
                                    break;
                                case 'FAILED':
                                    $status_text = '失敗';
                                    break;
                                default:
                                    $status_text = $payment->status;
                            }
                            echo $status_text;
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-id">決済ID</th>
                <th scope="col" class="manage-column column-email">メールアドレス</th>
                <th scope="col" class="manage-column column-type">種別</th>
                <th scope="col" class="manage-column column-details">サブスクID／商品名</th>
                <th scope="col" class="manage-column column-amount">金額</th>
                <th scope="col" class="manage-column column-date">決済日</th>
                <th scope="col" class="manage-column column-status">ステータス</th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php echo esc_html($total_items); ?> 件</span>
                <span class="pagination-links">
                    <?php
                    // 最初のページへのリンク
                    if ($current_page > 1) {
                        echo '<a class="first-page button" href="' . esc_url(add_query_arg('paged', 1)) . '"><span class="screen-reader-text">最初のページ</span><span aria-hidden="true">&laquo;</span></a>';
                    } else {
                        echo '<span class="first-page button disabled"><span class="screen-reader-text">最初のページ</span><span aria-hidden="true">&laquo;</span></span>';
                    }

                    // 前のページへのリンク
                    if ($current_page > 1) {
                        echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '"><span class="screen-reader-text">前のページ</span><span aria-hidden="true">&lsaquo;</span></a>';
                    } else {
                        echo '<span class="prev-page button disabled"><span class="screen-reader-text">前のページ</span><span aria-hidden="true">&lsaquo;</span></span>';
                    }

                    // ページ番号の表示
                    echo '<span class="paging-input">' . $current_page . ' / <span class="total-pages">' . $total_pages . '</span></span>';

                    // 次のページへのリンク
                    if ($current_page < $total_pages) {
                        echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '"><span class="screen-reader-text">次のページ</span><span aria-hidden="true">&rsaquo;</span></a>';
                    } else {
                        echo '<span class="next-page button disabled"><span class="screen-reader-text">次のページ</span><span aria-hidden="true">&rsaquo;</span></span>';
                    }

                    // 最後のページへのリンク
                    if ($current_page < $total_pages) {
                        echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages)) . '"><span class="screen-reader-text">最後のページ</span><span aria-hidden="true">&raquo;</span></a>';
                    } else {
                        echo '<span class="last-page button disabled"><span class="screen-reader-text">最後のページ</span><span aria-hidden="true">&raquo;</span></span>';
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['processed'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                $success = isset($_GET['success']) ? intval($_GET['success']) : 0;
                $failed = isset($_GET['failed']) ? intval($_GET['failed']) : 0;

                printf(
                    'サブスクリプション決済処理が完了しました。処理対象: %d, 成功: %d, 失敗: %d',
                    $count,
                    $success,
                    $failed
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
</div>