<?php

declare(strict_types=1);

namespace JobPostingPro\Cli;

use JobPostingPro\Sync\Synchronizer;
use WP_CLI;

if (!defined('ABSPATH')) {
    exit;
}

final class CliCommand
{
    public function register(): void
    {
        WP_CLI::add_command('jobposting sync', [$this, 'sync']);
    }

    /**
     * Synchronise les offres d'emploi depuis le flux XML JobPosting.pro.
     *
     * ## EXEMPLES
     *
     *     # Synchronisation standard
     *     wp jobposting sync
     *
     * @when after_wp_load
     */
    public function sync(array $args, array $assoc_args): void
    {
        WP_CLI::line('Démarrage de la synchronisation JobPosting.pro...');

        $synchronizer = new Synchronizer();
        $stats = $synchronizer->sync();

        if ($stats['status'] === 'error') {
            WP_CLI::error(sprintf('Erreur lors de la synchronisation : %s', implode(' | ', $stats['errors'])));
            return;
        }

        WP_CLI::success(sprintf(
            'Synchronisation terminée en %s secondes !',
            $stats['execution_time']
        ));

        WP_CLI::line(sprintf('• Total dans le flux : %d', $stats['total_in_feed']));
        WP_CLI::line(sprintf('• Créées : %d', $stats['created']));
        WP_CLI::line(sprintf('• Mises à jour : %d', $stats['updated']));
        WP_CLI::line(sprintf('• Inchangées : %d', $stats['unchanged']));
        WP_CLI::line(sprintf('• Dépubliées (retirées du flux) : %d', $stats['removed']));
    }
}
