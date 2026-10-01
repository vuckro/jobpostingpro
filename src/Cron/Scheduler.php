<?php

declare(strict_types=1);

namespace JobPostingPro\Cron;

use JobPostingPro\Config;
use JobPostingPro\Sync\Synchronizer;

if (!defined('ABSPATH')) {
    exit;
}

final class Scheduler
{
    public const HOOK = 'jobpostingpro_cron_sync';

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'execute']);
    }

    public function execute(): void
    {
        $synchronizer = new Synchronizer();
        $synchronizer->sync();
    }

    public static function schedule_event(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            $frequency = Config::get_cron_frequency();
            wp_schedule_event(time(), $frequency, self::HOOK);
        }
    }

    public static function clear_event(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }

    public static function reschedule(): void
    {
        self::clear_event();
        self::schedule_event();
    }
}
