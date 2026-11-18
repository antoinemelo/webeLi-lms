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
| `app/PathwayGroups.php`, `app/PathwayGroupViews.php` | regroupements, déplacements, contrôles de concurrence et affichage enseignant/élève |
| `app/PathwayEvent.php` | type exclusif, validation des dates, rendu des événements, .ics et lien Google Calendar |
| `app/ProgressExport.php`, `app/ProgressPdf.php` | sélection et droits, données communes, CSV et PDF de progression |
| `public/assets/progress-export.js` | recherche et sélection des élèves dans la fenêtre unique d’export |
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

Le socle utilise `storage/apr.sqlite` (schéma 25). La migration 24 attribue des identités durables aux personnes, parcours et à l’instance, et ajoute l’activation de la messagerie par parcours. Les fils, messages, lectures, demandes d’effacement et abonnements push sont dans `storage/messaging.sqlite` (schéma 1), avec leur propre chaîne sous `database/messaging/migrations/`. Ils ne rejoignent pas les tables de suivi administratif. Les clés VAPID sont privées dans `storage/messaging-push.json`. Voir [discussions](discussions.md) pour les droits de gestion, exports, effacements et limites de conservation.

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

## Événements et export de progression

La migration **25** ajoute `pathway_items.event_data`, un JSON nullable contenant `all_day`, `start`, `end` et `location`. Les dates sont conservées dans le fuseau `Europe/Zurich`. Le formulaire `item_type` produit les indicateurs existants `is_evaluation` et `self_evaluation_enabled`, tous deux à zéro pour un événement ; une consultation a les mêmes indicateurs et `event_data=NULL`. Les commandes de sauvegarde et l’import normalisent les types exclusifs. La migration donne priorité à l’évaluation pour les étapes mixtes sans toucher aux lignes `progress`. Un déclencheur actualise `updated_at` lors d’une modification des données calendaires.

