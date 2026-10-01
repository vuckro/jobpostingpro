<?php

declare(strict_types=1);

namespace JobPostingPro\Rest;

use JobPostingPro\Sync\Synchronizer;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class RestApi
{
    public const NAMESPACE = 'jobpostingpro/v1';
    public const ROUTE = '/sync';
    public const OPTION_SECRET = 'jobpostingpro_webhook_secret';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => ['GET', 'POST'],
            'callback'            => [$this, 'handle_sync'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Vérifie la clé secrète fournie dans la requête (GET/POST key ou header Authorization/X-Token).
     */
    public function check_permission(WP_REST_Request $request): bool
    {
        $expected_secret = self::get_secret();
        if (empty($expected_secret)) {
            return false;
        }

        // 1. Paramètre query string ?key=... ou ?token=...
        $token = $request->get_param('key') ?: $request->get_param('token');

        // 2. En-tête X-JobPosting-Token ou X-Token
        if (empty($token)) {
            $token = $request->get_header('x-jobposting-token') ?: $request->get_header('x-token');
        }

        // 3. En-tête Authorization: Bearer <token>
        if (empty($token)) {
            $auth_header = $request->get_header('authorization');
            if ($auth_header && preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
                $token = $matches[1];
            }
        }

        if (empty($token)) {
            return false;
        }

        return hash_equals($expected_secret, (string) $token);
    }

    /**
     * Exécute la synchronisation et retourne le rapport au format JSON.
     */
    public function handle_sync(WP_REST_Request $request): WP_REST_Response
    {
        $synchronizer = new Synchronizer();
        $stats = $synchronizer->sync();

        $status_code = ($stats['status'] === 'success') ? 200 : 500;

        return new WP_REST_Response([
            'success'   => ($stats['status'] === 'success'),
            'message'   => ($stats['status'] === 'success') ? 'Synchronisation réussie.' : 'Erreur lors de la synchronisation.',
            'report'    => $stats,
        ], $status_code);
    }

    /**
     * Récupère ou génère le jeton secret pour le webhook externe.
     */
    public static function get_secret(): string
    {
        $secret = (string) get_option(self::OPTION_SECRET, '');
        if (empty($secret)) {
            $secret = wp_generate_password(32, false);
            update_option(self::OPTION_SECRET, $secret);
        }
        return $secret;
    }

    /**
     * Retourne l'URL complète du webhook avec son jeton pour Cron-job.org.
     */
    public static function get_webhook_url(): string
    {
        $secret = self::get_secret();
        return add_query_arg(['key' => $secret], rest_url(self::NAMESPACE . self::ROUTE));
    }
}
