# Architecture technique

## Principes

Le projet n’utilise ni framework PHP ni étape de compilation. Composer installe les dépendances générales, notamment mPDF, dans un dossier `vendor/` non versionné par défaut. Le module de discussions possède son propre `app/Messaging/vendor/`, embarqué dans les publications pour les notifications push. Bootstrap 5.3.8 et Bootstrap Icons 1.13.1 sont les seules bibliothèques de présentation ; elles sont versionnées dans le projet et ne nécessitent ni CDN ni connexion Internet. Une requête traverse :

```text
public/index.php
  ├── app/bootstrap.php       session, SQLite et fonctions communes
  ├── app/RegistrationPolicy.php  limites et purge des inscriptions publiques
  ├── app/PathwayService.php      copie et suppressions sûres des parcours
  ├── app/TransferService.php     documents JSON et imports transactionnels
  ├── app/AdminService.php        suppressions globales superadmin
  ├── app/PdfExport.php           HTML imprimable et rendu PDF via mPDF
  ├── app/DocumentExport.php      exports Markdown, DOCX et LaTeX des étapes
  ├── app/actions.php         commandes POST et notifications
  └── app/views.php           requêtes de lecture et rendu HTML
```

Le navigateur charge d’abord les fichiers Bootstrap locaux, puis `assets/app.css` pour l’identité et les composants pédagogiques, `assets/app.js`, le manifest PWA et le service worker. Dans les sources, les imports sont stockés sous `public/uploads/` ; dans une instance préparée, ce dossier devient `uploads/` à la racine Web.

## Arborescence

| Chemin | Rôle |
|---|---|
| `app/Database.php` | ouverture et initialisation automatique de SQLite |
| `app/Markdown.php` | sous-ensemble Markdown sans dépendance : titres H1–H6, séparateurs, sauts de page PDF sécurisés, listes imbriquées, citations, code, liens et tableaux alignés |
| `app/RegistrationPolicy.php` | plafonds anti-abus et purge des comptes non validés |
| `app/LearningActivity.php` | temps actif par page, rapports enseignants et rétention d’un mois |
| `app/PathwayService.php` | copie de parcours, retrait d’étape et suppression sûre de page |
| `app/TransferService.php` | import/export JSON versionné des pages, parcours et élèves |
| `app/AdminService.php` | suppressions globales réservées au superadmin |
| `app/PdfExport.php` | tableaux et fiches détaillées rendus en PDF par mPDF |
| `app/DocumentExport.php` | document Markdown détaillé, paquet DOCX OOXML portable sans dépendance ZIP et source LaTeX compilable |
| `app/ContentBlock.php`, `app/Embed.php` | validation des six types de blocs, imports et lecteurs externes |
| `app/WorkSubmission.php` | brouillons, remises de liens/textes et réouvertures |
| `app/AnnouncementMessages.php` | modèles personnels, annonces ciblées, courriels élèves et récapitulatif enseignant |
| `app/StudentAdminHistory.php`, `app/StudentAdminExport.php` | comptes rendus partagés, accès et exports administratifs complets |
| `app/Messaging/` | discussions et notifications push ; adaptateur LMS dans `Lms.php` |
| `app/UpdateService.php` | vérification des publications, sauvegardes sélectives, migrations et retour arrière |
| `./vendor/` ou `../vendor/` | moteur mPDF et dépendances PHP externes, hors des instances et publications Git par défaut |
| `app/bootstrap.php` | constantes, accès SQL et helpers de présentation |
| `app/actions.php` | validations, édition, parcours, référentiels et rewards |
| `app/views.php` | interfaces élève et enseignant |
| `database/schema.sql` | schéma complet et contraintes |
| `database/seed.sql` | cours et utilisateurs de démonstration |
| `public/` | racine HTTP de l’arborescence de développement ; son contenu est remonté à la racine d’une instance préparée |
| `public/assets/vendor/` | Bootstrap et Bootstrap Icons, toujours embarqués et disponibles hors ligne |
| `scripts/mail_outbox.php` | aperçu ou envoi des emails en attente |
| `scripts/push_notifications.php` | traitement borné de la file push |
| `scripts/apr.py` | menu, reconstruction des profils SQLite et préparation d’une instance locale sans transfert |
| `database/seed_blank.sql` | superadmin unique pour une base vierge |
| `tests/smoke.php` | contrôles métier SQLite |

