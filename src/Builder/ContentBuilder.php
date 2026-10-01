<?php

declare(strict_types=1);

namespace JobPostingPro\Builder;

if (!defined('ABSPATH')) {
    exit;
}

final class ContentBuilder
{
    /**
     * Génère le post_content structuré en blocs Gutenberg conformes à la charte ATYX.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function build_content(array $data): string
    {
        $blocks = [];

        // 1. Contexte du projet & Enjeu
        $context_paragraphs = $this->parse_context((string) ($data['desc_societe'] ?? ''));
        if (!empty($context_paragraphs)) {
            $blocks[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Contexte du projet &amp; Enjeu</h2>\n<!-- /wp:heading -->";
            foreach ($context_paragraphs as $p) {
                $blocks[] = sprintf("<!-- wp:paragraph -->\n<p class=\"wp-block-paragraph\">%s</p>\n<!-- /wp:paragraph -->", esc_html($p));
            }
        }

        // 2. Vos missions au quotidien
        $missions = $this->parse_bullet_points((string) ($data['desc_offre'] ?? ''));
        if (!empty($missions)) {
            $blocks[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Vos missions au quotidien</h2>\n<!-- /wp:heading -->";
            $items_html = '';
            foreach ($missions as $m) {
                $items_html .= sprintf("<li>%s</li>\n", esc_html($m));
            }
            $blocks[] = sprintf(
                "<!-- wp:list {\"className\":\"atyx-job-checklist\"} -->\n<ul class=\"wp-block-list atyx-job-checklist\">\n%s</ul>\n<!-- /wp:list -->",
                $items_html
            );
        }

        // 3. Profil & Compétences recherchées
        $skills = $this->parse_bullet_points((string) ($data['desc_profil'] ?? ''));
        if (!empty($skills)) {
            $blocks[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Profil &amp; Compétences recherchées</h2>\n<!-- /wp:heading -->";
            $items_html = '';
            foreach ($skills as $s) {
                $items_html .= sprintf("<li>%s</li>\n", esc_html($s));
            }
            $blocks[] = sprintf(
                "<!-- wp:list {\"className\":\"atyx-job-checklist\"} -->\n<ul class=\"wp-block-list atyx-job-checklist\">\n%s</ul>\n<!-- /wp:list -->",
                $items_html
            );
        }

        // 4. Cadre de travail & Avantages
        $blocks[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Cadre de travail &amp; Avantages</h2>\n<!-- /wp:heading -->";
        $blocks[] = "<!-- wp:list {\"className\":\"atyx-job-checklist\"} -->\n<ul class=\"wp-block-list atyx-job-checklist\">\n"
            . "<li>Rémunération attractive selon profil et expérience</li>\n"
            . "<li>Missions à forte visibilité auprès des directions de projets</li>\n"
            . "<li>Télétravail selon organisation de mission</li>\n"
            . "<li>Mutuelle d'entreprise & participation aux transports</li>\n"
            . "<li>Accompagnement de carrière et développement des compétences</li>\n"
            . "</ul>\n<!-- /wp:list -->";

        return implode("\n\n", $blocks);
    }

    /**
     * Génère l'extrait court (excerpt) à partir du contexte du poste.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function build_excerpt(array $data): string
    {
        $context = $this->clean_text((string) ($data['desc_societe'] ?? ''));
        
        // Supprimer les intitulés courants
        $context = preg_replace('/^(L\'enjeu|Le projet)\s*[:\-]?\s*/i', '', $context) ?? $context;
        $context = trim(preg_replace('/\s+/', ' ', $context) ?? '');

        if (mb_strlen($context) > 180) {
            $excerpt = mb_substr($context, 0, 177);
            $last_space = mb_strrpos($excerpt, ' ');
            if ($last_space !== false) {
                $excerpt = mb_substr($excerpt, 0, $last_space);
            }
            return $excerpt . '…';
        }

        return $context;
    }

    /**
     * Extrait les paragraphes propres du contexte projet.
     *
     * @param string $raw_html
     * @return array<string>
     */
    private function parse_context(string $raw_html): array
    {
        $clean = $this->clean_text($raw_html);
        $lines = preg_split('/\r\n|\r|\n/', $clean);
        $paragraphs = [];

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Ignorer les titres isolés de type "L'enjeu" ou "Le projet"
            if (preg_match('/^(L\'enjeu|Le projet)\s*$/i', $line)) {
                continue;
            }
            $paragraphs[] = $line;
        }

        return $paragraphs;
    }

    /**
     * Découpe un bloc de texte en liste à puces propre.
     *
     * @param string $raw_html
     * @return array<string>
     */
    private function parse_bullet_points(string $raw_html): array
    {
        $clean = $this->clean_text($raw_html);
        $lines = preg_split('/\r\n|\r|\n/', $clean);
        $points = [];

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Ignorer les titres de section généraux
            if (preg_match('/^(Missions|Les missions( du poste)?|Les compétences( recherchées)?)\s*$/i', $line)) {
                continue;
            }

            // Retirer les puces en tête de ligne (-, *, •, ;, >)
            $item = preg_replace('/^[•\-\*\;\>]\s*/u', '', $line);
            $item = trim((string) $item);

            // Retirer le point-virgule final si présent
            $item = rtrim($item, ';');

            if (!empty($item)) {
                $points[] = $item;
            }
        }

        return $points;
    }

    /**
     * Décode le HTML et convertit les <br> en retours à la ligne.
     */
    private function clean_text(string $html): string
    {
        $text = str_ireplace(['<br />', '<br/>', '<br>', '</p>'], "\n", $html);
        $text = strip_tags($text);
        return html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
