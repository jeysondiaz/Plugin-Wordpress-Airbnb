<?php
/**
 * Plugin Name: Airbnb WordPress Reservation Sync
 * Description: Sincroniza las reservas entre Airbnb y WordPress
 * Version: 1.0.0
 */

class AirbnbWordPressSync {
    private $airbnb_api_key;
    private $property_id;
    
    public function __construct() {
        $this->airbnb_api_key = get_option('airbnb_api_key');
        $this->property_id = get_option('airbnb_property_id');
        
        // Programar la sincronización automática
        add_action('init', array($this, 'schedule_sync'));
        add_action('airbnb_sync_hook', array($this, 'sync_reservations'));
    }
    
    public function schedule_sync() {
        if (!wp_next_scheduled('airbnb_sync_hook')) {
            wp_schedule_event(time(), 'hourly', 'airbnb_sync_hook');
        }
    }
    
    public function sync_reservations() {
        // Obtener reservas de Airbnb
        $airbnb_reservations = $this->get_airbnb_reservations();
        
        if (!empty($airbnb_reservations)) {
            foreach ($airbnb_reservations as $reservation) {
                $this->update_wordpress_availability($reservation);
            }
        }
        
        $this->log_sync_activity();
    }
    
    private function get_airbnb_reservations() {
        // Implementar la llamada a la API de Airbnb
        $endpoint = "https://api.airbnb.com/v2/calendar/{$this->property_id}";
        
        $args = array(
            'headers' => array(
                'Authorization' => "Bearer {$this->airbnb_api_key}",
                'Content-Type' => 'application/json'
            )
        );
        
        $response = wp_remote_get($endpoint, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Error al obtener reservas de Airbnb: ' . $response->get_error_message());
            return array();
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    private function update_wordpress_availability($reservation) {
        global $wpdb;
        
        // Adaptar esto según tu sistema de reservas de WordPress
        $table_name = $wpdb->prefix . 'reservations';
        
        // Bloquear las fechas en WordPress
        $wpdb->insert(
            $table_name,
            array(
                'start_date' => $reservation['start_date'],
                'end_date' => $reservation['end_date'],
                'status' => 'blocked',
                'source' => 'airbnb',
                'reservation_id' => $reservation['id']
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    private function log_sync_activity() {
        $log_file = WP_CONTENT_DIR . '/airbnb-sync-log.txt';
        $message = date('[Y-m-d H:i:s]') . " Sincronización completada\n";
        file_put_contents($log_file, $message, FILE_APPEND);
    }
    
    private function log_error($message) {
        $log_file = WP_CONTENT_DIR . '/airbnb-sync-errors.txt';
        $log_message = date('[Y-m-d H:i:s]') . " ERROR: {$message}\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}

// Inicializar el plugin
add_action('plugins_loaded', function() {
    new AirbnbWordPressSync();
});

// Agregar página de configuración en el admin
add_action('admin_menu', function() {
    add_options_page(
        'Configuración Airbnb Sync',
        'Airbnb Sync',
        'manage_options',
        'airbnb-sync-settings',
        function() {
            include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
        }
    
