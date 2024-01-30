<?php

declare(strict_types=1);

namespace BeycanPress\Payeer;

class Callback
{
    /**
     * @var object
     */
    private object $order;

    /**
     * @return void
     */
    public function init(): void
    {
        // params
        $orderId = isset($_GET['m_orderid']) ? absint($_GET['m_orderid']) : 0;
        $amount  = isset($_GET['m_amount']) ? floatval($_GET['m_amount']) : null;
        $action  = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : null;
        $shopId  = isset($_GET['m_shop']) ? sanitize_text_field($_GET['m_shop']) : null;
        $desc    = isset($_GET['m_desc']) ? sanitize_text_field($_GET['m_desc']) : null;
        $curr    = isset($_GET['m_curr']) ? sanitize_text_field($_GET['m_curr']) : null;
        $sign    = isset($_GET['m_sign']) ? sanitize_text_field($_GET['m_sign']) : null;
        $status  = isset($_GET['m_status']) ? sanitize_text_field($_GET['m_status']) : null;

        // operation params
        $operationId      = isset($_GET['m_operation_id']) ?
        sanitize_text_field($_GET['m_operation_id']) : null;

        $operationPs      = isset($_GET['m_operation_ps']) ?
        sanitize_text_field($_GET['m_operation_ps']) : null;

        $operationDate    = isset($_GET['m_operation_date']) ?
        sanitize_text_field($_GET['m_operation_date']) : null;

        $operationPayDate = isset($_GET['m_operation_pay_date']) ?
        sanitize_text_field($_GET['m_operation_pay_date']) : null;


        if (!$this->order = wc_get_order($orderId)) {
            exit(wp_redirect(home_url()));
        }

        if (!$action) {
            exit(wp_redirect(home_url()));
        }

        if ($action == 'fail') {
            $this->updateOrderAsFail();
        }

        if ($operationId && $sign) {
            $secret = Gateway::get_option_custom('payeer_secret_key');

            $signHash = strtoupper(hash('sha256', implode(':', [
                $operationId,
                $operationPs,
                $operationDate,
                $operationPayDate,
                $shopId,
                $orderId,
                $amount,
                $curr,
                $desc,
                $status,
                $secret
            ])));

            $this->order->update_meta_data(esc_html__('Payeer operation ID', 'payeer_gateway'), $operationId);

            $this->order->save();

            if ($sign == $signHash && $status == 'success') {
                $this->updateOrderAsComplete();
            }
        }

        $this->updateOrderAsFail();
    }

    /**
     * @return void
     */
    public function updateOrderAsComplete(): void
    {
        global $woocommerce;

        $completeStatus = Gateway::get_option_custom('payment_complete_order_status');
        if ($completeStatus == 'wc-completed') {
            $note = esc_html__('Your order is complete.', 'payeer_gateway');
        } else {
            $note = esc_html__('Your order is processing.', 'payeer_gateway');
        }

        $this->order->payment_complete();

        $this->order->update_status($completeStatus, $note);

        // Remove cart
        $woocommerce->cart->empty_cart();

        exit(wp_redirect($this->order->get_checkout_order_received_url()));
    }

    /**
     * @return void
     */
    public function updateOrderAsFail(): void
    {
        $this->order->update_status('wc-failed', esc_html__('Payment is failed!', 'payeer_gateway'));

        wc_add_notice(esc_html__('Payment is failed!', 'payeer_gateway'), 'error');

        exit(wp_redirect(wc_get_checkout_url()));
    }
}
