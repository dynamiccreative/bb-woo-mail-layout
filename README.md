# BB Woo Mail Layout

Plugin WordPress bleuebuzz de mise en forme des e-mails transactionnels WooCommerce : layout codé en tables HTML, 100 % français, quelques variables réglables par le client.

- WordPress ≥ 6.4, WooCommerce ≥ 8.0, PHP ≥ 8.1. Compatible HPOS.
- Zéro dépendance runtime (autoloader PSR-4 embarqué ; Composer sert uniquement aux outils de dev).
- Réglages : **WooCommerce → Réglages → E-mails → Mise en forme bleuebuzz**.

## Fonctionnement

| Étape | Mécanisme |
|---|---|
| Suivi de l'e-mail en cours | `woocommerce_before_template_part` / `after_template_part` : pile des `WC_Email` passés aux templates. WooCommerce ne transmet pas `$email` à `email-header.php` / `email-footer.php`, d'où cette pile. |
| Layout | Filtre `wc_get_template` : `emails/email-header.php` et `emails/email-footer.php` pointent vers `templates/emails/`, qui délèguent à `layouts/{layout}/header.php` et `footer.php`. |
| CSS | Filtre `woocommerce_email_styles` (priorité 5) : le CSS natif est remplacé par `layouts/base.css` + `layouts/{layout}/styles.css` ; les CSS ajoutés ensuite par d'autres extensions sont conservés. L'inlining reste fait par WooCommerce (Emogrifier `CssInliner`). |
| Contenu | Hooks WooCommerce standard. Les templates de contenu natifs sont surchargés (`templates/emails/*.php`) uniquement pour remplacer le « Hi %s » anglais par l'intro FR et franciser les libellés du tableau et des adresses. |
| E-mail désactivé | Aucun filtre ne s'applique : rendu WooCommerce natif à l'identique. |
| Email improvements | `pre_option_woocommerce_feature_email_improvements_enabled` et `…block_email_editor…` forcés à `no` tant que le plugin est actif ; l'option stockée n'est jamais modifiée. |

