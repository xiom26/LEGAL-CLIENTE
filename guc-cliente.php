<?php
/**
 * Plugin Name: LEGAL ENGINEERING - Gestión de Clientes
 * Description: Lista el caso de acuerdo al cliente registrado para LEGAL ENGINEERING
 * Version:     1.1.0
 * Author:      Inecxus
 */
if ( ! defined('ABSPATH') ) exit;

class GUC_Cliente_Plugin {
    const SLUG = 'guc-cliente';
    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this,'register_assets']);
        add_shortcode('guc_cliente', [$this,'shortcode']);
        add_action('wp_ajax_guc_cliente_dataset', [$this,'ajax_dataset']);
    }

    public function register_assets() {
        $ver = '1.1.0';
        wp_register_style(self::SLUG, plugins_url('assets/guc-cliente.css', __FILE__), [], $ver);
        wp_register_script(self::SLUG, plugins_url('assets/guc-cliente.js', __FILE__), ['jquery'], $ver, true);
        wp_localize_script(self::SLUG, 'GUC_CLIENTE', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('guc_cliente_nonce')
        ]);
    }

    public function shortcode($atts) {
        if ( ! is_user_logged_in() ) {
            return '<div id="guc-cliente" class="guc-wrap"><p class="guc-msg">Debes iniciar sesión.</p></div>';
        }
        wp_enqueue_style(self::SLUG);
        wp_enqueue_script(self::SLUG);

        ob_start(); ?>
        <div id="guc-cliente" class="guc-wrap" data-ready="0">
            <div class="guc-header">
                <div>
                    <h3 class="guc-title">LEGAL ENGINEERING</h3>
                    <div class="guc-subtitle">Seguimiento personalizado de su caso</div>
                </div>
                <div class="guc-pill" aria-label="Resultados encontrados">1 resultado</div>
            </div>
            <div class="guc-card" id="guc-case-header">
                <div class="guc-row">
                    <div><span class="guc-label">Entidad Convocante</span><div class="guc-value" data-k="entity">—</div></div>
                    <div><span class="guc-label">Objeto de Contratación</span><div class="guc-value" data-k="objeto">—</div></div>
                </div>
                <div class="guc-row">
                    <div><span class="guc-label">Nomenclatura</span><div class="guc-value" data-k="nomenclatura">—</div></div>
                    <div><span class="guc-label">Descripción del objeto</span><div class="guc-value" data-k="descripcion">—</div></div>
                </div>
                <div class="guc-row">
                    <div><span class="guc-label">N° de convocatoria</span><div class="guc-value" data-k="convocatoria">—</div></div>
                </div>
            </div>

            <h4 class="guc-block-title">Listado de acciones realizadas por el ítem</h4>

            <div class="guc-accordion">
                <details open class="guc-section">
                    <summary><span>Pre Arbitrales</span></summary>
                    <div class="guc-table-wrap">
                        <table class="guc-table" id="tbl-pre">
                            <thead><tr>
                                <th>Nro</th><th>Situación</th><th>Fecha y Hora</th><th>Motivo</th><th>Acciones</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </details>

                <details class="guc-section">
                    <summary><span>Arbitrales</span></summary>

                    <div class="guc-subblock">
                        <div class="guc-subtitle-2" id="sec-label">Secretaría</div>
                        <div class="guc-table-wrap">
                            <table class="guc-table" id="tbl-secretaria">
                                <thead><tr>
                                    <th>Nro</th><th>Situación</th><th>Fecha y Hora</th><th>Motivo</th><th>Acciones</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="guc-subblock">
                        <div class="guc-subtitle-2">Arbitral</div>
                        <div class="guc-table-wrap">
                            <table class="guc-table" id="tbl-arbitral">
                                <thead><tr>
                                    <th>Nro</th><th>Situación</th><th>Fecha y Hora</th><th>Motivo</th><th>Acciones</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_dataset() {
        check_ajax_referer('guc_cliente_nonce','_wpnonce');
        if ( ! is_user_logged_in() ) wp_send_json_error(['msg'=>'no_login'], 401);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $wp_user = wp_get_current_user();
        $wp_uid  = (int) $wp_user->ID;
        $login   = $wp_user->user_login;

        $guc_user_id = (int) get_user_meta($wp_uid, 'guc_user_id', true);
        if ( ! $guc_user_id ) {
            $guc_user_id = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$prefix}guc_users WHERE username = %s LIMIT 1", $login)
            );
        }
        if ( ! $guc_user_id ) {
            wp_send_json_error(['msg'=>'no_guc_user','login'=>$login], 200);
        }

        $case = $wpdb->get_row(
            $wpdb->prepare("SELECT id, nomenclature as nomenclatura, convocatoria, expediente, entidad, objeto, descripcion, estado, estado_fecha, user_id, username, case_type, created_at 
                            FROM {$prefix}guc_cases 
                            WHERE user_id = %d OR username = (SELECT username FROM {$prefix}guc_users WHERE id = %d)
                            ORDER BY id DESC LIMIT 1", $guc_user_id, $guc_user_id), ARRAY_A);

        if ( ! $case ) {
            wp_send_json_error(['msg'=>'no_case'], 200);
        }
        $case_id = (int) $case['id'];

        $pre = $wpdb->get_results(
            $wpdb->prepare("SELECT id, situacion, motivo, fecha, COALESCE(pdf_url,'') AS pdf_url 
                            FROM {$prefix}guc_pre_actions WHERE case_id = %d ORDER BY id ASC", $case_id), ARRAY_A);

        $secretaria_table = strtolower($case['case_type']) === 'jprd'
            ? "{$prefix}guc_secretaria_general_actions"
            : "{$prefix}guc_secretaria_arbitral_actions";

        $secretaria = $wpdb->get_results(
            $wpdb->prepare("SELECT id, situacion, motivo, fecha, COALESCE(pdf_url,'') AS pdf_url 
                            FROM {$secretaria_table} WHERE case_id = %d ORDER BY id ASC", $case_id), ARRAY_A);

        $arbitral = $wpdb->get_results(
            $wpdb->prepare("SELECT id, situacion, motivo, fecha, COALESCE(pdf_url,'') AS pdf_url 
                            FROM {$prefix}guc_arbitral_actions WHERE case_id = %d ORDER BY id ASC", $case_id), ARRAY_A);

        $sec_label = (strtolower($case['case_type']) === 'jprd') ? 'Secretaría General' : 'Secretaría Arbitral';

        wp_send_json_success([
            'case'       => $case,
            'sec_label'  => $sec_label,
            'pre'        => $pre,
            'secretaria' => $secretaria,
            'arbitral'   => $arbitral,
        ]);
    }
}

GUC_Cliente_Plugin::instance();