## Tables SQLite

### Identité et cours

- `users` : prénom, nom, courriel, groupe classe, téléphone, rôle, identifiants et indicateur superadmin ;
- `courses` : cours rattaché à un enseignant, référence stable d’échange et code unique utilisé par les invitations ;
- `enrollments` : appartenance d’un élève à un cours, créée par un enseignant ou par l’élève avec le code du cours ; `pathway_changes_seen_at` est l’unique repère de consultation des changements du parcours.

### Contenus

- `pages` : métadonnées, référence stable d’échange et propriétaire enseignant immuable ;
- `page_blocks` : blocs ordonnés ;
- `tags`, `page_tags` : catégories réutilisables.
- `qcm_drafts` : réponses en cours, privées à l’élève et liées à la version du QCM ; sauvegarde avec contrôle de révision, reprise sans score, suppression à la remise ou au retrait de participation.
- `qcm_attempts` : dernier score agrégé d’un QCM Markdown, sans conservation des réponses choisies ; une étape évaluative verrouille chaque QCM après sa première remise.
- `work_submissions`, `work_submission_versions` : brouillons et remises propres à l’élève et à l’étape, avec conservation des versions lors d’une nouvelle remise autorisée.

### Parcours et référentiel

- `pathway_items` : usage ordonné d’une page dans un cours, avec ses horodatages de création et de dernière modification ;
- `course_objectives`, `course_skills` : référentiel propre au cours ;
- `item_objectives`, `item_skills` : rattachement du référentiel aux étapes.

### Suivi et encouragements

- `progress` : double validation par inscription et étape ;
- `learning_visits` : sessions de consultation d’une étape, temps actif et dernière activité, supprimés après un mois ;
- `reward_types` : catalogue de rewards du cours ;
- `reward_awards` : occurrences et points attribués ;
- `message_templates` : modèles personnels réutilisables entre parcours ;
- `announcement_recipients` : destinataires des annonces ciblées, contenu personnalisé, copies et lecture ;
- `student_followups`, `student_followup_participants` : un compte rendu administratif partagé et ses liens vers les élèves ;
- `notification_outbox` : emails différés ; le corps technique est effacé après envoi réussi, les métadonnées restent conservées.

### Discussions séparées

Le socle utilise `storage/apr.sqlite` (schéma 24). La migration 24 attribue des identités durables aux personnes, parcours et à l’instance, et ajoute l’activation de la messagerie par parcours. Les fils, messages, lectures, demandes d’effacement et abonnements push sont dans `storage/messaging.sqlite` (schéma 1), avec leur propre chaîne sous `database/messaging/migrations/`. Ils ne rejoignent pas les tables de suivi administratif. Les clés VAPID sont privées dans `storage/messaging-push.json`. Voir [discussions](discussions.md) pour les droits de gestion, exports, effacements et limites de conservation.

Les clés étrangères sont activées à chaque connexion. Les contraintes `CHECK`, `UNIQUE` et les suppressions en cascade portent les invariants simples au plus près des données.

Le signalement élève des étapes ajoutées ou modifiées ne crée aucune ligne d’historique. À la connexion, l’ancien repère `enrollments.pathway_changes_seen_at` et l’heure courante forment une fenêtre conservée dans la session PHP, puis le repère durable est avancé en préparation de la connexion suivante. La vue compare `pathway_items.created_at` ainsi que le maximum de `pages.updated_at` et `pathway_items.updated_at` à cette fenêtre, uniquement sur les étapes actuellement visibles. La liste reste ainsi stable pendant toute la session et la base ne conserve qu’un seul horodatage par inscription. `users.student_first_login_at` empêche toute liste d’apparaître lors de la première connexion d’un élève.

## Authentification et autorisation

