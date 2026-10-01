<?php

declare(strict_types=1);

namespace JobPostingPro\Client;

use SimpleXMLElement;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class XmlClient
{
    /**
     * Télécharge et analyse le flux XML JobPosting.pro.
     *
     * @param string $url
     * @return SimpleXMLElement
     * @throws RuntimeException
     */
    public function fetch(string $url): SimpleXMLElement
    {
        $response = wp_remote_get($url, [
            'timeout'    => 30,
            'user-agent' => 'ATYX-JobPostingPro-Sync/1.0.0; ' . home_url(),
            'headers'    => [
                'Accept' => 'application/xml, text/xml, */*',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException(
                'Erreur de connexion au flux JobPosting : ' . $response->get_error_message()
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException(
                sprintf('Le serveur JobPosting a retourné le code HTTP %d.', $code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new RuntimeException('Le contenu du flux XML retourné est vide.');
        }

        // Nettoyage des éventuels caractères nuls ou de contrôle non valides en XML
        $clean_body = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $body);
        if ($clean_body === null) {
            $clean_body = $body;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($clean_body, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = !empty($errors) ? $errors[0]->message : 'Format XML invalide.';
            throw new RuntimeException('Impossible d\'analyser le flux XML : ' . trim($msg));
        }

        return $xml;
    }
}
