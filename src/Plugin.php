<?php

declare(strict_types=1);

namespace JobPostingPro;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    public function boot(): void
    {
        // Initialiser l'admin
        if (is_admin()) {
            (new Admin\AdminPage())->register();
        }

        // Initialiser le planificateur Cron
        (new Cron\Scheduler())->register();

        // Initialiser la commande WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            (new Cli\CliCommand())->register();
        }
    }
}