La session conserve `user_id` et, pour les élèves, le jeton de session classique ou l’empreinte de reconnexion PWA. À la connexion :

- l’enseignant est recherché par `login_code`, puis son hash est vérifié par `password_verify()` ;
- l’élève est recherché par son code personnel majuscule, sans mot de passe ;
- l’identifiant de session est renouvelé après succès.

Tous les formulaires POST portent un jeton CSRF lié à la session. Les commandes vérifient ensuite le rôle et, pour les données d’un cours, que le cours appartient bien à l’enseignant connecté.

Les pages sont également isolées par `owner_id` : un enseignant ne peut ni consulter, ni modifier, ni ajouter à un parcours, ni supprimer la page d’un autre enseignant.

Le superadmin constitue l’exception explicite à cette isolation. Ses commandes vérifient `role='teacher'` et `is_superadmin=1`, puis effectuent les suppressions globales dans une transaction.

## Documents d’échange

Les trois formats portent `version: 1` et un discriminant : `liike.page`, `liike.pathway` ou `liike.students`. Les anciens discriminants `elan.*` restent acceptés à l’import pour assurer la compatibilité. Les pages et parcours utilisent une référence stable indépendante de leur identifiant SQLite.

Un document de parcours ne transporte jamais les pages. Avant une copie ou un écrasement, le service résout toutes les `page_reference` dans la bibliothèque de l’enseignant. La transaction ne commence qu’après cette validation complète. Les documents d’élèves appliquent la même règle aux références de parcours.

L’écrasement d’un parcours remplace sa définition et supprime donc les progressions liées aux anciennes étapes. La copie reçoit une nouvelle référence et ne reprend ni inscriptions, ni progressions. L’import d’une page peut recréer ses pièces jointes locales embarquées en Base64. L’import JSON des élèves accepte au maximum 500 fiches et 25 Mo. Pour les nouveaux comptes, il choisit entre l’état `pending` avec validation par courriel et l’état `active` attribué immédiatement par l’enseignant authentifié. Dans ce second cas, `email_verified_at` reste vide afin de ne pas présenter l’adresse comme vérifiée.

## PDF

`PdfExport.php` construit un document HTML autonome avec styles d’impression, puis le convertit directement avec mPDF, sans processus système. Le chargeur cherche l’autoload Composer sous `./vendor/`, puis sous `../vendor/` ; aucun autre chemin ou nom de répertoire n’est implicite. Le parcours utilise une page A4 paysage et la fiche détaillée une page A4 portrait. Les images locales sont converties en URI `data:` afin que le rendu ne dépende pas du serveur HTTP ni d’une session. L’aperçu HTML imprimable reste disponible en complément du téléchargement PDF natif.

Tous les nouveaux comptes restent `pending` jusqu’au clic sur le lien reçu par courriel, qu’ils proviennent du formulaire public ou de l’annuaire enseignant. Le jeton n’est stocké que sous forme de hash et expire après 15 minutes. La purge s’exécute à chaque requête et par tâche cron chaque minute. L’inscription publique est limitée à 5 tentatives par IP sur 15 minutes, 10 acceptations globales par heure, 30 par jour et 10 comptes publics simultanément en attente ; ce dernier plafond est aussi imposé par un trigger SQLite. Les créations et imports réalisés par un enseignant authentifié sont identifiés par `managed_by` et ne consomment pas ces plafonds anti-robot.

Le code élève est calculé en Unicode après suppression de tous les espaces du nom :

```text
upper(firstname[0:2] + remove_spaces(lastname)[0:3])
```

Le code est unique à l’échelle de la plateforme. Les noms sont convertis en majuscules avant stockage.

## Vues par rôle

Le paramètre `view` sélectionne une vue. Les vues autorisées dépendent du rôle authentifié :

| Élève | Enseignant |
|---|---|
| `student` | `teacher` |
| `learn` | `student-detail` |
|  | `students` |
| `competencies` | `library` |
| `rewards` | `page-edit` |
| `discussions` | `discussions` |
|  | `pathway` |
|  | `teacher-preview` |
|  | `teacher-preview-page` |
|  | `outbox` |
|  | `profile` |

