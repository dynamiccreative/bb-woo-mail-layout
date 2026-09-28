# Migration depuis Flycart « Email Customizer for WooCommerce »

Procédure pour remplacer Flycart par BB Woo Mail Layout (première cible : moulinolivette.fr).

## 1. Relever les réglages Flycart (avant toute désactivation)

Ouvrir chaque template Flycart utilisé et noter :

| À relever | Où le reporter dans BB Woo Mail Layout |
|---|---|
| Logo (URL) | **Logo → Image**. Si l'image est hébergée sur un domaine tiers (cas moulinolivette.fr), la téléverser dans la médiathèque du site et la choisir depuis la médiathèque. |
| Couleur d'en-tête / de titres | Couleur principale |
| Couleur des boutons | Couleur des boutons |
| Couleur du texte, du fond | Couleur du texte, Couleur de fond extérieur |
| Police | Police (ou Google Font avec repli) |
| Téléphone, e-mail, horaires | Besoin d'aide ? |
| Liens réseaux sociaux | Réseaux sociaux |
| Raison sociale, adresse, mentions | Pied de page |
| Textes personnalisés par e-mail | Textes d'introduction (laisser vide pour le texte FR par défaut) |

Faire une capture de chaque e-mail Flycart (commande en cours, terminée, nouvelle commande admin) pour comparer.

## 2. Installer et régler

1. Installer et activer BB Woo Mail Layout. Tant que Flycart est actif, une notice signale le conflit : c'est normal.
2. Reporter les réglages relevés, enregistrer.
3. Vérifier qu'aucune notice « logo injoignable » n'apparaît.

## 3. Basculer

1. Désactiver Flycart (ne pas le supprimer tout de suite).
2. Dans **E-mails mis en forme**, vérifier que tous les e-mails utiles sont cochés, y compris ceux des extensions (Colissimo, GLS, factures PDF…).
3. Dans WooCommerce → Réglages → E-mails, relire sujets et titres de chaque e-mail : Flycart pouvait les surcharger. Corriger les éventuels libellés anglais.

## 4. Vérifier par envoi de test

Depuis **Aperçu et e-mail de test**, choisir une commande réelle récente et envoyer au minimum :

- Commande en cours (client), Commande terminée (client), Nouvelle commande (admin) ;
- Facture / détails de commande, Réinitialisation du mot de passe.

Destinataires : une boîte Gmail, une boîte Outlook (desktop Windows si possible), une boîte Apple Mail / iOS, une boîte Orange. Contrôler : logo affiché, tableau de commande, adresses, pied de page, textes en français, aucune image cassée.

## 5. Nettoyer

Après quelques jours sans retour client : supprimer Flycart (et son éventuelle licence). Conserver l'export JSON des réglages (**Import / export → Exporter**) pour les prochains sites.
