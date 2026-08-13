# Gutendex Books

Plugin WordPress autonome affichant une sélection de livres du domaine public issus de l'API publique [Gutendex](https://gutendex.com) (Project Gutenberg), via le shortcode `[books_list]`.

- **Version** : 1.0.0
- **Prérequis** : WordPress 6.0+, PHP 8.1+
- **Licence** : GPL-2.0-or-later

---

## Installation

1. Copier le dossier `gutendex-books/` dans `wp-content/plugins/`.
2. Activer « Gutendex Books » dans **Extensions**.
3. Insérer `[books_list]` dans une page ou un article.

Aucune dépendance à installer.

## Utilisation

| Attribut        | Défaut               | Description                                                |
| --------------- | -------------------- | ---------------------------------------------------------- |
| `limit`         | `10`                 | Nombre de livres, borné à 32 (taille d'une page de l'API). |
| `lang`          | -                    | Filtre de langue, code ISO 639-1. Ex. `fr`, ou `fr,en`.    |
| `search`        | -                    | Recherche par titre ou auteur.                             |
| `title`         | *(texte par défaut)* | Titre de la section. Chaîne vide pour le masquer.          |
| `intro`         | *(texte par défaut)* | Texte d'introduction. Chaîne vide pour le masquer.         |
| `heading_level` | `h2`                 | Niveau du titre, de `h2` à `h6`.                           |
| `form`          | `no`                 | `yes` affiche un formulaire de recherche et de langue.     |


```
[books_list limit="12" lang="fr" title="Nos classiques francophones"]
```

## Administration

**Réglages -> Gutendex Books** (capacité `manage_options`) :

- date de la dernière récupération réussie, nombre d'entrées encore valides en cache ;
- **Renouveler les données** : rappelle l'API et remplace chaque entrée dont la récupération aboutit. Non destructif — si l'API est indisponible, les données déjà servies restent en place ;
- **Vider le cache** : supprime les données, reconstituées au prochain affichage.

---

## Architecture

```
gutendex-books/
├── gutendex-books.php          En-tête, constantes, chargement, point d'entrée
├── uninstall.php               Nettoyage des options et transients
├── includes/
│   ├── class-cache.php         GTDX_Cache       — transients + registre des clés
│   ├── class-api-client.php    GTDX_Api_Client  — appel HTTP, erreurs, normalisation
│   ├── class-shortcode.php     GTDX_Shortcode   — [books_list]
│   ├── class-admin-page.php    GTDX_Admin_Page  — page de réglages et actions
│   └── class-plugin.php        GTDX_Plugin      — orchestration, rendu des templates
├── templates/                  Vues (échappement systématique à la sortie)
└── assets/css/books-list.css
```

## Choix techniques

### Shortcode plutôt que bloc Gutenberg

- **aucune chaîne de build** : pas de `@wordpress/scripts`, pas de `node_modules` ;
- **compatibilité** : éditeur de blocs, éditeur classique, widget texte, `do_shortcode()` ;
- **tests rapides** avec cette solution ;

### Chargement des classes par `require_once`

Plutôt qu'un autoloader ou Composer : aucune librairie tierce, cinq classes, gain d'un chargement à la demande : négligeable. 

### Cache par transients, avec registre de clés

Les transients bénéficient automatiquement d'un cache objet persistant (Redis, Memcached) lorsqu'il est disponible, sans modification de code. Deux ajouts par rapport à un usage brut :

- **registre des clés** (option `gtdx_cache_keys`) associant chaque clé à ses arguments : purge de toutes les entrées sans requête SQL directe sur `wp_options`, et réchauffement de chaque variante de shortcode réellement utilisée. ;
- **horodatage en option séparée** (`gtdx_last_fetch`) et non en transient : l'information survit à l'expiration du cache, ce qui est l'intérêt de l'afficher.

La clé ne retient que `lang` et `search` : la requête récupère toujours la première page, `limit` n'intervient qu'au rendu, donc deux shortcodes ne différant que par `limit` partagent une entrée. 
Codes langue dédoublonnés et triés : `lang="en,fr"` et `lang="fr,en"` désignent la même requête.

Durées de vie : **12 h** pour une réponse valide (le catalogue évolue lentement), filtrable via `gtdx_cache_ttl` ; **5 min** pour une erreur, afin de ne pas solliciter une API indisponible à chaque affichage sans figer le site douze heures. Une erreur ne remplace jamais des données valides déjà en cache.

