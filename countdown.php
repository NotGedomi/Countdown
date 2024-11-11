<?php
/*
Plugin Name: Single Countdown Products
Plugin URI: 
Description: Plugin para crear un countdown de productos en oferta de WooCommerce
Version: 1.0
Author: Gedomi
*/

if (!defined('ABSPATH')) exit;

class Single_Countdown_Products {
    private static $instance = null;
    private $option_name = 'single_countdown_data';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_search_sale_products', array($this, 'search_sale_products'));
        add_action('wp_ajax_save_countdown', array($this, 'save_countdown'));
        add_shortcode('sale_countdown', array($this, 'countdown_shortcode'));
        add_shortcode('sale_countdown_data', array($this, 'countdown_data_shortcode'));
    }

    public function add_menu_page() {
        add_menu_page(
            'Countdown Ofertas',
            'Countdown',
            'manage_options',
            'countdown-settings',
            array($this, 'render_admin_page'),
            'dashicons-clock',
            56
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_countdown-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('countdown-admin-css', plugins_url('assets/css/admin.css', __FILE__));
        wp_enqueue_script('countdown-admin-js', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script('countdown-admin-js', 'countdownAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('countdown_nonce')
        ));
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('countdown-css', plugins_url('assets/css/countdown.css', __FILE__));
        wp_enqueue_script('countdown-js', plugins_url('assets/js/countdown.js', __FILE__), array('jquery'), '1.0', true);
    }

    public function countdown_shortcode() {
        $countdown_data = get_option($this->option_name);
        if (!$countdown_data || empty($countdown_data['end_time'])) {
            return '';
        }

        $timezone = wp_timezone();
        $end_time = new DateTime($countdown_data['end_time'], $timezone);
        $now = new DateTime('now', $timezone);

        if ($end_time <= $now) {
            return '';
        }

        // Creamos un array asociativo con los datos necesarios
        $data = array(
            'endTime' => $end_time->format('c'),
            'serverTime' => $now->format('c')
        );

        // Usamos wp_json_encode en lugar de json_encode directo
        $json_data = wp_json_encode($data);
        
        // Escapamos el JSON para asegurar que es seguro en HTML
        $escaped_data = esc_attr($json_data);

        return sprintf(
            '<div class="wc-countdown" data-countdown="%s"></div>',
            $escaped_data
        );
    }

    public function countdown_data_shortcode() {
        $countdown_data = get_option($this->option_name);
        if (!$countdown_data || empty($countdown_data['end_time'])) {
            return wp_json_encode(array());
        }

        $timezone = wp_timezone();
        $end_time = new DateTime($countdown_data['end_time'], $timezone);
        $now = new DateTime('now', $timezone);

        if ($end_time <= $now) {
            return wp_json_encode(array());
        }

        // Aseguramos que product_ids sea un array
        $product_ids = !empty($countdown_data['product_ids']) ? 
            explode(',', $countdown_data['product_ids']) : 
            array();

        $data = array(
            'product_ids' => $product_ids,
            'end_time' => $end_time->format('c')
        );

        return wp_json_encode($data);
    }
    

    public function search_sale_products() {
        check_ajax_referer('countdown_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search']);
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $search_term,
            'meta_query' => array(
                array(
                    'key' => '_sale_price',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product && $product->is_on_sale()) {
                    $products[] = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'image' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'),
                        'price' => $product->get_price_html()
                    );
                }
            }
        }
        wp_reset_postdata();

        wp_send_json_success($products);
    }

    public function save_countdown() {
        check_ajax_referer('countdown_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
        }

        $name = sanitize_text_field($_POST['name']);
        $product_ids = sanitize_text_field($_POST['product_ids']);
        $duration_value = intval($_POST['duration_value']);
        $duration_unit = sanitize_text_field($_POST['duration_unit']);

        if (empty($name) || empty($product_ids) || $duration_value < 1) {
            wp_send_json_error('Faltan datos requeridos');
        }

        $timezone = wp_timezone();
        $end_time = new DateTime('now', $timezone);
        
        switch ($duration_unit) {
            case 'days':
                $end_time->modify("+{$duration_value} days");
                break;
            case 'hours':
                $end_time->modify("+{$duration_value} hours");
                break;
            case 'minutes':
                $end_time->modify("+{$duration_value} minutes");
                break;
            default:
                wp_send_json_error('Unidad de tiempo no válida');
        }

        $data = array(
            'name' => $name,
            'product_ids' => $product_ids,
            'end_time' => $end_time->format('Y-m-d H:i:s')
        );

        update_option($this->option_name, $data);

        wp_send_json_success(array(
            'message' => 'Countdown actualizado correctamente',
            'redirect' => admin_url('admin.php?page=countdown-settings&updated=1')
        ));
    }

    public function render_admin_page() {
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Countdown actualizado correctamente.</p></div>';
        }

        $countdown_data = get_option($this->option_name, array(
            'name' => '',
            'product_ids' => '',
            'end_time' => ''
        ));

        $duration = array(
            'value' => 24,
            'unit' => 'hours'
        );

        if (!empty($countdown_data['end_time'])) {
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            $end_time = new DateTime($countdown_data['end_time'], $timezone);
            
            if ($end_time > $now) {
                $diff = $end_time->diff($now);
                $total_minutes = ($diff->d * 24 * 60) + ($diff->h * 60) + $diff->i;
                
                if ($total_minutes >= 1440) {
                    $duration['value'] = ceil($total_minutes / 1440);
                    $duration['unit'] = 'days';
                } elseif ($total_minutes >= 60) {
                    $duration['value'] = ceil($total_minutes / 60);
                    $duration['unit'] = 'hours';
                } else {
                    $duration['value'] = max(1, $total_minutes);
                    $duration['unit'] = 'minutes';
                }
            }
        }
        ?>
        <div class="wrap">
            <h1>Configuración del Countdown</h1>
            
            <form id="countdown-form" class="countdown-form">
                <div class="form-field">
                    <label for="countdown-name">Nombre de la Oferta</label>
                    <input type="text" id="countdown-name" name="name" required 
                           value="<?php echo esc_attr($countdown_data['name']); ?>">
                </div>

                <div class="form-field duration-field">
                    <label>Duración:</label>
                    <div class="duration-inputs">
                        <input type="number" 
                               name="duration_value" 
                               min="1" 
                               value="<?php echo esc_attr($duration['value']); ?>" 
                               required>
                        <select name="duration_unit">
                            <option value="minutes" <?php selected($duration['unit'], 'minutes'); ?>>Minutos</option>
                            <option value="hours" <?php selected($duration['unit'], 'hours'); ?>>Horas</option>
                            <option value="days" <?php selected($duration['unit'], 'days'); ?>>Días</option>
                        </select>
                    </div>
                </div>

                <div class="form-field">
                    <label for="product-search">Buscar productos en oferta:</label>
                    <input type="text" id="product-search" placeholder="Escribe para buscar...">
                    <div id="search-results"></div>
                </div>

                <div class="form-field">
                    <label>Productos seleccionados:</label>
                    <div id="selected-products" 
                         <?php if (!empty($countdown_data['product_ids'])): ?>
                         data-products='<?php echo json_encode($this->get_products_data($countdown_data['product_ids'])); ?>'
                         <?php endif; ?>>
                    </div>
                </div>

                <div class="form-field shortcodes-info">
                    <h3>Shortcodes disponibles:</h3>
                    <p><code>[sale_countdown]</code> - Muestra el contador</p>
                    <p><code>[sale_countdown_data]</code> - Devuelve los datos de la oferta</p>
                </div>

                <div class="submit-wrapper">
                    <button type="submit" class="button button-primary">Actualizar Countdown</button>
                </div>
            </form>
        </div>
        <?php
    }

    private function get_products_data($product_ids) {
        $products = array();
        $ids = explode(',', $product_ids);
        
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $products[] = array(
                    'id' => $id,
                    'title' => $product->get_name(),
                    'image' => get_the_post_thumbnail_url($id, 'thumbnail'),
                    'price' => $product->get_price_html()
                );
            }
        }
        
        return $products;
    }
}

// Inicializar el plugin
add_action('plugins_loaded', array('Single_Countdown_Products', 'get_instance'));