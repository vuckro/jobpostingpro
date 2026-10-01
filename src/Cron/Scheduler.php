<?php

declare(strict_types=1);

namespace JobPostingPro\Cron;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestionnaire WP-Cron (Obsolète : Le plugin fonctionne désormais à 100% via Webhook externe).
 * Cette classe est conservée pour purger tout événement WP-Cron existant ou résiduel.
 */
final class Scheduler
{
    public const HOOK = 'jobpostingpro_cron_sync';

    public function register(): void
    {
        // S'assurer qu'aucun événement WP-Cron n'est actif
        self::clear_event();
    }

    public static function clear_event(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
            $timestamp = wp_next_scheduled(self::HOOK);
        }
    }
}
