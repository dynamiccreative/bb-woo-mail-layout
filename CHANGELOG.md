# Changelog

## 1.1.0

- Nouveau layout **E-commerce** : barre d'accent, en-tête de tableau coloré, photos produit dans le tableau de commande (en-tête `Product Images: yes` du `styles.css` d'un layout).
- Bloc **Produits mis en avant** : jusqu'à 3 produits choisis dans l'admin (photo, titre, prix, promo), dans les e-mails client ; produits non publiés ou masqués ignorés ; filtre `bb_email_featured_products`.
- Nouveaux placeholders : `{billing_address}`, `{shipping_address}`, `{payment_method}`, `{shipping_method}`, `{payment_url}`, `{order_meta:clé}`.
- Champ **CSS personnalisé** (balises, `@import` et scripts retirés ; jetons `{{primary}}`… disponibles).
- `bin/make-pot.php` pour régénérer le catalogue de traduction.

## 1.0.4

- Traduction anglaise livrée (`en_US`, `en_GB`) : libellés du layout, textes d'introduction par défaut, administration.
- Multilingue : l'e-mail client est rendu dans la langue de la commande (WPML `wpml_language`, Polylang), à défaut dans la langue du profil du client. Aucune bascule sans WPML ni Polylang (évite un e-mail à moitié traduit).
- Nouveaux filtres : `bb_email_locale`, `bb_email_order_language`, `bb_email_admin_uses_order_language` (e-mails admin dans la langue de la commande, désactivé par défaut).

## 1.0.3

- Mises à jour automatiques depuis GitHub (mécanisme maison `GitHubUpdater`, comme DC Visibility / GéoPrestations) : une nouvelle version poussée sur `main` est proposée dans Extensions.

## 1.0.2

- Aucun changement fonctionnel : correction de l'environnement de tests (CI wp-env).

## 1.0.1

- Le CSS du layout est écrit dans le `<head>` de l'e-mail : il ne peut plus être écrasé par une autre extension de personnalisation d'e-mails (rendu sans style constaté sur « Nouvelle commande »), et reste présent si l'inlining échoue.

## 1.0.0

- Première version : layouts Classique et Sobre, intros françaises par e-mail, activation e-mail par e-mail, aperçu et e-mail de test, import / export JSON, contrôle du logo, compatibilité HPOS / WPML / Polylang.
