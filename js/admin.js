jQuery(document).ready(function ($) {
    'use strict';

    // 初期化
    initSubscriptionFilter();

    function initSubscriptionFilter() {
        // Cookie から設定を読み込み
        const showCancelled = getCookie('edel_square_show_cancelled') !== 'false';
        $('#show-cancelled-subscriptions').prop('checked', showCancelled);

        // ステータス列にクラスを追加
        addStatusClasses();

        // 初期表示を設定
        toggleCancelledRows(showCancelled);

        // カウント更新と縞模様初期化
        updateCounts();

        // チェックボックスイベント
        $('#show-cancelled-subscriptions').on('change', function () {
            const isChecked = $(this).is(':checked');
            toggleCancelledRows(isChecked);
            setCookie('edel_square_show_cancelled', isChecked, 365); // 1年間保存
            updateCounts();
        });
    }

    function addStatusClasses() {
        $('.subscription-row').each(function () {
            const $row = $(this);
            const status = $row.attr('data-status');
            const $statusCell = $row.find('td').eq(4); // ステータス列（5番目）

            // ステータスに基づいてクラスを追加
            switch (status) {
                case 'CANCELED':
                    $statusCell.addClass('status-canceled');
                    break;
                case 'ACTIVE':
                    $statusCell.addClass('status-active');
                    break;
                case 'CANCELING':
                    $statusCell.addClass('status-canceling');
                    break;
                case 'PAST_DUE':
                    $statusCell.addClass('status-past-due');
                    break;
            }
        });
    }

    function toggleCancelledRows(show) {
        const $cancelledRows = $('.subscription-row[data-status="CANCELED"]');

        if (show) {
            $cancelledRows.removeClass('hidden');
        } else {
            $cancelledRows.addClass('hidden');
        }
    }

    function updateCounts() {
        const activeCount = $('.subscription-row[data-status="ACTIVE"]:visible').length;
        const cancelledTotal = $('.subscription-row[data-status="CANCELED"]').length;
        const cancelledVisible = $('.subscription-row[data-status="CANCELED"]:visible').length;

        $('#active-count').text(activeCount);

        if (cancelledVisible === cancelledTotal) {
            $('#cancelled-count').text(cancelledTotal);
        } else {
            $('#cancelled-count').text(cancelledVisible + '/' + cancelledTotal);
        }

        // 縞模様を再計算
        updateTableStripes();
    }

    function updateTableStripes() {
        // 全ての行からストライプクラスを削除
        $('.subscription-row').removeClass('edel-stripe-even edel-stripe-odd');

        // 表示されている行のみを取得してストライプを適用
        const $visibleRows = $('.subscription-row:visible');

        $visibleRows.each(function (visualIndex) {
            const $row = $(this);

            // デバッグログ（本番では削除可能）
            // console.log('行' + (visualIndex + 1) + ':', visualIndex % 2 === 0 ? 'even' : 'odd');

            if (visualIndex % 2 === 0) {
                $row.addClass('edel-stripe-even');
                $row.removeClass('edel-stripe-odd');
            } else {
                $row.addClass('edel-stripe-odd');
                $row.removeClass('edel-stripe-even');
            }
        });

        // 強制的にスタイルを再描画
        $visibleRows.each(function () {
            this.offsetHeight; // リフロー強制
        });
    }

    // Cookie 操作
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }

    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
});