`?view=event-download&item=…` vérifie le rôle et les droits de l’acteur avant de produire un fichier `text/calendar` non mis en cache. Son UID dépend de l’identité durable du parcours et de l’étape. Les heures sont converties en UTC ; les journées entières utilisent une fin exclusive. Le sérialiseur applique l’échappement, CRLF et le pliage UTF-8 à 75 octets définis par [RFC 5545](https://datatracker.ietf.org/doc/html/rfc5545). Le lien Google utilise le [formulaire d’événement prérempli documenté par Google](https://developers.google.com/workspace/calendar/api/concepts/inviting-attendees-to-events#provide_a_link_for_users_to_add_the_event), sans OAuth ni appel d’écriture serveur.

L’action POST `export_progress` contrôle le parcours, le rôle, les élèves actifs, le mode et le format. `course_progress_students()` fournit les indicateurs communs au tableau de bord et à l’export ; `ProgressExport.php` ajoute les résultats détaillés, tandis que `ProgressPdf.php` prépare le rendu mPDF et les ruptures par élève. CSV et PDF utilisent la même sélection autorisée. Les tests couvrent notamment les notes nulles/zéro, les QCM multiples, les frontières entre parcours, les formats et la pagination.

La sauvegarde de page ne prépare `page.updated` que si le lien d’enregistrement transmet `notify_students=1` et qu’aucun conflit n’est constaté. Les modifications ordinaires restent visibles par le suivi des changements de parcours.

## Regroupements des étapes

La migration 26 crée `pathway_groups(id, course_id, title)` et ajoute `pathway_items.group_id`, facultatif. Sa clé étrangère remet le rattachement à NULL quand un groupe est supprimé ; deux triggers interdisent les rattachements entre parcours. Le déplacement d’une étape conserve des groupes contigus dans l’ordre global `position` ; la migration 27 ajoute `pathway_groups.position` pour conserver la place des titres, même vides. Un déplacement collectif conserve l’ordre des étapes du groupe et ne crée aucun sous-groupe. Les titres sont limités à 120 caractères. Aucun sous-groupe n’est géré.

Les actions de gestion sont réservées aux enseignants autorisés et protégées par CSRF, le verrou `course_structure` et une empreinte des groupes, positions et rattachements. Un ancien onglet ne peut pas écraser une structure plus récente. Les vues élève construisent les groupes après le filtrage d’accès et la numérotation habituels, sans révéler les groupes vides ou inaccessibles. L’état replié reste dans `sessionStorage`, par compte, parcours, groupe et vue. Les groupes sont transportés par clés locales remappées lors des copies/imports ; un import avec référence inconnue ou groupe disjoint est refusé atomiquement.

La migration 30 ajoute `pathway_groups.is_visible`, booléen à `1` par défaut pour préserver les parcours existants. Les opérations `show` et `hide` partagent les contrôles de gestion et ne mettent à jour que cette colonne : `pathway_items.access_mode`, les restrictions nominatives, les révisions et les données pédagogiques restent intacts. L’empreinte de structure inclut la visibilité.

L’accès effectif élève exige à la fois un regroupement visible (ou aucun regroupement) et l’autorisation individuelle sur l’étape. Ce filtre s’applique aux listes, à la navigation, aux lectures directes, aux PDF et événements, aux remises, aux QCM individuels et collectifs, aux notes privées et au suivi d’activité. Les règles de calcul des notes, moyennes, acquis et confirmations enseignantes continuent d’utiliser les réglages pédagogiques des étapes. Les synthèses conservent les résultats historiques ; une évaluation masquée sans résultat ne révèle pas son titre dans les acquis. L’aperçu global élève filtre les groupes masqués, mais un enseignant peut toujours inspecter directement leur contenu avec une icône indiquant le masquage.

Le format d’export ajoute un booléen `is_visible` à chaque groupe. Les copies le conservent ; les anciens documents qui omettent ce champ sont importés avec leurs groupes visibles. Une valeur présente non booléenne est rejetée. La suppression d’un groupe masqué détache ses étapes, qui retrouvent alors leurs propres règles d’accès ; la confirmation le précise.

Le CSS applicatif est chargé avec une version dans son URL, comme le JavaScript : une mise à jour du style ne réutilise pas une ancienne réponse du cache HTTP ou PWA. Les groupes utilisent les composants Bootstrap Collapse, Dropdown et Modal.


### Inclusion individuelle des évaluations — migration 28

`progress.evaluation_included` est un booléen non nul, à `1` par défaut. Il est enregistré avec la note, y compris lorsque celle-ci est vide, pour chaque couple inscription/étape. La migration 28 ajoute cette colonne sans modifier les notes et validations existantes. Une valeur `0` retire la note et sa pondération des moyennes individuelles, du groupe et des acquis ; les résultats sources restent conservés. Les moyennes QCM du groupe appliquent également cette exclusion pour les étapes évaluatives. Les exports détaillés indiquent le statut dans **Inclus dans les moyennes**.

`course_pending_review_counts()` expose également `evaluations_by_student`, sous-ensemble du total des confirmations. Le tableau utilise ce nombre pour distinguer les évaluations en rouge pastel, sans compter plusieurs fois une étape comportant plusieurs QCM.


### Calendrier enseignant et préférence de navigation

`TeacherDashboard.php` calcule des périodes jour/semaine/mois en Europe/Zurich (jour par défaut) et interroge les échéances de tous les parcours actifs détenus ou partagés avec l’enseignant. La sélection d’un parcours ne filtre pas le calendrier. La grille va du lundi au dimanche ; la navigation mensuelle part du premier jour pour éviter les sauts de mois. La période est conservée dans la session par utilisateur.

`date-fields.js` enrichit tous les `input[type=date]` et `input[type=datetime-local]`, y compris les contrôles ajoutés dynamiquement : saisie manuelle au format jour/mois/année et sélection via un calendrier de l’application, traduit par `Intl.DateTimeFormat` avec les mêmes locales que `locale_code()`. Le premier jour de semaine suit la locale (lundi pour les cinq locales proposées) ; le calendrier ne dépend pas de la langue du navigateur. Le contrôle nommé conserve sa valeur ISO pour les formulaires et les API. Les contraintes, les verrous et les réinitialisations sont synchronisés. Après une affectation de valeur par JavaScript sans événement, appeler `liikeDateFields.sync(form)` ; le suivi administratif le fait à l’ouverture de son éditeur.

Le cookie `liike_pathway_view_<id>` mémorise uniquement `pathway` ou `teacher-preview`, pendant un an, au chemin de l’installation, avec HttpOnly et SameSite=Lax (Secure en HTTPS). Les valeurs absentes ou invalides donnent l’aperçu. La préférence concerne les liens génériques **Parcours** ; les liens d’édition explicites gardent leur destination. Aucune migration supplémentaire n’est nécessaire.


## Synthèse et partage des contenus (migrations 31 à 34)

`course_summary_data()` fournit aux exports PDF et DOCX les mêmes sections, numéros, objectifs et compétences. Les sections sont constituées avant le filtre des étapes exclues du référentiel afin de conserver la position des groupes vides. `course_docx_bytes()` produit un tableau OOXML paysage avec en-tête répété, sans dépendance PHP supplémentaire.

La migration 31 ajoute les collaborateurs de page et les reçus de récupération. La migration 32 introduit `page_share_links` : un jeton aléatoire de 256 bits par page, un état activé/désactivé, trois modes autorisés et une révision contre les modifications depuis un ancien onglet. La migration 33 réserve au lien unique un nouveau jeton : les anciens liens actifs restent limités à leur mode d’origine, sous réserve des permissions actuelles. Un ancien lien externe donné aux élèves ne peut donc jamais donner accès aux sources ou aux modifications. Les anciens reçus sont conservés. Chaque séance garde le jeton d’origine pour son lien de retour, sans révéler un lien offrant davantage de droits. `page_shares` reste le support des identifiants des reçus par mode. Aucun partage n’est créé pour une page qui n’en avait pas.

`LibrarySharing.php` vérifie la propriété, les modes et le statut Prêt à chaque utilisation. L’import et l’octroi des droits se font dans une transaction avec vérification de la destination et de son verrou de structure. Les récupérations partagées et les copies sont idempotentes indépendamment l’une de l’autre. Les permissions déjà acquises sont conservées après désactivation, mais le lecteur externe et les nouvelles récupérations sont bloqués immédiatement.

`LibrarySharingViews.php` affiche le lien unique dans une fenêtre accessible depuis le parcours et l’éditeur. La route publique `shared-content` propose le choix du mode. Les modes partagé et copie, y compris le téléchargement JSON contenant les réponses nécessaires à l’édition, nécessitent un compte enseignant actif. Les visiteurs anonymes et les élèves ne voient que le mode externe autorisé. L’entraînement individuel externe corrige côté serveur sans enregistrer de tentative ni de note. Les réponses publiques sont `private, no-store`, sans référent ni indexation.

`QcmGuest.php` et `QcmGuestViews.php` ajoutent les routes `guest-class` et `guest-class-state`. Un enseignant connecté crée une séance depuis un QCM collectif partagé en mode externe. Le jeton de salle (128 bits) autorise uniquement la participation. Le pilotage exige le compte créateur ; le QR ne contient ni autorisation d’édition ni jeton de contenu. Les tables `qcm_guest_sessions`, `qcm_guest_participants` et `qcm_guest_votes` ne relient les participants à aucun compte, nom, courriel ou IP. Un identifiant aléatoire par salle est conservé dans la session PHP ; il n’est jamais envoyé dans l’état public. Les questions sont figées à la création, avec invalidation si la source change. Seule la question courante est envoyée, les comptes après clôture, les corrections après révélation.

Votes et commandes utilisent `BEGIN IMMEDIATE`, des contrôles de question/révision et le CSRF. Le vote unique par participant/question reste modifiable jusqu’à la fermeture. Les séances expirent au bout de 24 heures et sont purgées, avec leurs participations et votes, au prochain bootstrap. Aucune donnée n’est écrite dans `qcm_attempts` ou `progress`. Le retrait du mode externe termine les séances ouvertes. La capacité est bornée à 500 participations par salle et 20 séances non terminées par enseignant. La session du navigateur ne garantit pas l’unicité d’une personne physique.

`app_public_base_url()` fournit les liens de partage, de salle, d’invitation et de courriel. `APP_PUBLIC_URL` a priorité sur l’ancien `APP_MAIL_BASE_URL` ; à défaut, l’adresse est construite avec le nom, protocole et port du serveur et le dossier du script. Aucun domaine `lms.` n’est ajouté. Les en-têtes Host et X-Forwarded-Host ne sont pas lus. Les proxys et traitements CLI utilisent une URL canonique configurée.

Vérifications : `php tests/library_sharing.php /tmp/liike-exports`, `php tests/guest_qcm.php`, `node tests/library_sharing_browser.mjs`, `php tests/mail_security.php`, `python3 tests/mail_security_http.py`, `php tests/smoke.php`, `php tests/qcm_modes.php`. Les scénarios navigateur utilisent une instance temporaire, deux navigateurs élèves anonymes et un enseignant collègue.

`library_page_sharing()` calcule les repères visuels pour les seuls identifiants de pages déjà autorisés par la bibliothèque ou le parcours. Les accès communs d’autres enseignants actifs sont distingués des permissions de nouveaux partages et des copies indépendantes. Les filtres multiples s’appliquent côté navigateur aux seules pages accessibles ; le filtrage personnel initial est aussi appliqué dans le HTML. L’origine correspond au propriétaire actuel. Les choix sont unis au sein d’un filtre et croisés entre filtres.

La migration 34 crée `page_copy_origins` et reprend les reçus de copies existants. Le lien de copie enregistre la provenance dans la même transaction que l’import. Le nom source est conservé si la page ou le compte source disparaît ; les clés étrangères passent à NULL. Cette table n’accorde aucun accès à la source et ne synchronise aucun contenu. `php tests/library_origins.php` couvre les permissions, l’usage réel, les couleurs, la migration et la conservation de provenance après suppression. Le scénario navigateur de partage couvre également les filtres combinés et les accès anonymes directs aux modes administratifs.
