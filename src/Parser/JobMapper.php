<?php

declare(strict_types=1);

namespace JobPostingPro\Parser;

use SimpleXMLElement;

if (!defined('ABSPATH')) {
    exit;
}

final class JobMapper
{
    /**
     * Dictionnaire de mapping des codes Enjeux de JobPosting vers les enjeux ATYX du nouveau site.
     */
    private const ENJEUX_MAP = [
        '17' => 'Décarbonation',
        '72' => 'Réindustrialisation',
        '14' => 'Espaces de vie et mobilités',
        '15' => 'Espaces de vie et mobilités',
        '16' => 'Décarbonation',
        '71' => 'Santé et alimentation',
        '0'  => 'Grands Projets & Ingénierie',
    ];

    /**
     * Dictionnaire de mapping des types de contrats.
     */
    private const CONTRACTS_MAP = [
        'cdi'       => 'CDI',
        'cdd'       => 'CDD',
        'freelance' => 'Freelance / Prestation',
        'stage'     => 'Stage',
        'alternance'=> 'Alternance',
    ];

    /**
     * Mappe un nœud <job> XML en tableau associatif normalisé.
     *
     * @param SimpleXMLElement $job
     * @return array<string, mixed>
     */
    public function map(SimpleXMLElement $job): array
    {
        $reference = trim((string) $job->ido);
        $title     = html_entity_decode(trim((string) $job->titre), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Mapping de l'enjeu
        $enjeu_code = trim((string) $job->enjeux);
        $enjeu = self::ENJEUX_MAP[$enjeu_code] ?? 'Grands Projets & Ingénierie';

        // Mapping du contrat
        $raw_contract = strtolower(trim((string) $job->contrat));
        $contract = self::CONTRACTS_MAP[$raw_contract] ?? (string) $job->contrat;
        if (empty($contract)) {
            $contract = 'CDI';
        }

        // Localisation
        $location_data = $job->localisation ?? null;
        $cp    = $location_data ? trim((string) $location_data->cp) : '';
        $ville = $location_data ? html_entity_decode(trim((string) $location_data->ville), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        $pays  = $location_data ? trim((string) $location_data->lib_pays) : 'France';
        if (empty($pays)) {
            $pays = 'France';
        }

        $formatted_location = $this->format_location($ville, $cp, $pays);

        // Métadonnées complémentaires
        $experience = trim((string) $job->experience);
        $education  = trim((string) $job->diplome);
        $raw_salary = html_entity_decode(trim((string) $job->salaire), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $salary     = !empty($raw_salary) ? $raw_salary : 'Selon profil';

        $apply_url = trim((string) $job->url);
        $email     = trim((string) $job->email);
        $date_paru = trim((string) $job->date_paru);

        // Blocs de description
        $desc = $job->description ?? null;
        $desc_societe = $desc ? (string) $desc->desc_societe : '';
        $desc_offre   = $desc ? (string) $desc->desc_offre : '';
        $desc_profil  = $desc ? (string) $desc->desc_profil : '';

        return [
            'reference'        => $reference,
            'title'            => $title,
            'enjeu'            => $enjeu,
            'contract_type'    => $contract,
            'city'             => $ville,
            'postal_code'      => $cp,
            'country'          => $pays,
            'location'         => $formatted_location,
            'salary'           => $salary,
            'availability'     => 'Dès que possible',
            'experience'       => $experience,
            'education'        => $education,
            'apply_url'        => $apply_url,
            'email'            => $email,
            'date_paru'        => $date_paru,
            'desc_societe'     => $desc_societe,
            'desc_offre'       => $desc_offre,
            'desc_profil'      => $desc_profil,
        ];
    }

    /**
     * Formate la chaîne de localisation lisible : ex. "La Rochelle (17) — France".
     */
    private function format_location(string $ville, string $cp, string $pays): string
    {
        $dept = '';
        if (strlen($cp) >= 2) {
            $dept = substr($cp, 0, 2);
        }

        $parts = [];
        if (!empty($ville)) {
            $parts[] = !empty($dept) ? sprintf('%s (%s)', $ville, $dept) : $ville;
        } elseif (!empty($dept)) {
            $parts[] = sprintf('Dép. %s', $dept);
        }

        if (!empty($pays)) {
            $parts[] = $pays;
        }

        return !empty($parts) ? implode(' — ', $parts) : 'France';
    }
}
