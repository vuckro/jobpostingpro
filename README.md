# JobPosting.pro Sync for ATYX

Plugin WordPress sur-mesure pour synchroniser automatiquement, proprement et en continu les offres d'emploi depuis le flux XML **JobPosting.pro** vers le Custom Post Type `job` d'ATYX.

Ce plugin est conçu selon une séparation stricte des responsabilités :
* **Le plugin gère les données** : téléchargement du flux XML, normalisation, calcul différentiel (Delta Sync), création/mise à jour des posts, mise à jour des champs ACF et dépublication automatique.
* **WordPress & EtchWP gèrent l'affichage** : le rendu visuel, la grille des offres, les cartes, les filtres JavaScript et la page de détail sont gérés par les gabarits natifs du site sans la moindre ligne de code de présentation dans le plugin.

---

## Fonctionnalités

* **Synchronisation différentielle (Delta Sync)** :
  * **Nouvelles offres** : Créées automatiquement avec le balisage de blocs Gutenberg officiel et les champs ACF. Statut `publish`.
  * **Offres modifiées** : Mises à jour en direct si le contenu ou les conditions évoluent.
  * **Offres clôturées/supprimées du flux** : Passées immédiatement en statut `draft` (Brouillon) ou envoyées en corbeille. Elles disparaissent instantanément du catalogue public sans causer de rupture d'historique.
* **Respect du design existant** :
  * Génération des blocs Gutenberg (`<!-- wp:heading -->`, `<!-- wp:list {"className":"atyx-job-checklist"} -->`) pour les sections *Contexte*, *Missions*, *Profil* et *Avantages*.
  * Renseignement de tous les champs ACF requis par la grille `/recrutement/` et la fiche offre `/recrutement/{slug}`.
* **Mapping automatique des Enjeux et Contrats** :
  * Correspondance des codes du flux (`17` ➔ *Décarbonation*, `72` ➔ *Réindustrialisation*, `14`/`15` ➔ *Espaces de vie et mobilités*, `71` ➔ *Santé et alimentation*, etc.).
  * Normalisation des contrats (*CDI*, *Freelance / Prestation*, *CDD*).
  * Formatage lisible de la localisation (*Ville (Dépt) — Pays*).
* **Double mode d'exécution** :
  * Interface d'administration dans le back-office WordPress avec bouton de synchronisation immédiate et rapport détaillé.
  * Commande WP-CLI native (`wp jobposting sync`) pour déclenchement direct via le Cron serveur O2Switch.

---

## Architecture des fichiers

```
jobpostingpro/
├── jobpostingpro.php         # Point d'entrée principal du plugin
├── src/
│   ├── Plugin.php            # Amorçage (bootstrapper)
│   ├── Config.php            # Gestion des options et constantes
│   ├── Client/
│   │   └── XmlClient.php     # Téléchargement et validation sécurisée du XML
│   ├── Parser/
│   │   └── JobMapper.php     # Normalisation des balises et mapping métiers/enjeux
│   ├── Builder/
│   │   └── ContentBuilder.php# Génération des blocs Gutenberg et de l'extrait
│   ├── Sync/
│   │   └── Synchronizer.php  # Moteur de synchronisation et dépublication
│   ├── Cron/
│   │   └── Scheduler.php     # Planificateur WP-Cron
│   ├── Cli/
│   │   └── CliCommand.php    # Commande WP-CLI
│   └── Admin/
│       └── AdminPage.php     # Tableau de bord et réglages en back-office
└── README.md
```

---

## Champs ACF alimentés

| Champ ACF | Type | Description | Exemple |
|---|---|---|---|
| `job_reference` | Texte | Identifiant unique JobPosting (`<ido>`) | `2701305` |
| `job_enjeu` | Texte / Select | Enjeu stratégique ATYX | `Décarbonation` |
| `job_contract_type` | Texte / Select | Type de contrat | `CDI`, `Freelance / Prestation` |
| `job_location` | Texte | Ville et département formatés | `Paris (75) — France` |
| `job_country` | Texte | Pays | `France` |
| `job_availability` | Texte | Disponibilité | `Dès que possible` |
| `job_salary` | Texte | Rémunération | `Selon profil` |
| `job_experience` | Texte | Expérience requise | `5 ans minimum` |
| `job_education` | Texte | Diplôme / Niveau d'études | `BAC +4/5` |
| `job_external_apply_url`| URL | Lien direct JobPosting | `https://www.jobposting.pro/...` |

---

## Configuration sur le serveur O2Switch

### 1. Installation du plugin
Déployer le dossier dans `wp-content/plugins/jobpostingpro` et l'activer :
```bash
wp plugin activate jobpostingpro --path=/home/sc4appd9081/atyx.waaskit.site
```

### 2. Automatisation officielle via Webhook (Cron-job.org)
Le plugin est configuré en mode **100% Webhook externe**. Le WP-Cron interne est volontairement désactivé pour préserver les performances du site.

Pour automatiser la synchronisation :
1. Créez un compte ou connectez-vous sur [Cron-job.org](https://cron-job.org).
2. Créez un nouveau cron job :
   - **URL** : `https://atyx.waaskit.site/wp-json/jobpostingpro/v1/sync?key=VOTRE_CLE_SECRETE` (disponible dans *Offres d'emploi > Synchro JobPosting*).
   - **Méthode** : `GET`
   - **Fréquence** : Toutes les heures (ou toutes les 30 minutes).
   - **Timeout** : 60 secondes.
3. Chaque exécution appellera le Webhook de façon sécurisée et enregistrera le rapport d'exécution en base.

### 3. Alternative serveur (cPanel O2Switch / SSH)
En cas de besoin, vous pouvez également utiliser la commande WP-CLI :
```bash
/usr/local/bin/wp jobposting sync --path=/home/sc4appd9081/atyx.waaskit.site > /dev/null 2>&1
```

### 3. Exécution manuelle en ligne de commande
```bash
wp jobposting sync
```
Sortie attendue :
```
Démarrage de la synchronisation JobPosting.pro...
Success: Synchronisation terminée en 1.45 secondes !
• Total dans le flux : 65
• Créées : 64
• Mises à jour : 1
• Inchangées : 0
• Dépubliées (retirées du flux) : 7
```
