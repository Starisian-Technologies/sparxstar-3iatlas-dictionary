<?php
// Define constants needed for analysis
if (!defined('SPARX_3IATLAS_PATH')) {
    define('SPARX_3IATLAS_PATH', '/var/www/html/wp-content/plugins/sparxstar-3iatlas-dictionary/');
}
if (!defined('SPARX_3IATLAS_URL')) {
    define('SPARX_3IATLAS_URL', 'http://localhost/wp-content/plugins/sparxstar-3iatlas-dictionary/');
}
if (!defined('SPARX_3IATLAS_VERSION')) {
    define('SPARX_3IATLAS_VERSION', '1.0.0');
}

// Mock ACF function if missing
if (!function_exists('get_field')) {
    /**
     * @param string $selector
     * @param mixed $post_id
     * @param bool $format_value
     * @return mixed
     */
    function get_field($selector, $post_id = false, $format_value = true) {
        return '';
    }
}
