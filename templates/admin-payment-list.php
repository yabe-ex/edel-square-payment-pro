<div class="wrap">
    <h1>決済一覧</h1>

    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(EDEL_SQUARE_PAYMENT_PRO_SLUG); ?>">

                <select name="status">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="絞り込み">
            </form>
        </div>

        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php echo sprintf(_n('%s件中 %s件', '%s件中 %s件', $total_payments), number_format_i18n($total_payments), number_format_i18n(count($payments))); ?></span>

                <span class="pagination-links">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ));

                    echo $page_links;
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>商品名</th>
                <th>金額</th>
                <th>ステータス</th>
                <th>購入者</th>
                <th>日時</th>
                <th>決済ID</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="8">決済データがありません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <?php
                    $user_info = '';
                    if (!empty($payment['user_id'])) {
                        $user = get_userdata($payment['user_id']);
                        if ($user) {
                            $user_info = '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . $user->ID)) . '">' . esc_html($user->display_name) . '</a><br>' . esc_html($user->user_email);
                        }
                    } elseif (!empty($payment['metadata']) && is_array($payment['metadata']) && !empty($payment['metadata']['email'])) {
                        $user_info = esc_html($payment['metadata']['email']);
                    }

                    $status_label = isset($statuses[$payment['status']]) ? $statuses[$payment['status']] : $payment['status'];
                    $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment['created_at']));

                    // 返金ボタンを表示するかどうか
                    $can_refund = in_array($payment['status'], array('APPROVED', 'COMPLETED')) && !strpos($payment['status'], 'REFUND');
                    ?>
                    <tr>
                        <td><?php echo esc_html($payment['id']); ?></td>
                        <td><?php echo esc_html($payment['item_name']); ?></td>
                        <td><?php echo number_format($payment['amount']); ?>円</td>
                        <td><?php echo esc_html($status_label); ?></td>
                        <td><?php echo $user_info; ?></td>
                        <td><?php echo esc_html($date); ?></td>
                        <td>
                            <code><?php echo esc_html($payment['payment_id']); ?></code>
                            <?php if (!empty($payment['customer_id'])): ?>
                                <br>
                                <small>顧客ID: <?php echo esc_html($payment['customer_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($can_refund): ?>
                                <button type="button" class="button edel-square-refund-button" data-payment-id="<?php echo esc_attr($payment['payment_id']); ?>" data-amount="<?php echo esc_attr($payment['amount']); ?>">返金</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php echo sprintf(_n('%s件中 %s件', '%s件中 %s件', $total_payments), number_format_i18n($total_payments), number_format_i18n(count($payments))); ?></span>

                <span class="pagination-links">
                    <?php echo $page_links; ?>
                </span>
            <?php endif; ?>
        </div>
        <br class="clear">
    </div>
</div>

<!-- 返金モーダル -->
<div id="edel-square-refund-modal" style="display: none;">
    <div class="edel-square-modal-backdrop"></div>
    <div class="edel-square-modal-content">
        <h2>返金処理</h2>

        <p>以下の決済を返金します。</p>

        <form id="edel-square-refund-form">
            <input type="hidden" id="edel-square-refund-payment-id" name="payment_id" value="">

            <div class="edel-square-form-row">
                <label for="edel-square-refund-amount">返金金額</label>
                <input type="number" id="edel-square-refund-amount" name="amount" min="1" required>
            </div>

            <div class="edel-square-form-row">
                <label for="edel-square-refund-reason">返金理由</label>
                <textarea id="edel-square-refund-reason" name="reason" rows="3"></textarea>
            </div>

            <div class="edel-square-form-row" id="edel-square-refund-status"></div>

            <div class="edel-square-form-actions">
                <button type="button" class="button" id="edel-square-refund-cancel">キャンセル</button>
                <button type="submit" class="button button-primary" id="edel-square-refund-submit">返金処理を実行</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function($) {
        // 返金モーダルの表示
        $('.edel-square-refund-button').on('click', function() {
            var paymentId = $(this).data('payment-id');
            var amount = $(this).data('amount');

            $('#edel-square-refund-payment-id').val(paymentId);
            $('#edel-square-refund-amount').val(amount).attr('max', amount);
            $('#edel-square-refund-status').html('');

            $('#edel-square-refund-modal').show();
        });

        // モーダルを閉じる
        $('#edel-square-refund-cancel, .edel-square-modal-backdrop').on('click', function() {
            $('#edel-square-refund-modal').hide();
        });

        // 返金処理の実行
        $('#edel-square-refund-form').on('submit', function(e) {
            e.preventDefault();

            if (!confirm(edelSquareAdminParams.i18n.confirmRefund)) {
                return false;
            }

            var $submitButton = $('#edel-square-refund-submit');
            $submitButton.prop('disabled', true);

            $('#edel-square-refund-status').html('<div class="notice notice-warning inline"><p>' + edelSquareAdminParams.i18n.loading + '</p></div>');

            $.ajax({
                url: edelSquareAdminParams.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'edel_square_process_refund',
                    nonce: edelSquareAdminParams.nonce,
                    payment_id: $('#edel-square-refund-payment-id').val(),
                    amount: $('#edel-square-refund-amount').val(),
                    reason: $('#edel-square-refund-reason').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#edel-square-refund-status').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');

                        // 1秒後にページを再読み込み
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $('#edel-square-refund-status').html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        $submitButton.prop('disabled', false);
                    }
                },
                error: function() {
                    $('#edel-square-refund-status').html('<div class="notice notice-error inline"><p>' + edelSquareAdminParams.i18n.error + '</p></div>');
                    $submitButton.prop('disabled', false);
                }
            });
        });
    })(jQuery);
</script>

<style>
    .edel-square-modal-backdrop {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 100050;
    }

    .edel-square-modal-content {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #fff;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        z-index: 100051;
        width: 90%;
        max-width: 500px;
    }

    .edel-square-form-row {
        margin-bottom: 15px;
    }

    .edel-square-form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .edel-square-form-row input,
    .edel-square-form-row textarea {
        width: 100%;
    }

    .edel-square-form-actions {
        text-align: right;
    }
</style>