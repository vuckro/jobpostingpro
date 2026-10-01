<?php
/**
 * Plugin Name: JobPosting.pro Sync for ATYX
 * Plugin URI: https://github.com/vuckro/jobpostingpro
 * Description: Synchronisation automatique et propre des offres d'emploi depuis le flux XML JobPosting.pro vers le Custom Post Type job (compatible EtchWP & ACF).
 * Version: 1.0.0
 * Author: Waaskit
 * Author URI: https://waaskit.com
 * License: Proprietary
 * Text Domain: jobpostingpro
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

declare(strict_types=1);

namespace JobPostingPro;

if (!defined('ABSPATH')) {
    exit;
}

define('JOBPOSTINGPRO_VERSION', '1.0.0');
define('JOBPOSTINGPRO_FILE', __FILE__);
define('JOBPOSTINGPRO_PATH', plugin_dir_path(__FILE__));
define('JOBPOSTINGPRO_URL', plugin_dir_url(__FILE__));

// Autoloader PSR-4 simple pour le namespace JobPostingPro
spl_autoload_register(function (string $class): void {
    $prefix = 'JobPostingPro\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = JOBPOSTINGPRO_PATH . 'src/' . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Hooks d'activation et désactivation
register_activation_hook(__FILE__, function (): void {
    Config::set_defaults();
    Cron\Scheduler::schedule_event();
});

register_deactivation_hook(__FILE__, function (): void {
    Cron\Scheduler::clear_event();
});

// Initialisation du plugin
add_action('plugins_loaded', function (): void {
    Plugin::get_instance()->boot();
});
