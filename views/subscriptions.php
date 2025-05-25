<?php
// 直接アクセス禁止
defined('ABSPATH') || exit;

// ページネーション用の設定
$per_page = EdelSquarePaymentProDB::get_subscriptions_per_page(); // フィルターフックから件数を取得
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// サブスクリプション一覧を取得
require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
$total_items = EdelSquarePaymentProDB::count_subscriptions(); // 全件数取得用メソッド
$subscriptions = EdelSquarePaymentProDB::get_subscriptions($per_page, $offset);

// 全ページ数を計算
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">サブスクリプション一覧</h1>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>サブスクリプションが更新されました。</p>
        </div>
    <?php endif; ?>

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
                <th scope="col" class="manage-column column-id">ID</th>
                <th scope="col" class="manage-column column-email">メールアドレス</th>
                <th scope="col" class="manage-column column-plan">プラン</th>
                <th scope="col" class="manage-column column-amount">金額</th>
                <th scope="col" class="manage-column column-start-date">開始日</th>
                <th scope="col" class="manage-column column-next-billing">次回請求日</th>
                <th scope="col" class="manage-column column-status">ステータス</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($subscriptions)): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="7">サブスクリプションが見つかりません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td class="column-id">
                            <strong><a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-edit-subscription&subscription_id=' . $subscription->subscription_id); ?>"><?php echo esc_html($subscription->subscription_id); ?></a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-edit-subscription&subscription_id=' . $subscription->subscription_id); ?>">詳細</a> | </span>
                                <span class="cancel"><a href="<?php echo wp_nonce_url(admin_url('admin.php?page=edel-square-payment-pro-subscriptions&action=cancel&id=' . $subscription->subscription_id), 'cancel-subscription-' . $subscription->subscription_id); ?>" class="submitdelete">キャンセル</a></span>
                            </div>
                        </td>
                        <td class="column-email">
                            <?php
                            // メールアドレスを取得
                            $email = '';
                            // 1. ユーザーからメールアドレスを取得
                            if (!empty($subscription->user_id)) {
                                $user = get_userdata($subscription->user_id);
                                if ($user && !empty($user->user_email)) {
                                    $email = $user->user_email;
                                }
                            }

                            // 2. メタデータからメールアドレスを取得（ユーザーに紐づいていない場合）
                            if (empty($email) && !empty($subscription->metadata)) {
                                $metadata = json_decode($subscription->metadata, true);
                                if (is_array($metadata) && isset($metadata['email'])) {
                                    $email = $metadata['email'];
                                }
                            }

                            echo !empty($email) ? esc_html($email) : '（メールなし）';
                            ?>
                        </td>
                        <td class="column-plan">
                            <?php
                            $plan = EdelSquarePaymentProDB::get_plan($subscription->plan_id);
                            echo $plan ? esc_html($plan['name']) : esc_html($subscription->plan_id);
                            ?>
                        </td>
                        <td class="column-amount">
                            <?php
                            echo number_format($subscription->amount);
                            // 通貨表示を修正
                            if ($subscription->currency === 'JPY') {
                                echo ' 円';
                            } else {
                                echo ' ' . esc_html($subscription->currency);
                            }
                            ?>
                        </td>
                        <td class="column-start-date"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->current_period_start)); ?></td>
                        <td class="column-next-billing"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->next_billing_date)); ?></td>
                        <td class="column-status">
                            <?php
                            $status_text = '';
                            switch ($subscription->status) {
                                case 'ACTIVE':
                                    $status_text = '有効';
                                    break;
                                case 'CANCELED':
                                    $status_text = 'キャンセル済み';
                                    break;
                                case 'CANCELING':
                                    $status_text = 'キャンセル予定';
                                    break;
                                case 'PAST_DUE':
                                    $status_text = '支払い遅延';
                                    break;
                                default:
                                    $status_text = $subscription->status;
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
                <th scope="col" class="manage-column column-id">ID</th>
                <th scope="col" class="manage-column column-email">メールアドレス</th>
                <th scope="col" class="manage-column column-plan">プラン</th>
                <th scope="col" class="manage-column column-amount">金額</th>
                <th scope="col" class="manage-column column-start-date">開始日</th>
                <th scope="col" class="manage-column column-next-billing">次回請求日</th>
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
</div>