<?php

/**
 * @package AutoFEL
 * @version 1.0
 * @author eDesigns
 */

/*
    Plugin Name: AutoFEL
    Description: Plugin para la integración de FEL con WooCommerce.
    Author: eDesigns
    Version: 1.0
    Author URI: https://edesigns.com/
*/

    if (!defined('ABSPATH')) {
        exit;
    }

    class AutoFEL {

        public function __construct() {
            add_action('woocommerce_order_status_changed', array($this, 'auto_fel_order_status_changed'), 10, 3);
            add_action('admin_menu', array($this, 'auto_fel_menu'));
            add_action('admin_init', array($this, 'auto_fel_settings'));
        }

        public function auto_fel_menu() {
            add_menu_page('AutoFEL', 'AutoFEL', 'manage_options', 'auto-fel', array($this, 'auto_fel_history_page'), 'dashicons-admin-generic', 99);
            add_submenu_page('auto-fel', 'Historial', 'Historial', 'manage_options', 'auto-fel', array($this, 'auto_fel_history_page'));
            add_submenu_page('auto-fel', 'Configuración', 'Configuración', 'manage_options', 'auto-fel-settings', array($this, 'auto_fel_settings_page'));
        }

        /* 
         * Función que se ejecuta cuando el estado de un pedido cambia.
         * 
         * @param int $order_id
         * @param string $old_status
         * @param string $new_status
         * @return void
         */
        public function auto_fel_order_status_changed($order_id, $old_status, $new_status) {
            try {
                switch ($new_status) {
                    case 'completed':
                        $this->auto_fel_send_invoice($order_id);
                        break;
                    case 'cancelled':
                        $this->auto_fel_cancel_invoice($order_id);
                        break;
                    default:
                        break;
                }
            }
            catch (Exception $e) {
                $this->auto_fel_log($e->getMessage());
            }
        }

        /* 
         * Función que genera la factura.
         * 
         * @param int $order_id
         * @return void
         */
        public function auto_fel_generate_invoice($order_id) {
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?></FacturaElectronica>');

            $order_data['xml'] = $xml;
            $order_data['pdf'] = "";
            $order_data['html'] =  "";

            return $order_data;
        }

        /* 
         * Función que envía la factura.
         * 
         * @param int $order_id
         * @return void
         */
        public function auto_fel_send_invoice($order_id) {

            $fel = $this->auto_fel_generate_invoice($order_id);

            $r = wp_remote_post("", array(
                'method' => 'POST',
                'httpversion' => '2.0',
                'headers' => array(
                    'Authorization' => 'Bearer ',
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(
                    [
                        'xml' => $fel['xml']->asXML(),
                        'pdf' => $fel['pdf'],
                        'html' => $fel['html']
                    ]
                )
            ));

        }
        
        /* 
         * Función que cancela la factura.
         * 
         * @param int $order_id
         * @return void
         */
        public function auto_fel_cancel_invoice($order_id) {
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();
            
        }

        public function auto_fel_settings() {

            // Section Certificador
            add_settings_section('auto-fel-settings-section-certificador', 'Certificador de Documentos', array($this, 'auto_fel_settings_section_certficador_callback'), 'auto-fel-settings');
            // Section Certificador Settings
            register_setting('auto-fel-settings', 'auto-fel-settings-certificador');
            // Section Certificador Fields
            add_settings_field('auto-fel-settings-certificador', 'Certificador', array($this, 'auto_fel_settings_certificador_callback'), 'auto-fel-settings', 'auto-fel-settings-section-certificador', [
                'label_for' => 'auto-fel-settings-certificador',
                'class' => 'auto-fel-settings-class',
                'type' => 'select',
            ]);

            // Section Contribuyente
            add_settings_section('auto-fel-settings-section', 'Contribuyente', array($this, 'auto_fel_settings_section_contribuyente_callback'), 'auto-fel-settings');
            // Section Contribuyente Settings
            register_setting('auto-fel-settings', 'auto-fel-settings-nit');
            // Section Contribuyente Fields
            add_settings_field('auto-fel-settings-nit', 'NIT', array($this, 'auto_fel_settings_nit_callback'), 'auto-fel-settings', 'auto-fel-settings-section', [
                'label_for' => 'auto-fel-settings-nit',
                'class' => 'auto-fel-settings-class',
                'type' => 'text',
            ]);

        }

        // Section Certificador Description
        public function auto_fel_settings_section_certficador_callback() {
            echo '<p>Configuración del certificador aque emitirá el documento.</p>';
        }
        // Section Contribuyente Description
        public function auto_fel_settings_section_contribuyente_callback() {
            echo '<p>Configuración del contribuyente.</p>';
        }

        // Field Callbacks
        public function auto_fel_settings_certificador_callback($args) {
            $option = get_option($args['label_for']);
            $html = '<select id="' . $args['label_for'] . '" name="' . $args['label_for'] . '">';
                $html .= '<option value="1" ' . selected(1, $option, false) . '>Sin certificador (AutoFEL)</option>';
                $html .= '<option value="2" ' . selected(2, $option, false) . '>DigiFact</option>';
            $html .= '</select>';
            echo $html;
        }
        public function auto_fel_settings_nit_callback($args) {
            $option = get_option($args['label_for']);
            $html = '<input type="text" id="' . $args['label_for'] . '" name="' . $args['label_for'] . '" value="' . $option . '">';
            echo $html;
        }

        // Settings
        public function auto_fel_settings_page() {
            ?>
            <div class="wrap">
                <h1>Configuración AutoFEL</h1>
                <form method="post" action="options.php">
                    <?php
                        settings_fields('auto-fel-settings');
                        do_settings_sections('auto-fel-settings');
                        submit_button();
                    ?>
                </form>
            </div>
            <?php
        }
        
        // History
        public function auto_fel_history_page() {
            ?>
            <div class="wrap">
                <h1>Historial</h1>
            </div>
            <?php
        }

        // Error logs
        public function auto_fel_logs_page() {
            ?>
            <div class="wrap">
                <h1>Logs</h1>
            </div>
            <?php
        }

        /* 
         * Función que registra un mensaje en el log.
         * 
         * @param string $message
         * @return void
         */
        public function auto_fel_log($message) {

        }

    }

    $autoFEL = new AutoFEL();

?>