<?php

declare(strict_types=1);

namespace JobPostingPro\Admin;

use JobPostingPro\Config;
use JobPostingPro\Cron\Scheduler;
use JobPostingPro\Sync\Synchronizer;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'handle_manual_sync']);
        add_action('admin_init', [$this, 'handle_save_settings']);
    }

    public function add_menu_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=job',
            'Synchronisation JobPosting',
            'Synchro JobPosting',
            'manage_options',
            'jobpostingpro-sync',
            [$this, 'render']
        );
    }

    public function handle_manual_sync(): void
    {
        if (!isset($_POST['jobpostingpro_manual_sync'])) {
            return;
        }

        check_admin_referer('jobpostingpro_sync_action', 'jobpostingpro_sync_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        $synchronizer = new Synchronizer();
        $stats = $synchronizer->sync();

        $redirect_url = add_query_arg([
            'post_type' => 'job',
            'page'      => 'jobpostingpro-sync',
            'synced'    => '1',
            'status'    => $stats['status'],
        ], admin_url('edit.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_save_settings(): void
    {
        if (!isset($_POST['jobpostingpro_save_settings'])) {
            return;
        }

        check_admin_referer('jobpostingpro_settings_action', 'jobpostingpro_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        if (isset($_POST['feed_url'])) {
            update_option(Config::OPTION_FEED_URL, esc_url_raw((string) $_POST['feed_url']));
        }

        if (isset($_POST['removed_action'])) {
            $action = in_array($_POST['removed_action'], ['draft', 'trash'], true) ? $_POST['removed_action'] : 'draft';
            update_option(Config::OPTION_REMOVED_ACTION, $action);
        }

        if (isset($_POST['cron_frequency'])) {
            $freq = in_array($_POST['cron_frequency'], ['hourly', 'twicedaily', 'daily'], true) ? $_POST['cron_frequency'] : 'hourly';
            update_option(Config::OPTION_CRON_FREQUENCY, $freq);
            Scheduler::reschedule();
        }

        $redirect_url = add_query_arg([
            'post_type' => 'job',
            'page'      => 'jobpostingpro-sync',
            'saved'     => '1',
        ], admin_url('edit.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function render(): void
    {
        $feed_url       = Config::get_feed_url();
        $removed_action = Config::get_removed_action();
        $cron_freq      = Config::get_cron_frequency();
        $last_sync      = Config::get_last_sync();

        $published_count = (int) wp_count_posts('job')->publish;
        $draft_count     = (int) wp_count_posts('job')->draft;

        ?>
        <div class="wrap" style="max-width: 1000px;">
            <h1 style="display:flex; align-items:center; gap:10px;">
                <span class="dashicons dashicons-update" style="font-size:32px; width:32px; height:32px;"></span>
                Synchronisation JobPosting.pro
            </h1>

            <?php if (isset($_GET['synced']) && $_GET['synced'] === '1') : ?>
                <?php if (isset($_GET['status']) && $_GET['status'] === 'success') : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><strong>Synchronisation réussie !</strong> Les offres d'emploi ont été actualisées en base.</p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>Erreur lors de la synchronisation :</strong> Consultez les logs ci-dessous.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['saved']) && $_GET['saved'] === '1') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Réglages enregistrés avec succès.</p>
                </div>
            <?php endif; ?>

            <!-- STATS CARDS -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <div style="color:#646970; font-size:12px; font-weight:600; text-transform:uppercase;">Offres Publiées</div>
                    <div style="font-size:28px; font-weight:700; color:#1d2327; margin-top:5px;"><?php echo esc_html((string) $published_count); ?></div>
                    <div style="font-size:12px; color:#50575e; margin-top:4px;">Visibles sur le site</div>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <div style="color:#646970; font-size:12px; font-weight:600; text-transform:uppercase;">Offres Retirées (Brouillons)</div>
                    <div style="font-size:28px; font-weight:700; color:#d63638; margin-top:5px;"><?php echo esc_html((string) $draft_count); ?></div>
                    <div style="font-size:12px; color:#50575e; margin-top:4px;">Dépubliées automatiquement</div>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:4px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <div style="color:#646970; font-size:12px; font-weight:600; text-transform:uppercase;">Dernier passage</div>
                    <div style="font-size:15px; font-weight:600; color:#1d2327; margin-top:10px;">
                        <?php echo !empty($last_sync['timestamp']) ? esc_html($last_sync['timestamp']) : 'Jamais'; ?>
                    </div>
                    <div style="font-size:12px; color:#50575e; margin-top:4px;">
                        <?php echo !empty($last_sync['execution_time']) ? sprintf('Exécuté en %s s', esc_html((string) $last_sync['execution_time'])) : ''; ?>
                    </div>
                </div>
            </div>

            <!-- BOUTON SYNC MANUELLE -->
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:4px; margin-bottom:20px;">
                <h2 style="margin-top:0;">Déclencher la synchronisation immédiate</h2>
                <p style="color:#50575e;">
                    Cette action télécharge le flux XML, crée les nouvelles annonces, met à jour les existantes et passe en brouillon les annonces qui ont été retirées de JobPosting.pro.
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('jobpostingpro_sync_action', 'jobpostingpro_sync_nonce'); ?>
                    <button type="submit" name="jobpostingpro_manual_sync" class="button button-primary button-hero" style="display:inline-flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-update"></span>
                        Synchroniser maintenant
                    </button>
                </form>
            </div>

            <!-- DERNIER RAPPORT DE SYNCHRO -->
            <?php if (!empty($last_sync)) : ?>
                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:4px; margin-bottom:20px;">
                    <h2 style="margin-top:0;">Détails du dernier passage</h2>
                    <table class="widefat striped" style="border:none;">
                        <tbody>
                            <tr>
                                <th style="width:250px;">Statut</th>
                                <td>
                                    <?php if (($last_sync['status'] ?? '') === 'success') : ?>
                                        <span style="color:#00a32a; font-weight:bold;">● Succès</span>
                                    <?php else : ?>
                                        <span style="color:#d63638; font-weight:bold;">● Erreur</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Offres détectées dans le flux XML</th>
                                <td><strong><?php echo esc_html((string) ($last_sync['total_in_feed'] ?? 0)); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Nouvelles offres créées</th>
                                <td><span style="color:#00a32a; font-weight:bold;">+<?php echo esc_html((string) ($last_sync['created'] ?? 0)); ?></span></td>
                            </tr>
                            <tr>
                                <th>Offres mises à jour</th>
                                <td><?php echo esc_html((string) ($last_sync['updated'] ?? 0)); ?></td>
                            </tr>
                            <tr>
                                <th>Offres dépubliées (absentes du flux)</th>
                                <td><span style="color:#d63638; font-weight:bold;">-<?php echo esc_html((string) ($last_sync['removed'] ?? 0)); ?></span></td>
                            </tr>
                            <?php if (!empty($last_sync['errors'])) : ?>
                                <tr>
                                    <th>Erreurs signalées</th>
                                    <td style="color:#d63638;">
                                        <?php echo esc_html(implode('<br>', $last_sync['errors'])); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- PARAMÈTRES DU PLUGIN -->
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:4px; margin-bottom:20px;">
                <h2 style="margin-top:0;">Configuration du flux et des règles</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('jobpostingpro_settings_action', 'jobpostingpro_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="feed_url">URL du flux XML JobPosting</label></th>
                            <td>
                                <input type="url" name="feed_url" id="feed_url" value="<?php echo esc_attr($feed_url); ?>" class="large-text" required>
                                <p class="description">Le flux XML officiel généré par votre ATS JobPosting.pro.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="removed_action">Action pour les offres retirées du flux</label></th>
                            <td>
                                <select name="removed_action" id="removed_action">
                                    <option value="draft" <?php selected($removed_action, 'draft'); ?>>Passer en Brouillon (recommandé : masque sans erreur 404)</option>
                                    <option value="trash" <?php selected($removed_action, 'trash'); ?>>Envoyer dans la Corbeille</option>
                                </select>
                                <p class="description">Dès qu'une annonce n'est plus présente dans le XML, elle est automatiquement retirée de la page recrutement publique.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cron_frequency">Fréquence du Cron WordPress</label></th>
                            <td>
                                <select name="cron_frequency" id="cron_frequency">
                                    <option value="hourly" <?php selected($cron_freq, 'hourly'); ?>>Toutes les heures</option>
                                    <option value="twicedaily" <?php selected($cron_freq, 'twicedaily'); ?>>Deux fois par jour (toutes les 12 heures)</option>
                                    <option value="daily" <?php selected($cron_freq, 'daily'); ?>>Une fois par jour (toutes les 24 heures)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="jobpostingpro_save_settings" class="button button-secondary">Enregistrer les réglages</button>
                    </p>
                </form>
            </div>

            <!-- WEBHOOK CRON-JOB.ORG -->
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:4px; margin-bottom:20px;">
                <h2 style="margin-top:0;">Webhook externe (Cron-job.org)</h2>
                <p style="color:#50575e;">
                    Pour déclencher la synchronisation à distance via un service externe comme <strong>Cron-job.org</strong>, configurez une requête HTTP <code>GET</code> vers cette URL sécurisée :
                </p>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="text" readonly value="<?php echo esc_attr(\JobPostingPro\Rest\RestApi::get_webhook_url()); ?>" class="large-text code" style="background:#f6f7f7;">
                </div>
                <p class="description">Chaque appel HTTP sur cette URL exécute la synchronisation et renvoie le bilan en JSON.</p>
            </div>

            <!-- COMMANDE CRON O2SWITCH -->
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:4px;">
                <h2 style="margin-top:0;">Automatisation serveur alternative (cPanel O2Switch)</h2>
                <p style="color:#50575e;">
                    Vous pouvez également utiliser la commande WP-CLI directe sur le serveur :
                </p>
                <pre style="background:#f6f7f7; padding:12px; border-radius:3px; overflow-x:auto; font-family:monospace; border:1px solid #dcdcde;">/usr/local/bin/wp jobposting sync --path=<?php echo esc_html(ABSPATH); ?> > /dev/null 2>&1</pre>
                <p class="description">Fréquence conseillée sur O2Switch : Toutes les 2 heures (ex : <code>0 */2 * * *</code>).</p>
            </div>
        </div>
        <?php
    }
}
