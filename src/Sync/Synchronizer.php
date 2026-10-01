<?php

declare(strict_types=1);

namespace JobPostingPro\Sync;

use JobPostingPro\Client\XmlClient;
use JobPostingPro\Config;
use JobPostingPro\Parser\JobMapper;
use JobPostingPro\Builder\ContentBuilder;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class Synchronizer
{
    private XmlClient $client;
    private JobMapper $mapper;
    private ContentBuilder $builder;

    public function __construct(
        ?XmlClient $client = null,
        ?JobMapper $mapper = null,
        ?ContentBuilder $builder = null
    ) {
        $this->client  = $client ?? new XmlClient();
        $this->mapper  = $mapper ?? new JobMapper();
        $this->builder = $builder ?? new ContentBuilder();
    }

    /**
     * Exécute la synchronisation complète (création, mise à jour, dépublication).
     *
     * @return array<string, mixed> Résumé de l'opération
     */
    public function sync(): array
    {
        $start_time = microtime(true);
        $feed_url = Config::get_feed_url();
        $removed_action = Config::get_removed_action();

        $stats = [
            'timestamp'      => current_time('mysql'),
            'feed_url'       => $feed_url,
            'total_in_feed'  => 0,
            'created'        => 0,
            'updated'        => 0,
            'removed'        => 0,
            'unchanged'      => 0,
            'errors'         => [],
            'status'         => 'success',
            'execution_time' => 0.0,
        ];

        try {
            // 1. Récupération du flux
            $xml = $this->client->fetch($feed_url);

            // 2. Indexation des offres existantes en base (job_reference => post_id)
            $existing_jobs = $this->get_existing_jobs();

            $feed_references = [];

            // 3. Traitement de chaque offre du flux
            foreach ($xml->job as $job_node) {
                $mapped = $this->mapper->map($job_node);
                $ref = (string) $mapped['reference'];

                if (empty($ref)) {
                    continue;
                }

                $feed_references[] = $ref;
                $stats['total_in_feed']++;

                $content = $this->builder->build_content($mapped);
                $excerpt = $this->builder->build_excerpt($mapped);

                if (isset($existing_jobs[$ref])) {
                    // Offre existante : mise à jour
                    $post_id = $existing_jobs[$ref]['post_id'];
                    $updated = $this->update_job($post_id, $mapped, $content, $excerpt);
                    if ($updated) {
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                } else {
                    // Nouvelle offre : création
                    $this->create_job($mapped, $content, $excerpt);
                    $stats['created']++;
                }
            }

            // 4. Dépublication des offres retirées du flux
            foreach ($existing_jobs as $ref => $job_info) {
                // Si l'offre n'est plus dans le flux et est actuellement publiée
                if (!in_array($ref, $feed_references, true) && $job_info['status'] === 'publish') {
                    $this->handle_removed_job($job_info['post_id'], $removed_action);
                    $stats['removed']++;
                }
            }

        } catch (Throwable $e) {
            $stats['status'] = 'error';
            $stats['errors'][] = $e->getMessage();
        }

        $stats['execution_time'] = round(microtime(true) - $start_time, 2);

        // Sauvegarde de l'historique
        Config::set_last_sync($stats);

        return $stats;
    }

    /**
     * Récupère toutes les offres existantes indexées par job_reference.
     *
     * @return array<string, array{post_id: int, status: string}>
     */
    private function get_existing_jobs(): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT p.ID, p.post_status, pm.meta_value as job_ref
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'job_reference'
             WHERE p.post_type = 'job'
               AND p.post_status IN ('publish', 'draft', 'trash', 'pending', 'private')
               AND pm.meta_value != ''",
            ARRAY_A
        );

        $indexed = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                $ref = trim((string) $row['job_ref']);
                if (!empty($ref)) {
                    $indexed[$ref] = [
                        'post_id' => (int) $row['ID'],
                        'status'  => (string) $row['post_status'],
                    ];
                }
            }
        }

        return $indexed;
    }

    /**
     * Crée une nouvelle offre dans WordPress.
     *
     * @param array<string, mixed> $data
     * @param string $content
     * @param string $excerpt
     * @return int Post ID
     */
    private function create_job(array $data, string $content, string $excerpt): int
    {
        $post_id = wp_insert_post([
            'post_type'    => 'job',
            'post_status'  => 'publish',
            'post_title'   => $data['title'],
            'post_content' => $content,
            'post_excerpt' => $excerpt,
        ]);

        if (is_wp_error($post_id) || $post_id === 0) {
            return 0;
        }

        $this->update_job_meta((int) $post_id, $data);

        return (int) $post_id;
    }

    /**
     * Met à jour une offre existante.
     *
     * @param int $post_id
     * @param array<string, mixed> $data
     * @param string $content
     * @param string $excerpt
     * @return bool
     */
    private function update_job(int $post_id, array $data, string $content, string $excerpt): bool
    {
        wp_update_post([
            'ID'           => $post_id,
            'post_status'  => 'publish', // Réactivation si elle était en brouillon
            'post_title'   => $data['title'],
            'post_content' => $content,
            'post_excerpt' => $excerpt,
        ]);

        $this->update_job_meta($post_id, $data);

        return true;
    }

    /**
     * Met à jour l'ensemble des champs ACF et métadonnées SEO du poste.
     *
     * @param int $post_id
     * @param array<string, mixed> $data
     */
    private function update_job_meta(int $post_id, array $data): void
    {
        $acf_fields = [
            'job_reference'          => (string) $data['reference'],
            'job_enjeu'              => (string) $data['enjeu'],
            'job_contract_type'      => (string) $data['contract_type'],
            'job_location'           => (string) $data['location'],
            'job_country'            => (string) $data['country'],
            'job_availability'       => (string) $data['availability'],
            'job_salary'             => (string) $data['salary'],
            'job_experience'         => (string) $data['experience'],
            'job_education'          => (string) $data['education'],
            'job_external_apply_url' => (string) $data['apply_url'],
        ];

        foreach ($acf_fields as $key => $value) {
            if (function_exists('update_field')) {
                update_field($key, $value, $post_id);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }

        // Métadonnées Slim SEO (titre et description optimisés)
        $seo_title = sprintf('Recrutement %s | ATYX', $data['title']);
        $seo_desc  = sprintf(
            'Offre d’emploi %s en %s chez ATYX (%s). Découvrez les missions et postulez en ligne.',
            $data['title'],
            $data['contract_type'],
            $data['location']
        );

        update_post_meta($post_id, 'slim_seo', [
            'title'       => $seo_title,
            'description' => $seo_desc,
        ]);
    }

    /**
     * Traite une offre qui n'est plus présente dans le flux.
     *
     * @param int $post_id
     * @param string $action
     */
    private function handle_removed_job(int $post_id, string $action): void
    {
        if ($action === 'trash') {
            wp_trash_post($post_id);
        } else {
            // Par défaut : passage en brouillon (disparaît du catalogue sans erreur 404 brutale)
            wp_update_post([
                'ID'          => $post_id,
                'post_status' => 'draft',
            ]);
        }
    }
}
