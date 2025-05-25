jQuery(document).ready(function ($) {
    'use strict';

    $('<button id="test-subscription">サブスクリプションAPIテスト</button>').insertAfter('#edel-square-subscription-submit');

    // テストボタンのクリックイベント
    $('#test-subscription').on('click', function () {
        $.ajax({
            url: edelSquarePaymentParams.ajaxUrl,
            type: 'POST',
            data: {
                action: 'edel_square_test_subscription',
                nonce: edelSquarePaymentParams.nonce
            },
            success: function (response) {
                console.log('テスト結果:', response);
                alert('テスト結果: ' + JSON.stringify(response));
            },
            error: function (xhr, status, error) {
                console.error('テストエラー:', error);
                alert('テストエラー: ' + error);
            }
        });
    });

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
                        // 決済フォームを非表示
                        $('.edel-square-payment-form').hide();

                        // 成功メッセージを表示
                        $('#edel-square-success-message').html(response.data.message).show();

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
                        // 決済フォームを非表示
                        $('.edel-square-payment-form').hide();

                        // 成功メッセージを表示
                        $('#edel-square-success-message').html(response.data.message).show();

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
                        let successHtml = '<div class="edel-square-success-message">';

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

                        successHtml += '</div>';

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

    // reCAPTCHA v3の処理（もし存在する場合）
    // if (typeof edelSquareRecaptchaParams !== 'undefined' && edelSquareRecaptchaParams.siteKey) {
    //     // reCAPTCHAの読み込みが完了しているか確認
    //     if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.ready === 'function') {
    //         grecaptcha.ready(function () {
    //             try {
    //                 // ログインページの読み込み時に実行
    //                 grecaptcha
    //                     .execute(edelSquareRecaptchaParams.siteKey, { action: 'login' })
    //                     .then(function (token) {
    //                         console.log('reCAPTCHAトークン取得成功');
    //                         $('#edel-square-recaptcha-token').val(token);
    //                     })
    //                     .catch(function (error) {
    //                         console.log('reCAPTCHAトークン取得エラー:', error);
    //                     });
    //             } catch (e) {
    //                 console.log('reCAPTCHA実行エラー:', e);
    //             }
    //         });
    //     } else {
    //         console.log('grecaptchaが正しく読み込まれていません');
    //     }
    // }
});
