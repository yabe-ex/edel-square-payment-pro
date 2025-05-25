<?php

class EdelSquarePaymentProFront {
    function front_enqueue() {
        $version  = (defined('EDEL_SQUARE_PAYMENT_PRO_DEVELOP') && true === EDEL_SQUARE_PAYMENT_PRO_DEVELOP) ? time() : EDEL_SQUARE_PAYMENT_PRO_VERSION;
        $strategy = array('in_footer' => true, 'strategy'  => 'defer');

        wp_register_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-front', EDEL_SQUARE_PAYMENT_PRO_URL . '/js/front.js', array('jquery'), $version, $strategy);
        wp_register_style(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-front',  EDEL_SQUARE_PAYMENT_PRO_URL . '/css/front.css', array(), $version);

        // $params = array('ajaxurl' => admin_url( 'admin-ajax.php'));
        // wp_localize_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-front', 'params', $params );

        // $front = array(
        //     'ajaxurl' => admin_url('admin-ajax.php'),
        //     'nonce'   => wp_create_nonce(MY_PLUGIN_TEMPLATE_PREFIX)
        // );
        // wp_localize_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-front', 'front', $front);

        // if (is_page()) {
        //     $params = array('ajaxurl' => admin_url('admin-ajax.php'));
        //     wp_localize_script(EDEL_SQUARE_PAYMENT_PRO_SLUG . '-front', 'params', $params);
        // }

    }
}
