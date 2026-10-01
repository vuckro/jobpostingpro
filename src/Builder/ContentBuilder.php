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

        $raw_societe = (string) ($data['desc_societe'] ?? '');
        $raw_offre   = (string) ($data['desc_offre'] ?? '');
        $raw_profil  = (string) ($data['desc_profil'] ?? '');

        // Extraction intelligente du contexte projet s'il est logé au début de desc_offre
        $extra_context = '';
        if (preg_match('/^(.*?)(?:Les missions(?: du poste)?|Missions|Vos missions)\s*[:\-]?\s*(.*)$/is', $raw_offre, $m)) {
            $potential_proj = trim($m[1]);
            if (!empty($potential_proj) && (stripos($potential_proj, 'projet') !== false || stripos($potential_proj, 'cadre') !== false)) {
                $extra_context = $potential_proj;
                $raw_offre = $m[2];
            }
        }

        // 1. Contexte du projet & Enjeu
        $full_context_raw = $raw_societe . "\n" . $extra_context;
        $context_paragraphs = $this->parse_context($full_context_raw);
        if (!empty($context_paragraphs)) {
            $blocks[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Contexte du projet &amp; Enjeu</h2>\n<!-- /wp:heading -->";
            foreach ($context_paragraphs as $p) {
                $blocks[] = sprintf("<!-- wp:paragraph -->\n<p class=\"wp-block-paragraph\">%s</p>\n<!-- /wp:paragraph -->", esc_html($p));
            }
        }

        // 2. Vos missions au quotidien
        $missions = $this->parse_bullet_points($raw_offre);
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
        $skills = $this->parse_bullet_points($raw_profil);
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
            . "<li>Rémunération attractive selon profil et expérience (Convention SYNTEC)</li>\n"
            . "<li>Missions d'envergure auprès des grands donneurs d'ordres industriels</li>\n"
            . "<li>Organisation du travail flexible (télétravail selon organisation de mission)</li>\n"
            . "<li>Mutuelle d'entreprise & prévoyance haut de gamme</li>\n"
            . "<li>Carte restaurant & participation aux frais de transport</li>\n"
            . "<li>Accompagnement de carrière de proximité et formations certifiantes</li>\n"
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
        $raw_societe = (string) ($data['desc_societe'] ?? '');
        $context = $this->clean_text($raw_societe);
        
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
            if (preg_match('/^(L\'enjeu|Le projet)\s*[:\-]?\s*$/i', $line)) {
                continue;
            }

            // Retirer un éventuel préfixe en début de paragraphe
            $line = preg_replace('/^(L\'enjeu|Le projet)\s*[:\-]\s*/i', '', $line) ?? $line;
            $line = trim($line);

            if (!empty($line)) {
                $paragraphs[] = $line;
            }
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

            // Ignorer les titres de section généraux ou titres résiduels
            if (preg_match('/^(Missions|Les missions( du poste)?|Vos missions|Les compétences( recherchées)?|Profil recherché|Le projet|L\'enjeu)\s*[:\-]?\s*$/i', $line)) {
                continue;
            }

            // Si la ligne commence par "Dans le cadre de..." et qu'elle s'est glissée ici au tout début, on l'ignore comme puce
            if (preg_match('/^Dans le cadre (de|d\')/i', $line) && count($points) === 0) {
                continue;
            }

            // Retirer les puces en tête de ligne (-, *, •, ;, >, –, —)
            $item = preg_replace('/^[•\-\*\;\>–—]\s*/u', '', $line);
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
     * Décode le HTML, supprime les balises et convertit les sauts de ligne.
     */
    private function clean_text(string $html): string
    {
        // Remplacement des balises de saut de ligne HTML par des retours chariot
        $text = str_ireplace(['<br />', '<br/>', '<br>', '</p>', '</div>', '</li>'], "\n", $html);
        $text = strip_tags($text);

        // Décodage des entités HTML (&oelig;, &nbsp;, etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Nettoyage des espaces insécables et caractères invisibles (zero-width)
        $text = str_replace(
            ["\xc2\xa0", "\u{00A0}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"],
            ' ',
            $text
        );

        // Nettoyage des espaces multiples par ligne
        $lines = explode("\n", $text);
        $cleaned_lines = array_map(function (string $l): string {
            return trim(preg_replace('/[ \t]+/', ' ', $l) ?? $l);
        }, $lines);

        return trim(implode("\n", $cleaned_lines));
    }
}