Réglages : une option sérialisée `bb_woo_mail_layout` (versionnée, migrée à l'activation), supprimée à la désinstallation seulement.

## Hooks publics

| Hook | Type | Rôle |
|---|---|---|
| `bb_email_layout` | filtre `( string $slug, ?WC_Email $email )` | Forcer un layout pour un e-mail. |
| `bb_email_layouts` | filtre `( array $layouts )` | Déclarer des layouts externes : `slug => [ 'label' => …, 'path' => dossier ]`. |
| `bb_email_enabled` | filtre `( bool $enabled, string $email_id )` | Forcer l'activation du layout. |
| `bb_email_placeholders` | filtre `( array $values, ?WC_Email $email )` | Ajouter des placeholders : `'{cle}' => 'texte'` ou `[ 'url' => …, 'label' => … ]` (rendu en lien). |
| `bb_email_intro_text` | filtre `( string $text, WC_Email $email )` | Texte d'intro brut (avant placeholders). |
| `bb_email_before_content` | action `( ?WC_Email $email )` | Après titre et intro, avant le contenu WooCommerce. |
| `bb_email_after_content` | action `( ?WC_Email $email )` | Après le contenu WooCommerce, avant le bloc d'aide. |
| `bb_email_footer_text` | filtre `( string $html, ?WC_Email $email )` | HTML des mentions de pied de page. |
| `bb_email_unsubscribe_url` | filtre `( string $url, ?WC_Email $email )` | Lien de désinscription (affiché seulement si non vide). |
| `bb_email_tracking_url` | filtre `( string $url, WC_Order $order )` | URL de suivi pour `{tracking_url}` (Colissimo, GLS…). WooCommerce Shipment Tracking est détecté nativement. |
| `bb_email_override_templates` | filtre `( string[] $templates )` | Templates de contenu surchargés (retirer une entrée pour revenir au template natif). |
| `bb_email_css` | filtre `( string $css, ?WC_Email $email )` | CSS final avant inlining. |
| `bb_email_prepare_simulation` | action `( WC_Email $email, WC_Order $order )` | Compléter les propriétés d'un e-mail tiers pour l'aperçu / le test. |
| `bb_email_featured_products` | filtre `( int[] $ids, WC_Email $email )` | Produits mis en avant pour un e-mail (vide = pas de bloc ; 3 affichés au maximum). |
| `bb_email_locale` | filtre `( string $locale, mixed $object )` | Locale de rendu d'un e-mail client (vide = pas de bascule). Appliqué seulement si WPML ou Polylang est actif. |
| `bb_email_order_language` | filtre `( string $code, mixed $object )` | Code langue d'une commande pour une extension non détectée (ex. stockage HPOS propre à une extension). |
| `bb_email_admin_uses_order_language` | filtre `( bool $enabled, WC_Email $email )` | Rendre aussi les e-mails admin dans la langue de la commande (faux par défaut). |
| `bb_email_conflicting_plugins` | filtre `( string[] $patterns )` | Fragments de dossiers de plugins signalés comme concurrents. |
| `bb_email_disabled_wc_features` | filtre `( string[] $options )` | Options de fonctionnalités WooCommerce neutralisées (appliqué au chargement : à utiliser depuis un mu-plugin). |

## Traductions et multilingue

- Chaînes source en français, domaine `bb-woo-mail-layout`, `languages/bb-woo-mail-layout.pot`. Traductions livrées : `en_US`, `en_GB`.
- Textes saisis dans l'admin (horaires, nom légal, adresse, mentions, intros) : traduisibles dans WPML (`wpml-config.xml`, admin-texts) et Polylang (Langues → Traductions de chaînes).
- Langue d'un e-mail client : langue de la commande (WPML `wpml_language` ; Polylang `pll_get_post_language()`), sinon langue du profil du client, sinon langue du site. **Aucune bascule sans WPML ni Polylang** : WooCommerce écrit alors ses propres textes dans la langue du site et l'e-mail serait à moitié traduit.
- E-mails admin : langue du site (filtre `bb_email_admin_uses_order_language` pour suivre la commande).
- Testé avec Polylang 3.8 (site fr_FR, langues fr/en). WPML : à valider sur un site équipé.

## Ajouter un layout

1. Copier `layouts/classique/` vers `layouts/mon-layout/`.
2. Renommer dans l'en-tête de `styles.css` : `Layout Name: Mon layout`.
3. Adapter `header.php` (ouvre le document et la cellule de contenu) et `footer.php` (la referme). Les variables disponibles sont dans le tableau `$v` (voir `LayoutRenderer::view_vars()`) : `colors`, `logo`, `heading`, `intro_html`, `help`, `socials`, `footer`, `settings`, `site_title`, `site_url`, `lang`, `charset`, `google_font_url`, `email`.
4. Jetons CSS disponibles : `{{primary}}`, `{{primary_text}}`, `{{button}}`, `{{button_text}}`, `{{text}}`, `{{muted}}`, `{{border}}`, `{{soft}}`, `{{background}}`, `{{font}}`.
5. Pour afficher les photos produit dans le tableau de commande, ajouter `Product Images: yes` dans l'en-tête de `styles.css` (comme le layout E-commerce).
6. Le bloc produits mis en avant est fourni par `$v['featured_html']` (gabarit commun `layouts/partials/featured-products.php`) : l'insérer dans `footer.php`.

## Placeholders

`{site_title}`, `{site_url}`, `{customer_first_name}`, `{customer_last_name}`, `{order_number}`, `{order_date}`, `{order_total}`, `{order_url}`, `{tracking_url}`, `{admin_email}`, `{shop_phone}`, `{billing_address}`, `{shipping_address}`, `{payment_method}`, `{shipping_method}`, `{payment_url}` (seulement si la commande est à régler), `{order_meta:clé}` (métadonnée scalaire de la commande). Extensible par `bb_email_placeholders` : valeur texte, lien `[ 'url' => …, 'label' => … ]` ou HTML `[ 'html' => … ]`.

Le layout apparaît automatiquement dans le sélecteur. Depuis un thème, déclarer le dossier via `bb_email_layouts`.

## Développement

```bash
composer install
npm install
composer lint          # PHPCS WordPress-Extra + WooCommerce
composer analyse       # PHPStan niveau 6
npm run env:start      # wp-env (Docker)
npm test               # PHPUnit dans wp-env
npm run snapshots      # régénère tests/snapshots/{layout}/{email}.html
npm run build          # build/bb-woo-mail-layout.zip
```

La CI GitHub Actions (`.github/workflows/ci.yml`) lance lint, PHPStan et PHPUnit, puis attache le zip à la release sur un tag `v*`.

## Mises à jour

Mécanisme maison des plugins Dynamic Creative / bleuebuzz : `lib/GitHubUpdater.php` (fork patché de Ryan Sechrest, classe préfixée `BB_WML_`), branché dans `Plugin::register_updater()`.

- L'en-tête `Update URI` du fichier principal pointe vers ce dépôt.
- La version proposée aux sites est la `Version` de l'en-tête de `bb-woo-mail-layout.php` **sur la branche `main`** : pour publier, incrémenter `Version` (et `BB_WML_VERSION`), compléter `CHANGELOG.md`, pousser sur `main`.
- Le paquet installé est le zip de la branche `main`.
- Dépôt public : aucun jeton. Dépôt privé : enregistrer un jeton GitHub dans l'option `bb_wml_github_access_token`.

Les tags `v*` servent uniquement à attacher un zip « propre » (sans outillage de dev) à une release GitHub, pour une installation manuelle.

## Migration depuis Flycart Email Customizer

Voir [docs/MIGRATION-FLYCART.md](docs/MIGRATION-FLYCART.md).