Une vue non autorisée ramène vers l’accueil du rôle. `teacher-preview` et `teacher-preview-page` reprennent la présentation élève sans créer de progression ni de tentative QCM ; elles restent strictement réservées aux enseignants autorisés sur le parcours. Les commandes POST possèdent leurs propres contrôles et ne reposent pas uniquement sur la navigation.

## PWA et mobile

La migration 23 ajoute `pwa_logins`, avec une seule ligne par personne : empreinte SHA-256 d’un secret aléatoire de 32 octets et échéance fixe de 90 jours. Le secret est conservé dans un cookie HttpOnly, SameSite=Lax, Secure en production, dont le nom et le chemin sont propres à l’instance. Il n’est placé ni dans une URL ni dans le stockage JavaScript. Seul le développement sur loopback autorise un cookie sans Secure.

Après une connexion manuelle réussie, l’option explicite de mémorisation crée ou remplace le jeton. Le client détecte le mode installé (standalone/fullscreen ou iOS `navigator.standalone`) et demande une restauration par POST `pwa_resume`, avec CSRF. Cette détection est une condition d’interface, pas une preuve d’identité : l’authentification repose sur le secret valide présenté. La nouvelle session PHP est régénérée et validée par le jeton PWA, sans modifier `student_session_token_hash` ni `student_session_seen_at`. La garde de session peut restaurer le même utilisateur avant une action sans effacer la saisie ; elle refuse une reprise vers un autre compte.

Une collision classique révoque également le jeton PWA pour éviter une reconnexion après invalidation globale. La déconnexion PWA révoque le jeton correspondant ; celle d’un navigateur classique dépourvu de ce cookie conserve le jeton PWA. Un déclencheur SQL révoque le jeton lors d’un changement de mot de passe, de code, de rôle ou de statut. Les sessions restaurées cessent d’être valides à expiration ou révocation du jeton. La table n’accumule aucun historique d’appareils.


Le manifest demande le mode `standalone` et fournit le sigle « ii » en 192 et 512 px, avec une variante adaptée aux masques Android. Une icône Apple Touch de 180 px permet le même raccourci sur iPhone et iPad. Le service worker ne met en cache que les assets publics explicitement listés, y compris Bootstrap et ses fontes. Les pages dynamiques authentifiées et les réponses métier ne sont jamais placées dans son cache. Les brouillons QCM et travaux disposent séparément d’une copie locale de reprise. Le service worker reçoit aussi les notifications push génériques et actualise le badge des annonces et messages non lus, selon les capacités du navigateur. L’interface possède :

- une navigation basse sous 850 px ;
- des tableaux transformés en cartes ;
- des commandes adaptées au mobile et un fil de discussion ajusté à `visualViewport` lors de l’ouverture du clavier ;
- la prise en compte de `safe-area-inset-bottom` ;
- un lecteur de page limité en largeur sur grand écran ;
- une réduction automatique des animations si le système demande moins de mouvement.

Les chemins relatifs du manifest, du service worker et des assets permettent d’installer l’application à la racine d’un domaine ou sous un préfixe tel que `/lms/`, sans dépendre d’un routeur partagé fourni avec le projet.


## Maintenance et sauvegardes du code

`UpdateService.php` télécharge puis vérifie l’ensemble de la publication. `maintenance_file_changes()` compare les empreintes aux fichiers réellement installés, ce qui détecte aussi les modifications locales et fichiers manquants. Le nouvel outil ne copie que les fichiers nouveaux ou différents et ne sauvegarde que les anciennes versions remplacées ou supprimées, avec `file-changes.json`. Les bibliothèques identiques restent en place. Un échec géré restaure les fichiers concernés et retire les nouveaux fichiers ; les migrations conservent leurs protections transactionnelles et sauvegardes SQLite. Le téléchargement reste complet et les sauvegardes partielles ne sont pas des copies autonomes du site. Voir [exploitation](exploitation.md#mettre-à-jour-depuis-la-superadministration) pour le déploiement de cet outil et la restauration.
