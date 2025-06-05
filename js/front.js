jQuery(document).ready(function ($) {
    'use strict';

    // OneTime決済フォーム処理
    if ($('#edel-square-submit').length > 0) {
        // Square Web Payments SDKの初期化
        let payments;
        let appId = edelSquarePaymentParams.appId;
        let locationId = edelSquarePaymentParams.locationId;
        let card;

        // 自動填サポート（Linkのトグル）
        let autocomplete = true;

        try {
            payments = window.Square.payments(appId, locationId);

            // カード入力フィールドの初期化
            initializeCard();
        } catch (e) {
            console.error('Square Web Payments SDK initialization error:', e);
            showError('決済システムの初期化に失敗しました。');
        }

        // カード入力フィールドの初期化関数
        async function initializeCard() {
            try {
                console.log('カード入力フィールドの初期化を開始します...');

                const cardContainer = document.getElementById('card-container');
                if (cardContainer) {
                    cardContainer.innerHTML = '';
                }

                // DOMが正しく読み込まれているか確認
                if (!document.getElementById('card-container')) {
                    console.error('card-container要素が見つかりません！');
                    return;
                }

                card = await payments.card({
                    style: {
                        input: {
                            color: '#333333',
                            fontSize: '16px',
                            fontFamily: 'sans-serif'
                        },
                        'input.is-focus': {
                            color: '#000000'
                        },
                        'input.is-error': {
                            color: '#cc0023'
                        }
                    }
                });
                console.log('カードオブジェクトが作成されました。アタッチを開始します...');
                await card.attach('#card-container');
                console.log('カードフォームが正常にアタッチされました！');

                // 自動填トグルボタン
                $('#toggle-button').on('click', function () {
                    autocomplete = !autocomplete;
                    card.configure({
                        autocomplete: autocomplete
                    });
                    $(this)
                        .find('.link')
                        .text(autocomplete ? 'link' : 'unlink');
                });

                // 決済ボタンのクリックイベント
                $('#edel-square-submit').on('click', async function () {
                    await handlePaymentMethodSubmission(this);
                });
            } catch (e) {
                console.error('Square card initialization error:', e);
                showError('カード入力フィールドの初期化に失敗しました。');
            }
        }

        // 決済処理関数
        async function handlePaymentMethodSubmission(buttonElement) {
            try {
                // 入力値検証
                const emailInput = $('#edel-square-email').val();
                const amount = $(buttonElement).data('amount');
                const itemName = $(buttonElement).data('item-name');

                if (!emailInput || !emailInput.trim()) {
                    showError('メールアドレスを入力してください。');
                    return;
                }

                if (!validateEmail(emailInput)) {
                    showError('有効なメールアドレスを入力してください。');
                    return;
                }

                // 名前の取得（フォームにフィールドがある場合）
                let firstName = '';
                let lastName = '';

                if ($('#edel-square-first-name').length > 0) {
                    firstName = $('#edel-square-first-name').val();
                    if (!firstName || !firstName.trim()) {
                        showError('名を入力してください。');
                        return;
                    }
                }

                if ($('#edel-square-last-name').length > 0) {
                    lastName = $('#edel-square-last-name').val();
                    if (!lastName || !lastName.trim()) {
                        showError('姓を入力してください。');
                        return;
                    }
                }

                // 同意チェック
                if ($('#edel-square-consent').length > 0 && !$('#edel-square-consent').is(':checked')) {
                    showError('利用規約とプライバシーポリシーに同意してください。');
                    return;
                }

                // ボタンを無効化
                $(buttonElement).prop('disabled', true);
                showMessage('処理中...');

                // カード情報からトークンを取得
                const result = await card.tokenize();

                if (result.status === 'OK') {
                    // サーバーサイドでの決済処理
                    processOneTimePayment(result.token, amount, emailInput, itemName, firstName, lastName);
                } else {
                    let errorMessage = 'カード情報の処理中にエラーが発生しました。';
                    if (result.errors && result.errors.length > 0) {
                        errorMessage = result.errors[0].message;
                    }
                    showError(errorMessage);
                    $(buttonElement).prop('disabled', false);
                }
            } catch (e) {
                console.error('Payment tokenization error:', e);
                showError('決済処理中にエラーが発生しました。');
                $(buttonElement).prop('disabled', false);
            }
        }

        // 買い切り決済専用のサーバーサイド処理関数
        function processOneTimePayment(token, amount, email, itemName, firstName, lastName) {
            // 新しいアクション名を使用して買い切り決済処理を呼び出し
            $.ajax({
                url: edelSquarePaymentParams.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'edel_square_process_onetime_payment_ajax', // 買い切り専用アクション
                    nonce: edelSquarePaymentParams.nonce,
                    payment_token: token,
                    amount: amount,
                    email: email,
                    item_name: itemName,
                    first_name: firstName,
                    last_name: lastName
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        console.log('買い切り決済成功');

                        // 決済フォームを非表示
                        $('.edel-square-payment-form').hide();

                        // メッセージのHTMLを構築（サブスクリプション決済と同じ方式）
                        let successHtml = '';

                        // メッセージ本文（responseの構造に合わせて修正）
                        if (response.data.message) {
                            successHtml += response.data.message;
                        } else {
                            successHtml += 'ご購入ありがとうございます。決済が完了しました。';
                        }

                        // リダイレクト情報を追加
                        if (response.data.redirect_url) {
                            successHtml += '<p class="redirect-info">3秒後にマイアカウントページへ移動します...</p>';
                        }

                        // 成功メッセージを表示
                        $('#edel-square-success-message').html(successHtml).show();

                        // リダイレクト先がある場合
                        if (response.data.redirect_url) {
                            console.log('リダイレクト先:', response.data.redirect_url);
                            setTimeout(function () {
                                window.location.href = response.data.redirect_url;
                            }, 3000);
                        }
                    } else {
                        showError(response.data || '決済処理に失敗しました。');
                        $('#edel-square-submit').prop('disabled', false);
                    }
                },
                error: function () {
                    showError('サーバーとの通信に失敗しました。');
                    $('#edel-square-submit').prop('disabled', false);
                }
            });
        }

        // サーバーサイドでの決済処理関数（既存のサブスクリプションコード用・互換性維持）
        function processPayment(token, amount, email, itemName) {
            $.ajax({
                url: edelSquarePaymentParams.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'edel_square_process_payment',
                    nonce: edelSquarePaymentParams.nonce,
                    payment_token: token,
                    amount: amount,
                    email: email,
                    item_name: itemName
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        console.log('買い切り決済成功');

                        // 決済フォームを非表示
                        $('.edel-square-payment-form').hide();

                        // メッセージのHTMLを構築（サブスクリプション決済と同じ方式）
                        let successHtml = '';

                        // メッセージ本文
                        if (response.data.message) {
                            successHtml += response.data.message;
                        } else {
                            successHtml += 'ご購入ありがとうございます。決済が完了しました。';
                        }

                        // リダイレクト情報を追加
                        if (response.data.redirect_url) {
                            successHtml += '<p class="redirect-info">3秒後にマイアカウントページへ移動します...</p>';
                        }

                        // 成功メッセージを表示
                        $('#edel-square-success-message').html(successHtml).show();

                        // リダイレクト先がある場合
                        if (response.data.redirect_url) {
                            console.log('リダイレクト先:', response.data.redirect_url);
                            setTimeout(function () {
                                window.location.href = response.data.redirect_url;
                            }, 3000);
                        }
                    } else {
                        showError(response.data || '決済処理に失敗しました。');
                        $('#edel-square-submit').prop('disabled', false);
                    }
                },
                error: function () {
                    showError('サーバーとの通信に失敗しました。');
                    $('#edel-square-submit').prop('disabled', false);
                }
            });
        }

        // エラーメッセージ表示関数
        function showError(message) {
            $('#edel-square-payment-status').html('<div class="edel-square-error">' + message + '</div>');
        }

        // メッセージ表示関数
        function showMessage(message) {
            $('#edel-square-payment-status').html('<div class="edel-square-message">' + message + '</div>');
        }

        // メールアドレス検証関数
        function validateEmail(email) {
            const re =
                /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
    }

    // サブスクリプションフォーム処理
    if ($('#edel-square-subscription-submit').length > 0) {
        // Square Web Payments SDKの初期化
        let payments;
        let appId = edelSquarePaymentParams.appId;
        let locationId = edelSquarePaymentParams.locationId;
        let card;

        try {
            payments = window.Square.payments(appId, locationId);

            // カード入力フィールドの初期化
            initializeCard();
        } catch (e) {
            console.error('Square Web Payments SDK initialization error:', e);
            showSubscriptionError('決済システムの初期化に失敗しました。');
        }

        // カード入力フィールドの初期化関数
        async function initializeCard() {
            try {
                console.log('サブスクリプション用カード入力フィールドの初期化を開始します...');

                const cardContainer = document.getElementById('card-container');
                if (cardContainer) {
                    cardContainer.innerHTML = '';
                }

                // DOMが正しく読み込まれているか確認
                if (!document.getElementById('card-container')) {
                    console.error('card-container要素が見つかりません！');
                    return;
                }

                card = await payments.card({
                    style: {
                        input: {
                            color: '#333333',
                            fontSize: '16px',
                            fontFamily: 'sans-serif'
                        },
                        'input.is-focus': {
                            color: '#000000'
                        },
                        'input.is-error': {
                            color: '#cc0023'
                        }
                    }
                });
                console.log('カードオブジェクトが作成されました。アタッチを開始します...');
                await card.attach('#card-container');
                console.log('カードフォームが正常にアタッチされました！');

                // サブスクリプション登録ボタンのクリックイベント
                $('#edel-square-subscription-submit').on('click', async function () {
                    await handleSubscriptionSubmission(this);
                });
            } catch (e) {
                console.error('Square card initialization error:', e);
                showSubscriptionError('カード入力フォームの初期化に失敗しました。');
            }
        }

        // サブスクリプション登録処理関数
        async function handleSubscriptionSubmission(buttonElement) {
            try {
                // 入力値検証
                const emailInput = $('#edel-square-sub-email').val();
                const planId = $(buttonElement).data('plan-id');
                const amount = $(buttonElement).data('amount');
                const itemName = $(buttonElement).data('item-name');
                const billingCycle = $(buttonElement).data('billing-cycle');
                const billingInterval = $(buttonElement).data('billing-interval');
                const trialDays = $(buttonElement).data('trial-days');

                // デバッグ出力
                console.log('サブスクリプション処理開始:', {
                    email: emailInput,
                    planId: planId,
                    amount: amount,
                    itemName: itemName,
                    billingCycle: billingCycle,
                    billingInterval: billingInterval,
                    trialDays: trialDays
                });

                if (!emailInput || !emailInput.trim()) {
                    showSubscriptionError('メールアドレスを入力してください。');
                    return;
                }

                if (!validateEmail(emailInput)) {
                    showSubscriptionError('有効なメールアドレスを入力してください。');
                    return;
                }

                // 同意チェック
                if ($('#edel-square-consent').length > 0 && !$('#edel-square-consent').is(':checked')) {
                    showSubscriptionError('利用規約とプライバシーポリシーに同意してください。');
                    return;
                }

                // ボタンを無効化
                $(buttonElement).prop('disabled', true);
                showSubscriptionMessage('処理中...');

                // カード情報からトークンを取得
                const result = await card.tokenize();

                if (result.status === 'OK') {
                    // サーバーサイドでのサブスクリプション処理
                    console.log('processSubscriptionの処理に入ります。');
                    processSubscription(result.token, emailInput, planId, amount, itemName, billingCycle, billingInterval, trialDays);
                } else {
                    let errorMessage = 'カード情報の処理中にエラーが発生しました。';
                    if (result.errors && result.errors.length > 0) {
                        errorMessage = result.errors[0].message;
                    }
                    showSubscriptionError(errorMessage);
                    $(buttonElement).prop('disabled', false);
                }
            } catch (e) {
                console.error('Subscription tokenization error:', e);
                showSubscriptionError('サブスクリプション処理中にエラーが発生しました。');
                $(buttonElement).prop('disabled', false);
            }
        }

        // サーバーサイドでのサブスクリプション処理関数
        function processSubscription(token, email, planId, amount, itemName, billingCycle, billingInterval, trialDays) {
            console.log('AJAX送信開始:', {
                token: token ? 'トークン有り' : 'トークン無し',
                email: email,
                action: 'edel_square_process_subscription',
                nonce: edelSquarePaymentParams.nonce ? 'nonce有り' : 'nonce無し'
            });

            // AJAX呼び出しデータを準備
            const data = {
                action: 'edel_square_process_subscription',
                nonce: edelSquarePaymentParams.nonce,
                payment_token: token,
                email: email,
                item_name: itemName,
                amount: amount
            };

            // プランID指定があれば追加
            if (planId) {
                data.plan_id = planId;
            }

            // 課金サイクル関連
            data.billing_cycle = billingCycle;
            data.billing_interval = billingInterval;
            data.trial_days = trialDays;

            console.log('送信データ:', data);

            $.ajax({
                url: edelSquarePaymentParams.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'edel_square_process_subscription',
                    nonce: edelSquarePaymentParams.nonce,
                    payment_token: token,
                    email: email,
                    plan_id: planId,
                    amount: amount,
                    item_name: itemName,
                    billing_cycle: billingCycle,
                    billing_interval: billingInterval,
                    trial_days: trialDays
                },
                dataType: 'json',
                success: function (response) {
                    console.log('Subscription API response:', response);

                    if (response.success) {
                        console.log('サブスクリプション成功');

                        // サブスクリプションフォームを非表示
                        $('.edel-square-subscription-form').hide();

                        // メッセージのHTMLを構築
                        let successHtml = '';

                        // メッセージ本文（responseの構造に合わせて修正）
                        if (response.message) {
                            successHtml += response.message;
                        } else {
                            successHtml += 'サブスクリプションの登録が完了しました。';
                        }

                        // リダイレクト情報を追加
                        if (response.redirect_url) {
                            successHtml += '<p class="redirect-info">3秒後にマイアカウントページへ移動します...</p>';
                        }

                        // 成功メッセージを表示
                        $('#edel-square-subscription-success').html(successHtml).show();

                        // リダイレクト処理
                        if (response.redirect_url) {
                            console.log('リダイレクト先:', response.redirect_url);
                            setTimeout(function () {
                                window.location.href = response.redirect_url;
                            }, 3000);
                        } else {
                            console.log('リダイレクト先が指定されていません');
                        }
                    } else {
                        // エラーメッセージの取得
                        let errorMsg = 'サブスクリプション処理に失敗しました。';

                        // responseの構造に合わせて条件分岐
                        if (response.message) {
                            errorMsg = response.message;
                        } else if (response.data && typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }

                        console.log('エラー:', errorMsg);
                        showSubscriptionError(errorMsg);
                        $('#edel-square-subscription-submit').prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX通信エラー:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    showSubscriptionError('サーバーとの通信に失敗しました。');
                    $('#edel-square-subscription-submit').prop('disabled', false);
                }
            });
        }

        // サブスクリプションエラーメッセージ表示関数
        function showSubscriptionError(message) {
            $('#edel-square-subscription-status').html('<div class="edel-square-error">' + message + '</div>');
        }

        // サブスクリプションメッセージ表示関数
        function showSubscriptionMessage(message) {
            $('#edel-square-subscription-status').html('<div class="edel-square-message">' + message + '</div>');
        }

        // メールアドレス検証関数
        function validateEmail(email) {
            const re =
                /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
    }

    // サブスクリプション管理機能
    if ($('.edel-square-subscription-management').length > 0) {
        // サブスクリプションのキャンセルボタンのイベント
        $('.edel-square-cancel-subscription').on('click', function (e) {
            e.preventDefault();
            if (confirm('本当にこのサブスクリプションをキャンセルしますか？')) {
                const subscriptionId = $(this).data('subscription-id');
                const cancelAtPeriodEnd = $(this).data('cancel-at-period-end');

                $.ajax({
                    url: edelSquareSubscriptionParams.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'edel_square_cancel_subscription',
                        nonce: edelSquareSubscriptionParams.nonce,
                        subscription_id: subscriptionId,
                        cancel_at_period_end: cancelAtPeriodEnd
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('エラーが発生しました: ' + response.data);
                        }
                    },
                    error: function () {
                        alert('サーバーとの通信に失敗しました。');
                    }
                });
            }
        });

        // 支払い方法更新ボタンのイベント
        $('.edel-square-update-payment-method').on('click', function (e) {
            e.preventDefault();
            const subscriptionId = $(this).data('subscription-id');

            // 支払い方法更新フォームの表示
            $('#edel-square-payment-method-form-' + subscriptionId).toggle();
        });
    }

    // ログインフォーム送信処理
    if ($('#edel-square-login-form').length > 0) {
        console.log('ログインフォームが見つかりました');

        // ログイン処理用のパラメータ確認
        if (typeof edelSquarePaymentParams === 'undefined') {
            console.error('edelSquarePaymentParams が定義されていません');
            // フォールバックとして空のオブジェクトを定義
            window.edelSquarePaymentParams = {
                ajaxUrl: '/wp-admin/admin-ajax.php'
            };
        }

        if (typeof edelSquareLoginParams === 'undefined') {
            console.error('edelSquareLoginParams が定義されていません');
            // フォールバックとして空のオブジェクトを定義
            window.edelSquareLoginParams = {
                nonce: ''
            };
        }

        // reCAPTCHA設定の確認
        var hasRecaptcha = typeof edelSquareRecaptchaParams !== 'undefined' && edelSquareRecaptchaParams.siteKey && typeof grecaptcha !== 'undefined';

        console.log('reCAPTCHA設定:', hasRecaptcha ? 'あり' : 'なし');

        // reCAPTCHAの初期化（存在する場合）
        if (hasRecaptcha) {
            grecaptcha.ready(function () {
                try {
                    grecaptcha
                        .execute(edelSquareRecaptchaParams.siteKey, { action: 'login' })
                        .then(function (token) {
                            console.log('reCAPTCHAトークン取得成功');
                            $('#edel-square-recaptcha-token').val(token);
                        })
                        .catch(function (error) {
                            console.error('reCAPTCHAトークン取得エラー:', error);
                        });
                } catch (e) {
                    console.error('reCAPTCHA実行エラー:', e);
                }
            });
        }

        // フォーム送信イベント
        $('#edel-square-login-form').on('submit', function (e) {
            e.preventDefault();

            var email = $('#edel-square-login-email').val();
            var password = $('#edel-square-login-password').val();

            // 入力チェック
            if (!email || !password) {
                $('#edel-square-login-message').addClass('error').html('メールアドレスとパスワードを入力してください。');
                return;
            }

            // ボタンを無効化して処理中表示
            $('#edel-square-login-button').prop('disabled', true).text('ログイン中...');
            $('#edel-square-login-message').removeClass('error success').html('ログイン処理中です...');

            // AJAX送信データの作成
            var data = {
                action: 'edel_square_process_login',
                email: email,
                password: password
            };

            // nonceを追加
            if (typeof edelSquareLoginParams !== 'undefined' && edelSquareLoginParams.nonce) {
                data.nonce = edelSquareLoginParams.nonce;
            } else if (typeof edelSquarePaymentParams !== 'undefined' && edelSquarePaymentParams.nonce) {
                data.nonce = edelSquarePaymentParams.nonce;
            }

            // reCAPTCHAトークンがある場合は追加
            if ($('#edel-square-recaptcha-token').length > 0) {
                data.recaptcha_token = $('#edel-square-recaptcha-token').val();
            }

            console.log('ログイン処理を開始します - AJAX URL:', edelSquarePaymentParams.ajaxUrl);

            // AJAX送信
            $.ajax({
                url: edelSquarePaymentParams.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    console.log('AJAX成功レスポンス:', response);

                    // レスポンス構造のチェック
                    if (response && response.success === true) {
                        // 成功の場合
                        $('#edel-square-login-message')
                            .removeClass('error')
                            .addClass('success')
                            .html(response.message || 'ログインに成功しました。');

                        // リダイレクト
                        if (response.redirect_url) {
                            console.log('リダイレクト先:', response.redirect_url);
                            $('#edel-square-login-message').append('<br>リダイレクトします...');

                            // 少し遅延してリダイレクト
                            setTimeout(function () {
                                window.location.href = response.redirect_url;
                            }, 1000);
                        } else {
                            // リダイレクト先がない場合はページをリロード
                            console.log('リダイレクト先が指定されていないためリロードします');
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        // エラーの場合
                        var errorMessage = 'ログインに失敗しました。';

                        // エラーメッセージの取得（レスポンス構造に応じて調整）
                        if (response && typeof response === 'object') {
                            if (response.message) {
                                errorMessage = response.message;
                            } else if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        }

                        console.log('ログインエラー:', errorMessage);
                        $('#edel-square-login-message').removeClass('success').addClass('error').html(errorMessage);
                        $('#edel-square-login-button').prop('disabled', false).text('ログイン');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX通信エラー:', status, error);
                    $('#edel-square-login-message').addClass('error').html('通信エラーが発生しました。しばらく経ってから再度お試しください。');
                    $('#edel-square-login-button').prop('disabled', false).text('ログイン');
                }
            });
        });
    }

    $('.card-update-scroll-link').on('click', function (e) {
        e.preventDefault();

        const targetId = $(this).data('target');
        const $target = $('#' + targetId);

        if ($target.length > 0) {
            // フォームが存在する場合はスムーズスクロール
            $('html, body').animate(
                {
                    scrollTop: $target.offset().top - 100
                },
                500,
                function () {
                    // スクロール完了後にフォームをハイライト
                    $target.addClass('highlight-form');
                    setTimeout(function () {
                        $target.removeClass('highlight-form');
                    }, 2000);
                }
            );
        } else {
            // フォームが存在しない場合は警告
            alert('カード情報更新フォームが見つかりません。ページを更新してもう一度お試しください。');
        }
    });

    const $myAccount = $('#edel-square-myaccount');
    const $tabs = $myAccount.find('.edel-square-tab');
    const $tabContents = $myAccount.find('.edel-square-tab-content');

    // タブクリック処理
    $tabs.on('click', function (e) {
        e.preventDefault();

        const $clickedTab = $(this);
        const targetTab = $clickedTab.data('tab');
        const $targetContent = $('#tab-' + targetTab);

        // 既にアクティブなタブの場合は何もしない
        if ($clickedTab.hasClass('edel-square-tab-active')) {
            return;
        }

        // アニメーション中は操作を無効化
        if ($clickedTab.data('switching')) {
            return;
        }

        $clickedTab.data('switching', true);

        // 現在のアクティブコンテンツをフェードアウト
        const $currentContent = $tabContents.filter('.edel-square-tab-content-active');

        $currentContent.fadeOut(200, function () {
            // すべてのタブとコンテンツからアクティブクラスを除去
            $tabs.removeClass('edel-square-tab-active');
            $tabContents.removeClass('edel-square-tab-content-active');

            // 新しいタブとコンテンツをアクティブに
            $clickedTab.addClass('edel-square-tab-active');
            $targetContent.addClass('edel-square-tab-content-active');

            // 新しいコンテンツをフェードイン
            $targetContent.fadeIn(300, function () {
                $clickedTab.removeData('switching');

                // カスタムイベントを発火（他の機能で利用可能）
                $myAccount.trigger('tabChanged', [targetTab]);
            });
        });

        // URLハッシュを更新（ブラウザの戻るボタン対応）
        if (history.pushState) {
            const newUrl = window.location.pathname + window.location.search + '#' + targetTab;
            history.pushState({ tab: targetTab }, '', newUrl);
        }
    });

    // ブラウザの戻る/進むボタン対応
    $(window).on('popstate', function (e) {
        const hash = window.location.hash.replace('#', '');
        if (hash && $myAccount.find('.edel-square-tab[data-tab="' + hash + '"]').length > 0) {
            $myAccount.find('.edel-square-tab[data-tab="' + hash + '"]').click();
        }
    });

    // 初期化処理
    function initializeTabs() {
        // URLハッシュがある場合は該当タブをアクティブに
        const hash = window.location.hash.replace('#', '');
        let $activeTab;

        if (hash && $myAccount.find('.edel-square-tab[data-tab="' + hash + '"]').length > 0) {
            $activeTab = $myAccount.find('.edel-square-tab[data-tab="' + hash + '"]');
        } else {
            // ハッシュがない場合は既にアクティブなタブまたは最初のタブを使用
            $activeTab = $tabs.filter('.edel-square-tab-active');
            if ($activeTab.length === 0) {
                $activeTab = $tabs.first();
            }
        }

        // アクティブタブとコンテンツを設定
        const targetTab = $activeTab.data('tab');
        $tabs.removeClass('edel-square-tab-active');
        $tabContents.removeClass('edel-square-tab-content-active');
        $activeTab.addClass('edel-square-tab-active');
        $('#tab-' + targetTab)
            .addClass('edel-square-tab-content-active')
            .show();
    }

    // キーボードナビゲーション（オプション）
    $tabs.on('keydown', function (e) {
        const $current = $(this);
        let $next;

        switch (e.keyCode) {
            case 37: // 左矢印
                $next = $current.prev('.edel-square-tab');
                if ($next.length === 0) {
                    $next = $tabs.last();
                }
                break;
            case 39: // 右矢印
                $next = $current.next('.edel-square-tab');
                if ($next.length === 0) {
                    $next = $tabs.first();
                }
                break;
            case 13: // Enter
            case 32: // Space
                e.preventDefault();
                $current.click();
                return;
        }

        if ($next && $next.length > 0) {
            e.preventDefault();
            $next.focus().click();
        }
    });

    // タブにtabindex属性を追加（キーボードナビゲーション対応）
    $tabs.attr('tabindex', '0');

    // 初期化実行
    initializeTabs();

    // デバッグ用（開発時のみ使用）
    $myAccount.on('tabChanged', function (e, tabName) {
        console.log('Tab changed to:', tabName);
    });

    // カード更新処理の初期化（表示時初期化方式）
    function initializeCardUpdate() {
        console.log('カード更新処理を初期化中...');

        // Square Web Payments SDKの確認
        if (typeof window.Square === 'undefined') {
            console.error('Square Web Payments SDKが読み込まれていません');
            return;
        }

        // フォーム表示ボタンのクリックイベント
        $(document).on('click', '.show-card-form-button', function () {
            const button = $(this);
            const subscriptionId = button.data('subscription-id');
            const formContainer = $('#card-update-form-container-' + subscriptionId);

            console.log('カード更新フォームを表示:', subscriptionId);

            // ボタンを非表示にしてフォームを表示
            button.hide();
            formContainer.show();

            // エラーメッセージをクリア
            clearCardError(subscriptionId);

            // フォーム表示後にSquare Card Formを初期化
            setTimeout(function () {
                initializeSquareCardForm(subscriptionId);
            }, 100);
        });

        // キャンセルボタンのクリックイベント
        $(document).on('click', '.cancel-card-form-button', function () {
            const button = $(this);
            const subscriptionId = button.data('subscription-id');
            const formContainer = $('#card-update-form-container-' + subscriptionId);
            const showButton = $('.show-card-form-button[data-subscription-id="' + subscriptionId + '"]');

            console.log('カード更新をキャンセル:', subscriptionId);

            // フォームを非表示にしてボタンを表示
            formContainer.hide();
            showButton.show();

            // エラーメッセージをクリア
            clearCardError(subscriptionId);
        });
    }

    // Square カードフォームの初期化（表示時のみ）
    function initializeSquareCardForm(subscriptionId) {
        console.log('Square カードフォームを初期化:', subscriptionId);

        const containerId = 'card-container-' + subscriptionId;
        const formId = 'card-update-form-' + subscriptionId;

        // 既に初期化済みかチェック
        if (window.cardInstances && window.cardInstances[subscriptionId]) {
            console.log('既に初期化済み:', subscriptionId);
            return;
        }

        let payments;
        let card;

        try {
            // Squareペイメントオブジェクトの初期化
            payments = window.Square.payments(edelSquarePaymentParams.appId, edelSquarePaymentParams.locationId);

            // カード入力フィールドの初期化
            initializeCardForm();
        } catch (e) {
            console.error('Square初期化エラー:', e);
            showCardError(subscriptionId, 'Square決済システムの初期化に失敗しました。');
        }

        async function initializeCardForm() {
            try {
                console.log('カード入力フィールドの初期化を開始します...', subscriptionId);

                const cardContainer = document.getElementById(containerId);
                if (!cardContainer) {
                    console.error('card-container要素が見つかりません！', containerId);
                    showCardError(subscriptionId, 'カードフォームのコンテナが見つかりません。');
                    return;
                }

                // コンテナをクリア
                cardContainer.innerHTML = '';
                console.log('カードコンテナをクリアしました:', containerId);

                // カードフォームを作成
                card = await payments.card({
                    style: {
                        input: {
                            color: '#333333',
                            fontSize: '16px',
                            fontFamily: 'sans-serif'
                        },
                        'input.is-focus': {
                            color: '#000000'
                        },
                        'input.is-error': {
                            color: '#cc0023'
                        }
                    }
                });

                console.log('カードオブジェクトを作成しました:', subscriptionId);

                // カードフォームを指定のコンテナにアタッチ
                await card.attach('#' + containerId);
                console.log('カードフォームがアタッチされました:', containerId);

                // カードインスタンスを保存
                if (!window.cardInstances) {
                    window.cardInstances = {};
                }
                window.cardInstances[subscriptionId] = card;

                // フォーム送信イベントを設定
                setupFormSubmission();
            } catch (e) {
                console.error('カードフォーム初期化エラー:', e);
                showCardError(subscriptionId, 'カード入力フォームの初期化に失敗しました: ' + e.message);
            }
        }

        function setupFormSubmission() {
            const form = $('#' + formId);

            // 既存のイベントを削除（重複防止）
            form.off('submit.cardUpdate' + subscriptionId);

            // フォーム送信イベント
            form.on('submit.cardUpdate' + subscriptionId, async function (e) {
                e.preventDefault();

                const submitButton = form.find('.update-card-submit-button');
                const originalText = submitButton.text();

                // ボタンを無効化
                submitButton.prop('disabled', true).text('処理中...');
                clearCardError(subscriptionId);

                try {
                    // カードインスタンスを取得
                    const cardInstance = window.cardInstances[subscriptionId];
                    if (!cardInstance) {
                        throw new Error('カードインスタンスが見つかりません');
                    }

                    // カードトークンを取得
                    const result = await cardInstance.tokenize();

                    if (result.status === 'OK') {
                        console.log('トークン取得成功:', result.token);

                        // トークンを隠しフィールドに設定
                        $('#payment-token-' + subscriptionId).val(result.token);

                        // AJAX処理を実行
                        processCardUpdate(form, subscriptionId);
                    } else {
                        let errorMessage = 'カード情報を確認してください。';
                        if (result.errors && result.errors.length > 0) {
                            errorMessage = result.errors[0].message || errorMessage;
                            console.log('バリデーションエラー:', result.errors);
                        }
                        showCardError(subscriptionId, errorMessage);

                        // ボタンを再有効化
                        submitButton.prop('disabled', false).text(originalText);
                    }
                } catch (error) {
                    console.error('トークン取得エラー:', error);
                    showCardError(subscriptionId, 'カード情報の処理中にエラーが発生しました: ' + error.message);

                    // ボタンを再有効化
                    submitButton.prop('disabled', false).text(originalText);
                }
            });
        }
    }

    // エラーメッセージ表示
    function showCardError(subscriptionId, message) {
        const errorContainer = $('#card-errors-' + subscriptionId);
        errorContainer
            .html(
                '<div class="error-message" style="color: #cc0023; margin: 10px 0; padding: 10px; border: 1px solid #cc0023; border-radius: 4px; background-color: #ffeaea;">' +
                    message +
                    '</div>'
            )
            .show();
    }

    // エラーメッセージクリア
    function clearCardError(subscriptionId) {
        $('#card-errors-' + subscriptionId)
            .empty()
            .hide();
    }

    // AJAX処理
    function processCardUpdate(form, subscriptionId) {
        // フォームから直接値を取得
        const subscriptionIdValue = form.find('input[name="subscription_id"]').val();
        const paymentTokenValue = form.find('input[name="payment_token"]').val();
        const nonceValue = form.find('input[name="card_update_nonce"]').val();

        console.log('AJAX処理開始:', subscriptionId);
        console.log('送信パラメータ:');
        console.log('- subscription_id:', subscriptionIdValue);
        console.log('- payment_token:', paymentTokenValue);
        console.log('- nonce:', nonceValue);

        // パラメータ検証
        if (!subscriptionIdValue) {
            showCardError(subscriptionId, 'サブスクリプションIDが取得できませんでした。');
            return;
        }

        if (!paymentTokenValue) {
            showCardError(subscriptionId, 'カード情報のトークンが取得できませんでした。');
            return;
        }

        if (!nonceValue) {
            showCardError(subscriptionId, 'セキュリティトークンが取得できませんでした。');
            return;
        }

        $.ajax({
            url: edelSquarePaymentParams.ajaxUrl,
            type: 'POST',
            data: {
                action: 'edel_square_update_card',
                subscription_id: subscriptionIdValue,
                payment_token: paymentTokenValue,
                card_update_nonce: nonceValue
            },
            dataType: 'json',
            success: function (response) {
                console.log('カード更新API応答:', response);

                if (response.success) {
                    // 成功メッセージを表示
                    clearCardError(subscriptionId);
                    const successMessage =
                        '<div class="success-message" style="color: #155724; margin: 10px 0; padding: 10px; border: 1px solid #46b450; border-radius: 4px; background-color: #ecf7ed;">' +
                        response.data.message +
                        '</div>';
                    $('#card-errors-' + subscriptionId)
                        .html(successMessage)
                        .show();

                    // 2秒後にページをリロード
                    setTimeout(function () {
                        window.location.reload();
                    }, 2000);
                } else {
                    // エラーメッセージを表示
                    const errorMessage = response.data || 'カード情報の更新に失敗しました。';
                    showCardError(subscriptionId, errorMessage);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX エラー:', error);
                showCardError(subscriptionId, '通信エラーが発生しました。しばらくしてから再度お試しください。');
            },
            complete: function () {
                // ボタンを再有効化
                form.find('.update-card-submit-button').prop('disabled', false).text('更新');
            }
        });
    }

    // デバッグ用：DOM確認
    function debugCardForm(subscriptionId) {
        console.log('=== DEBUG INFO ===');
        console.log('Subscription ID:', subscriptionId);
        console.log('Card container element:', document.getElementById('card-container-' + subscriptionId));
        console.log('Form container element:', document.getElementById('card-update-form-container-' + subscriptionId));
        console.log('Square SDK:', typeof window.Square);
        console.log('==================');
    }

    // ドキュメント読み込み完了後に初期化
    $(document).ready(function () {
        console.log('カード更新処理を初期化');
        initializeCardUpdate();

        // デバッグ用：フォームの存在確認
        $('.card-update-form').each(function () {
            const subscriptionId = $(this).find('input[name="subscription_id"]').val();
            if (subscriptionId) {
                console.log('発見されたカード更新フォーム:', subscriptionId);
            }
        });
    });
});
