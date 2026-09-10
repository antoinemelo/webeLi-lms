# liike — CMS adapté aux parcours pédagogiques

Liike est un CMS pédagogique volontairement simple : PHP 8.2, SQLite et HTML/JS. La présentation repose sur Bootstrap 5.3 et Bootstrap Icons, servis localement, complétés par un thème CSS léger. Le moteur mPDF installé sous `./vendor/` ou `../vendor/` produit les documents PDF sans Chromium. Ce dossier de dépendances PHP est exclu par défaut des instances et publications préparées, puis conservé lors des mises à jour Git.

## Documentation

- [Vue d’ensemble de la documentation](docs/README.md)
- [Guide utilisateur](docs/guide-utilisateur.md)
- [Modèle fonctionnel et règles métier](docs/modele-fonctionnel.md)
- [Architecture technique et base SQLite](docs/architecture.md)
- [Installation, exploitation et emails](docs/exploitation.md)
- [Discussions privées et notifications PWA](docs/discussions.md)
- [Limites connues et prochaines étapes](docs/limitations-roadmap.md)

La documentation décrit les sources de développement. Avec le nouvel outil, la mise à jour via **Maintenance de l’application** télécharge et vérifie la publication complète, puis remplace seulement les fichiers nouveaux ou différents. Les sauvegardes de code contiennent les anciennes versions des fichiers remplacés ou supprimés et un journal des changements ; les bibliothèques inchangées ne sont pas dupliquées. Ce gain nécessite l’installation du code de maintenance correspondant ; une publication de documentation seule ne l’active pas. Les détails de déploiement et de restauration figurent dans le [guide d’exploitation](docs/exploitation.md#mettre-à-jour-depuis-la-superadministration).

## Accès de démonstration

Enseignante superadmin :

```text
Identifiant : nora
Mot de passe : Elan-Nora-2026!
```

Élèves, sans mot de passe : `LIROS`, `SADIA` ou `NOMAR`.

Les codes élèves sont calculés à partir des deux premières lettres du prénom et des trois premiers caractères non blancs du nom. Par exemple, Maya **DA COSTA** reçoit `MADAC`.

Pour retrouver la démo initiale :

```bash
python3 scripts/apr.py --profile demo
```

Lancé sans option, le script présente un menu interactif donnant accès à toutes les opérations :

```bash
python3 scripts/apr.py
```

Pour une base vierge contenant uniquement le superadmin :

```bash
python3 scripts/apr.py --profile blank
```

Pour préparer un dossier d’instance locale autonome, destiné à être copié directement dans une racine Web telle que `/lms/edu/` :

```bash
python3 scripts/apr.py --profile blank --instance /tmp/liike-instance
```

Le script prépare uniquement le dossier local. Il n’effectue aucun transfert FTP.
Le dossier racine `vendor/` n’est pas copié par défaut. Il peut être installé manuellement à la racine de l’instance ou dans son répertoire parent. Pour l’embarquer exceptionnellement, ajouter `--include-vendor`.

## Dépendances et répertoires `vendor`

Deux emplacements portant le nom `vendor` ont des rôles différents :

| Emplacement | Contenu | Publication Git |
|---|---|---|
| `./vendor/` ou `../vendor/` | dépendances PHP Composer, notamment mPDF | exclu par défaut |
| `assets/vendor/` dans une instance, `public/assets/vendor/` en développement | Bootstrap et Bootstrap Icons servis au navigateur | toujours inclus |

Pour installer mPDF à la racine de l’application :

```bash
composer install --no-dev --prefer-dist
```

Le dossier Composer complet peut aussi être placé dans le parent immédiat de l’application. Par exemple, une application installée sous `/lms/edu/` accepte `/lms/edu/vendor/` ou `/lms/vendor/`. Aucun répertoire nommé `/server` ou `/serveur` n’est requis, créé ou recherché. Le partage par le parent dépend uniquement des droits d’accès accordés à PHP par l’hébergement.

## Préparer une publication Git

`apr.py` peut aussi produire un dépôt de diffusion directement exploitable par une instance Web. Le dépôt distant par défaut est `git@github.com:antoinemelo/webeLi-lms.git` et la branche est `main` :

```bash
python3 scripts/apr.py \
  --git-release ../git-release
```

Cette commande crée le dépôt local, configure `origin`, génère `VERSION` et `RELEASE.json`, puis crée un commit. Elle ne contacte pas GitHub. Après contrôle du contenu, le push doit être demandé explicitement :

```bash
python3 scripts/apr.py \
  --git-release ../git-release \
  --force \
  --git-push
```

`storage/apr.sqlite`, les fichiers propres à une installation sous `uploads/` et le dossier racine `vendor/` sont ignorés et ne sont jamais ajoutés au dépôt par défaut. Seule l’option explicite `--include-vendor` autorise son inclusion. Une publication ultérieure conserve l’historique Git et s’arrête si le dépôt local contient des modifications non validées.

## Ce qui est couvert

- bibliothèque de pages indépendantes, prêtes ou en brouillon ;
- recherche de pages par texte, statut, tags et objectifs ;
- import/export JSON des pages, parcours et élèves ;
- six blocs : texte Markdown avec aperçu, image avec description alternative et légende, document téléchargeable, vidéo/audio, intégration externe (iframe) et travail à rendre ;
- champs adaptés au type : import ou adresse pour les images et documents, hauteur réglable pour les intégrations, lecture native des fichiers audio/vidéo ;
- QCM intégrés au Markdown, avec choix simple ou multiple, réponses mélangées, sauvegarde et reprise des brouillons, score agrégé et remise unique lorsque l’étape est une évaluation ;
- aperçu élève non destructif d’un parcours et de ses pages pour l’équipe enseignante, incluant les contenus restreints ou masqués avec leur code couleur ;
- catégories par tags ;
- connexion enseignante protégée et codes personnels élèves ;
- inscription publique des élèves et enseignants, avec suffixe numérique en cas d’identifiant déjà utilisé ;
- profil enseignant modifiable : prénom, NOM, identifiant et mot de passe ;
- annuaire avec prénom, nom, courriel, groupe classe et téléphone facultatif ;
- inscription autonome à un cours par code ou lien d’invitation, avec validation du compte pour les nouveaux élèves ;
- inscription unitaire ou groupée des élèves à un ou plusieurs cours ;
- ordre, consigne, échéance et statut d’évaluation propres à chaque cours ;
- retrait de pages, archivage et duplication de parcours avec échéances conservées ou remises à zéro ;
- superadministration globale des utilisateurs, pages et parcours ;
- exports PDF du tableau d’un parcours, ainsi que PDF, Markdown, DOCX et LaTeX du détail de chaque étape ;
- objectifs et compétences définis au niveau du cours, puis liés aux étapes ;
- auto-positionnement élève 0–3 et confirmation enseignante 0–3 ;
- vues de progression par étape, compétence et objectif ;
- historique enseignant des pages consultées, du temps actif et de la dernière visite, avec rétention d’un mois ;
- rewards configurables par cours, attribués à la confirmation et score cumulatif ;
- boîte de notifications alimentée lors d’une mise à jour ou validation ;
- courriels/annonces ciblés avec modèles personnels et récapitulatif enseignant ;
- suivi administratif collectif et exports complets PDF/Markdown par élève ;
- discussions privées par parcours, gestion et exports, notifications push et reconnexion PWA de 90 jours ;
- interface responsive, mobile-first et PWA installable (Bootstrap local, manifest, cache des assets, navigation basse et safe areas).

Une page reste neutre et réutilisable. Les objectifs et compétences sont liés à son **étape dans un cours**, car la même ressource peut servir des intentions différentes selon le cours.

## Emails

Les actions préparent des messages dans `notification_outbox`. En développement, on les consulte dans **Notifications**. L’aperçu CLI ne transmet rien :

```bash
php scripts/mail_outbox.php
```

Sur un hébergement où `mail()` est configuré :

```bash
php scripts/mail_outbox.php --send
```

Pour activer le délai de 90 secondes des annonces et l’envoi de cinq messages toutes les cinq secondes, lancer le worker chaque minute :

```cron
* * * * * /usr/bin/php /chemin/absolu/vers/instance/scripts/mail_outbox.php --send --worker >/dev/null 2>&1
```

Le battement de vie du worker active automatiquement le mode différé. Sans cron actif, les annonces repassent en envoi immédiat et toutes les autres notifications arrivées à échéance sont traitées en fin de requête Web. Un échec attend cinq minutes avant une nouvelle tentative.

## Modèle mental

```text
Page réutilisable ── blocs + tags
        │
        └── Étape d’un cours ── ordre + échéance + évaluation
                    │           objectifs + compétences du cours
                    │
                    └── Progression de l’élève
                         ├── auto-positionnement 0–3
                         ├── confirmation 0–3
                         └── rewards et points cumulés
```

Les élèves ne reçoivent jamais les vues `students`, `pathway`, `library` ou `teacher` : les routes et commandes vérifient la session, le rôle et un jeton CSRF. Les codes élèves restent volontairement sans mot de passe et ne doivent donc pas protéger des données sensibles.

## Vérifier

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
php tests/incremental_updates.php
php tests/messaging_updates.php
php tests/messaging.php
php tests/pwa_sessions.php
php tests/embeds.php
php tests/content_blocks.php
php tests/announcement_messages.php
php tests/student_admin_history.php
php tests/work_submissions.php
python3 tests/database_profiles.py
node tests/qcm_browser.mjs
node tests/embeds_browser.mjs
node tests/content_blocks_browser.mjs
node tests/announcement_messages_browser.mjs
node tests/student_admin_history_browser.mjs
node tests/work_submissions_browser.mjs
node tests/messaging_browser.mjs
node tests/messaging_push_browser.mjs
node tests/messaging_layout_browser.mjs
```

Le scénario QCM utilise Chromium (`/usr/bin/chromium`, ou `CHROMIUM_BINARY`), Node.js 22 et PHP sur une instance et un profil navigateur temporaires. Il vérifie la reprise après fermeture, la sauvegarde hors ligne et la remise définitive.

Le scénario iframe / vidéo utilise le même environnement temporaire. Il vérifie les liens YouTube, le code iframe RTS, les vues enseignant et élève sur bureau et mobile, les exports et une iframe locale interactive. Les contrôles de chargement externe sont informatifs, car ils dépendent du fournisseur et du réseau.

Le scénario de remise vérifie la création du bloc, les trois formats, la limite de 512 caractères, les brouillons et la reprise hors ligne, la remise explicite, la notation et la réouverture avec historique. Le test PHP couvre aussi la migration v18, la conservation des QCM existants et les contrôles d’accès et de concurrence.

Le scénario des blocs vérifie dans Chromium les champs contextuels, l’aperçu Markdown, la récupération de la saisie lors d’un changement de type, les imports image/document, les erreurs sans perte de saisie et les affichages mobiles. La migration v19 ajoute les options aux blocs existants sans reconstruire leur table ; les copies JSON conservent ces options.

Depuis le suivi enseignant, le menu Actions à trois points verticaux, en haut à droite, regroupe Courriel / Annonce, la gestion des modèles personnels, Correspondance, Réunion, Paiement et Notifications. Les entrées sont accompagnées d’icônes et triées selon la langue affichée. Les modèles sont utilisables dans tous les parcours de leur auteur et acceptent `{prenom}`, `{nom}` et `{cours}` dans le titre et le corps Markdown. Le texte peut être adapté pour un envoi sans modifier le modèle ; aucun prénom ni signature n’est ajouté automatiquement.

La fenêtre d’envoi permet de sélectionner les élèves du parcours et d’inclure leur deuxième adresse en CC. Chaque élève reçoit un seul courriel, avec l’adresse principale en destinataire, la deuxième adresse cochée en CC. L’enseignant reçoit un seul courriel récapitulatif par envoi, avec le titre et le contenu originaux (variables entre accolades conservées), puis les noms et adresses des destinataires, y compris les CC sélectionnées. Ce récapitulatif suit le même délai et la même annulation que les courriels élèves ; il ne confirme pas leur livraison. Un aperçu personnalisé précède l’envoi. Les versions envoyées sont conservées dans une seule annonce, visible uniquement des destinataires sélectionnés et de l’équipe enseignante. L’envoi à toute la classe concerne les inscriptions actives au moment de l’envoi. Les annonces globales existantes gardent leur visibilité habituelle.

Les envois partiels apparaissent en gris dans la liste des annonces du parcours, avec leurs destinataires et états de lecture dans l’application. La corbeille supprime l’annonce et les courriels encore en attente ; elle ne rappelle pas les courriels déjà envoyés. La migration v20 conserve les anciennes annonces et étend la file des courriels aux copies CC/CCI. Les scénarios PHP et Chromium vérifient ces comportements sur des données temporaires ; le test navigateur neutralise la livraison réelle des courriels.


Sous `?view=students`, **Gérer** ouvre une fenêtre avec les onglets Informations, Inscriptions et Historique. Les actions existantes sur les comptes, les participations et la deuxième adresse restent disponibles ; après enregistrement, l’élève, l’onglet et les filtres sont conservés. Le suivi pédagogique reste sous `?view=student-detail`.

**Ajouter un suivi**, depuis l’historique ou le menu Actions du tableau de bord, enregistre une réunion, une correspondance ou un paiement pour un ou plusieurs élèves. Le compte rendu est commun aux participants et stocké une seule fois. Il comprend la date et l’heure, l’auteur, un titre (160 caractères), un texte Markdown (5 000 caractères) et éventuellement un parcours. Les modèles personnels d’envoi sont réutilisables ; dans un compte rendu collectif, `{prenom}` et `{nom}` donnent la liste des participants. `{cours}` nécessite de choisir un parcours auquel les participants sont inscrits. Enregistrer ce suivi ne déclenche aucun courriel. Aucune pièce jointe n’est proposée.

L’historique charge 50 éléments par page et rassemble ces comptes rendus et les annonces ciblées déjà adressées à l’élève, avec leur contenu personnalisé, leurs copies CC/CCI et leurs états d’envoi et de lecture. Les annonces restent les données de référence : leur suppression habituelle les retire aussi de cet historique. L’auteur peut modifier ou supprimer son compte rendu pour tous ses participants, avec contrôle de révision pour éviter d’écraser une modification concurrente. Les suivis généraux sont consultables par les enseignants autorisés à gérer l’élève ; ceux liés à un parcours sont réservés à son équipe enseignante, à l’auteur ou au superadministrateur. Les élèves n’accèdent pas au suivi administratif.

La migration v21 ajoute les deux tables de suivi sans modifier les progressions et efface les corps des copies techniques de courriels déjà envoyées. Des déclencheurs SQLite appliquent ensuite cette règle à chaque envoi réussi, quel que soit le transport. La file conserve les métadonnées (date, destinataire, objet, copies, état), ainsi que les corps des messages en attente ou en échec pour permettre une nouvelle tentative. Le contenu utile reste dans l’annonce d’origine ou le compte rendu. L’ancienne action de nettoyage ne supprime plus les métadonnées. Les tests PHP vérifient les accès, la migration, les liens collectifs, les révisions et la pagination ; Chromium vérifie les fenêtres sur ordinateur et mobile, les formulaires existants et les refus d’accès élèves sur une instance temporaire sans livraison réelle de courriels.

Sous `?view=teacher`, le menu Actions propose aussi **Réunion**, **Correspondance** et **Paiement**. Il ouvre le même suivi administratif avec la catégorie et le parcours sélectionnés ; les participants proposés appartiennent au parcours choisi. Les modèles créés ou modifiés dans cette page sont immédiatement disponibles dans le suivi. La migration v22 regroupe les anciennes discussions sous Réunion, en conservant leurs identifiants, textes, dates et participants. Paiement est une catégorie de compte rendu, sans transaction financière.


La fiche administrative présente chaque activité avec son type, son auteur et sa date de création, puis les autres participants actifs (sans l’élève consulté) et « Concerne : … ». Le chevron déplie le contenu et les détails ; les textes de l’historique utilisent une taille de police uniforme. Le menu à trois points de la fiche regroupe **Exporter en PDF**, **Exporter en Markdown** et **Fermer**. Chaque export contient les informations, les inscriptions affichables et toutes les pages de l’historique autorisé, avec le texte complet des activités et messages, indépendamment de leur état replié. Ces exports sont réservés aux enseignants autorisés ; ils ne contiennent pas les données techniques d’authentification. Les images Markdown sont représentées par leur description et leur adresse dans le PDF, sans chargement de ressource externe.

- [Discussions privées, gestion par parcours et superadmin, notifications PWA](docs/discussions.md)
