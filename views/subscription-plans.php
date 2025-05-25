<?php
// 直接アクセス禁止
defined('ABSPATH') || exit;

// ページネーション用の設定
$per_page = EdelSquarePaymentProDB::get_plans_per_page(); // フィルターフックから件数を取得
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// プラン一覧を取得
require_once EDEL_SQUARE_PAYMENT_PRO_PATH . '/inc/class-db.php';
$total_items = EdelSquarePaymentProDB::count_plans(); // プラン総数
$plans = EdelSquarePaymentProDB::get_plans($per_page, $offset);

// 全ページ数を計算
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">サブスクリプションプラン</h1>
    <a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-add-plan'); ?>" class="page-title-action">新規追加</a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>プランが更新されました。</p>
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

    <table class="wp-list-table widefat fixed striped posts">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name">プラン名</th>
                <th scope="col" class="manage-column column-amount">金額</th>
                <th scope="col" class="manage-column column-billing-cycle">請求サイクル</th>
                <th scope="col" class="manage-column column-trial">トライアル期間</th>
                <th scope="col" class="manage-column column-status">ステータス</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($plans)): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="5">プランが見つかりません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td class="column-name">
                            <strong><a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-edit-plan&id=' . $plan['plan_id']); ?>"><?php echo esc_html($plan['name']); ?></a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo admin_url('admin.php?page=edel-square-payment-pro-edit-plan&id=' . $plan['plan_id']); ?>">編集</a> | </span>
                                <span class="trash"><a href="<?php echo wp_nonce_url(admin_url('admin.php?page=edel-square-payment-pro-plans&action=delete&id=' . $plan['plan_id']), 'delete-plan-' . $plan['plan_id']); ?>" class="submitdelete">削除</a></span>
                            </div>
                        </td>
                        <td class="column-amount">
                            <?php
                            echo number_format($plan['amount']);
                            // 通貨表示を修正
                            if ($plan['currency'] === 'JPY') {
                                echo ' 円';
                            } else {
                                echo ' ' . esc_html($plan['currency'] ?: 'JPY');
                            }
                            ?>
                        </td>
                        <td class="column-billing-cycle">
                            <?php
                            $cycle_text = '';
                            switch ($plan['billing_cycle']) {
                                case 'DAILY':
                                    $cycle_text = '毎日';
                                    break;
                                case 'WEEKLY':
                                    $cycle_text = '毎週';
                                    break;
                                case 'MONTHLY':
                                    $cycle_text = '毎月';
                                    break;
                                case 'YEARLY':
                                    $cycle_text = '毎年';
                                    break;
                                default:
                                    $cycle_text = $plan['billing_cycle'];
                            }
                            echo $cycle_text;
                            if ($plan['billing_interval'] > 1) {
                                echo ' (' . $plan['billing_interval'] . ')';
                            }
                            ?>
                        </td>
                        <td class="column-trial">
                            <?php echo empty($plan['trial_period_days']) ? 'なし' : $plan['trial_period_days'] . '日間'; ?>
                        </td>
                        <td class="column-status">
                            <?php echo $plan['status'] === 'ACTIVE' ? '有効' : '無効'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name">プラン名</th>
                <th scope="col" class="manage-column column-amount">金額</th>
                <th scope="col" class="manage-column column-billing-cycle">請求サイクル</th>
                <th scope="col" class="manage-column column-trial">トライアル期間</th>
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