### Gestion des erreurs

`WP_Error` typé plutôt que des exceptions, selon la convention WordPress :

| Code                   | Situation                                                |
| ---------------------- | -------------------------------------------------------- |
| `gtdx_http_failure`    | Échec réseau, DNS, TLS ou dépassement du timeout (10 s). |
| `gtdx_http_status`     | Réponse reçue mais statut HTTP différent de 200.         |
| `gtdx_invalid_payload` | Corps non-JSON, ou absence de la clé `results`.          |

Gutendex peut mettre plusieurs secondes à répondre, et un appel sans borne bloquerait le rendu du front. Celui-ci distingue **données indisponibles** (`WP_Error`) et **aucun résultat** (tableau vide) ; le détail technique n'est affiché qu'aux utilisateurs disposant de `manage_options`.

### Normalisation des données

Toute la tolérance aux données manquantes est concentrée dans `GTDX_Api_Client::normalize_book()` : les templates reçoivent une structure constante et ne contiennent aucun `isset()`.

### Sécurité

- `defined( 'ABSPATH' ) || exit;` en tête de chaque fichier ;
- **échappement systématique à la sortie** — `esc_html()`, `esc_url()`, `esc_attr()`, `tag_escape()` : aucune donnée d'API affichée brute ;
- actions d'administration via `admin-post.php` : contrôles effectués **avant toute sortie HTML**, et le motif POST/Redirect/GET empêche le rejeu au rafraîchissement du navigateur ;
- **triple contrôle** sur chaque action : méthode `POST` exigée, puis `current_user_can( 'manage_options' )`, puis `check_admin_referer()` — la capacité atteste du droit, le nonce l'origine de la requête, et le refus des autres méthodes ferme le cas d'un nonce valide rejoué par URL, `admin-post.php` dispatchant sur `$_REQUEST` ;
- redirections via `wp_safe_redirect()`, paramètres via `add_query_arg()` ;
- arguments du shortcode assainis et bornés avant construction de l'URL d'appel.

### Accessibilité et sémantique

- **structure** : `<section>` nommée par `aria-labelledby`, `aria-label` si le titre est masqué (`title=""`) ; chaque livre en `<article>`, métadonnées en `<dl>` ;
- **hiérarchie de titres** : niveau de section configurable de `h2` à `h6` ;
- **clavier** : liens natifs, `:focus-visible` ;
- **lecteurs d'écran** : le lien porte le titre du livre ; couvertures en `alt=""` ; attribut `lang` sur le titre quand la langue est connue ;
- **mise en page** : grille `auto-fill` / `minmax` sans media query, `loading="lazy"` et `aspect-ratio` ;
- **thème** : styles neutres héritant de la typographie et des couleurs, chargés **uniquement** sur les pages où le shortcode est présent.

---

## Limitations connues

Périmètre volontairement tenu dans l'enveloppe de temps indiquée.

1. **Pas de pagination.** `limit` est borné à 32, taille d'une page de l'API. Une pagination front supposerait la gestion des liens `next` / `previous` et un état d'URL.
2. **Pas de rafraîchissement en tâche de fond.** Le cache est reconstruit paresseusement : le premier visiteur après expiration subit la latence de l'appel API.
3. **Pas de tests automatisés.** Une suite PHPUnit avec `wp_remote_get` bouchonné via le filtre `pre_http_request` couvrirait les trois branches d'erreur et la normalisation.
4. **Registre de clés borné à 20 entrées**, contre la croissance illimitée de l'option. L'éviction supprime le transient correspondant, et seuls `lang` et `search` distinguent une entrée : le plafond n'est atteint qu'avec vingt filtres réellement différents.
5. **Pas d'écran de réglages persistants.** La durée de vie du cache se modifie par le filtre `gtdx_cache_ttl` et non par l'interface.

## Temps passé

- 30min : compréhension et analyse du besoin ;
- 2h20 : développement d'une solution ("utilisation de l'ia comme assistant et non comme remplaçant") ;
*Je viens d'un envrionnement PHP/Symfony pur et Prestashop. Le temps de développement a été un peu long (temps avec IA) car je souhaite rester maître de mon code et en comprendre le fonctionnement.*