<?php

declare(strict_types=1);

namespace JobPostingPro;

if (!defined('ABSPATH')) {
    exit;
}

final class Config
{
    public const OPTION_FEED_URL = 'jobpostingpro_feed_url';
    public const OPTION_REMOVED_ACTION = 'jobpostingpro_removed_action';
    public const OPTION_CRON_FREQUENCY = 'jobpostingpro_cron_frequency';
    public const OPTION_LAST_SYNC = 'jobpostingpro_last_sync';

    public const DEFAULT_FEED_URL = 'https://www.jobposting.pro/flux/xml/atyx.xml';
    public const DEFAULT_REMOVED_ACTION = 'draft'; // 'draft' ou 'trash'
    public const DEFAULT_CRON_FREQUENCY = 'hourly'; // 'hourly', 'twicedaily', 'daily'

    public static function set_defaults(): void
    {
        if (get_option(self::OPTION_FEED_URL) === false) {
            update_option(self::OPTION_FEED_URL, self::DEFAULT_FEED_URL);
        }
        if (get_option(self::OPTION_REMOVED_ACTION) === false) {
            update_option(self::OPTION_REMOVED_ACTION, self::DEFAULT_REMOVED_ACTION);
        }
        if (get_option(self::OPTION_CRON_FREQUENCY) === false) {
            update_option(self::OPTION_CRON_FREQUENCY, self::DEFAULT_CRON_FREQUENCY);
        }
    }

    public static function get_feed_url(): string
    {
        $url = (string) get_option(self::OPTION_FEED_URL, self::DEFAULT_FEED_URL);
        return !empty($url) ? $url : self::DEFAULT_FEED_URL;
    }

    public static function get_removed_action(): string
    {
        $action = (string) get_option(self::OPTION_REMOVED_ACTION, self::DEFAULT_REMOVED_ACTION);
        return in_array($action, ['draft', 'trash'], true) ? $action : self::DEFAULT_REMOVED_ACTION;
    }

    public static function get_cron_frequency(): string
    {
        $freq = (string) get_option(self::OPTION_CRON_FREQUENCY, self::DEFAULT_CRON_FREQUENCY);
        return in_array($freq, ['hourly', 'twicedaily', 'daily'], true) ? $freq : self::DEFAULT_CRON_FREQUENCY;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_last_sync(): array
    {
        $data = get_option(self::OPTION_LAST_SYNC, []);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $stats
     */
    public static function set_last_sync(array $stats): void
    {
        update_option(self::OPTION_LAST_SYNC, $stats);
    }
}
