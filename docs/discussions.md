# Discussions privées et notifications PWA

Dans **Parcours**, ouvrir les informations du parcours et cocher **Autoriser les élèves à contacter les enseignants**. Cette option est désactivée par défaut. Un élève inscrit peut ensuite ouvrir une discussion avec un enseignant de ce parcours depuis l’icône de discussion en haut de page ou **Contacter un enseignant**. L’enseignant peut aussi initier l’échange.

Chaque fil relie un élève, un enseignant et un parcours. Les messages sont du texte simple, avec liens HTTP/HTTPS cliquables, limités à **256 caractères Unicode**. Seul l’auteur peut modifier son message pendant **180 secondes** après l’envoi. Les corrections portent une mention « Modifié ». Les versions précédentes ne sont pas conservées. Désactiver l’option ou archiver le parcours ferme les nouveaux envois et conserve la consultation des échanges encore autorisés.

Les messages visibles sont actualisés toutes les cinq secondes. Le compteur se met à jour au plus toutes les trente secondes, ainsi qu’après lecture. Une consultation par un responsable au titre de la gestion ne marque pas les messages comme lus pour leurs destinataires.

## Consulter, exporter et effacer

Dans **Discussions**, un enseignant responsable voit **Gérer les discussions de mes parcours**. Il sélectionne une personne et accède aux fils de ses propres parcours, y compris les échanges avec les coenseignants. Un coenseignant peut échanger dans ses fils ; être coenseignant ne donne pas le droit de gérer tous les fils du parcours.

Le **superadmin** dispose de **Gérer toutes les discussions** : consultation, export et effacement des fils d’une personne sur tous les parcours, y compris un parcours supprimé. Le rôle d’administrateur ordinaire ne donne pas cet accès global. L’accès de gestion ne permet pas d’envoyer ou de modifier un message à la place d’un participant.

Les exports **PDF**, **Markdown** et **JSON** contiennent tous les messages conservés, les participants, le parcours, les identifiants du fil et des messages, les dates d’envoi et de modification, ainsi que la date et l’auteur de l’export. Le menu à trois points d’un fil permet de l’exporter seul ; les boutons de la personne regroupent tous les fils autorisés. L’empreinte SHA-256 des données des fils sert à comparer un instantané ; elle ne constitue pas une signature ou un horodatage certifié.

Tout utilisateur peut demander l’effacement de ses discussions depuis cet écran. La demande apparaît pour les responsables concernés et le superadmin. Un responsable peut traiter sa partie ; la demande reste active si d’autres fils subsistent. L’effacement supprime les fils complets pour tous leurs participants, après confirmation du nombre de fils et messages. Si des messages arrivent ou sont modifiés entre le récapitulatif et l’opération, la page doit être rechargée avant de confirmer à nouveau. Les fils ne sont jamais ajoutés à l’historique administratif de l’élève.

## Notifications et hébergement

**Activer les notifications** demande l’autorisation du navigateur pour cette installation. Le site doit utiliser HTTPS (localhost est accepté pour le développement). Sur iPhone/iPad, utiliser une PWA ajoutée à l’écran d’accueil et une version d’iOS compatible avec Web Push. Le badge additionne annonces non lues et messages non lus ; le système d’exploitation peut afficher un point plutôt qu’un nombre. Les notifications restent soumises aux autorisations, au réseau et aux réglages du téléphone.

Le bouton devient **Désactiver les notifications** lorsque l’abonnement du navigateur est encore enregistré pour le compte connecté. Cet état est vérifié au chargement et au retour sur la page. La désactivation retire l’abonnement de cet appareil côté serveur et tente de le supprimer dans le navigateur, sans toucher aux autres appareils. Le bouton permet ensuite de réactiver les notifications. L’autorisation générale du navigateur reste inchangée.

Les clés VAPID sont créées automatiquement au premier abonnement dans `storage/messaging-push.json`. Ne pas les publier ni les régénérer à chaque mise à jour. Aucun service de messagerie tiers à souscrire : le transport utilise les services push du navigateur via Minishlink WebPush. Les notifications affichent seulement « Nouveau message » ou « Nouvelle annonce », sans identité ni contenu du message. L’abonnement est lié à l’installation et à la personne ; la déconnexion le retire côté serveur et neutralise les anciennes notifications côté PWA. Un abonnement expire côté serveur après 90 jours et peut être réactivé avec le bouton.

Pour une livraison régulière même quand le site n’a pas de visiteurs, ajouter au cron, chaque minute :

```cron
* * * * * /usr/bin/php /chemin/instance/scripts/push_notifications.php
```

Le script traite un lot borné (100 livraisons au plus, environ 10 secondes, requêtes individuelles limitées à 5 secondes). Sans cron actif, les visites déclenchent de petits lots de secours. En PHP-FPM, ce travail s’effectue après la réponse ; avec d’autres modes PHP, la réponse peut attendre la fin du lot. Le serveur doit autoriser les connexions HTTPS sortantes vers les services push Google, Mozilla, Apple et Microsoft (notamment les sous-domaines de `notify.windows.com`). L’adresse Google `jmt17.google.com`, utilisée par certaines versions de Chromium, est également acceptée ; l’adresse fournie par le navigateur est conservée telle quelle. Les abonnements expirés sont supprimés, les erreurs temporaires sont réessayées au plus cinq fois. Les informations techniques de notification sont purgées après sept jours. La messagerie fonctionne sans notifications push.

## Données, mises à jour et limites

- `storage/apr.sqlite` : identités durables des utilisateurs/parcours et activation par parcours (schéma 24).
- `storage/messaging.sqlite` : fils, participants, messages, marqueurs de lecture, demandes d’effacement et abonnements (schéma propre, initialement 1). SQLite utilise WAL, les clés étrangères et un délai d’attente d’écriture. Les messages sont paginés par lots de 50 ; une limite de 20 envois par personne et par minute réduit les envois accidentels en boucle.
- `app/Messaging/` : module et ses dépendances PHP embarquées. `database/messaging/migrations/` : migrations numérotées et vérifiées par empreinte. L’adaptateur `Lms.php` est la partie liée au LMS ; une application autonome nécessitera encore son authentification et son interface.

Le volume conservé croît avec les messages : aucune purge automatique des discussions n’est prévue. La séparation évite de grossir la base pédagogique ; elle n’élimine pas les limites de disque, les écritures concurrentes SQLite ni la mémoire nécessaire aux grands exports. Aucun fichier joint n’est accepté. Les données sont protégées par les droits applicatifs et le stockage privé, sans chiffrement des champs ajouté ici.

`apr.py` embarque le module, ses bibliothèques, migrations et le worker, même sans `--include-vendor` (cette dernière option concerne les dépendances générales, notamment PDF). Les données et clés de `storage/` ne font jamais partie d’une publication. Installer les bibliothèques du module avec `composer install --no-dev --working-dir=app/Messaging` avant de préparer une publication ; le script refuse un module sans chargeur Composer.

Le manifeste porte `messaging_database_version`. **Maintenance de l’application** vérifie les deux chaînes de migrations. Les anciennes versions de Maintenance peuvent installer cette première version grâce à la migration du socle 24. Les mises à jour suivantes peuvent migrer la messagerie indépendamment du schéma du socle. Une migration de messagerie dispose d’une sauvegarde technique préalable et d’une transaction ; un échec ultérieur du socle restaure cette sauvegarde. Ne jamais modifier une migration déjà publiée : ajouter la suivante et augmenter la version déclarée dans `app/Messaging/Database.php` et `scripts/apr.py`.

Les sauvegardes/restaurations métier habituelles du LMS continuent à porter sur le socle. Des identifiants aléatoires durables évitent qu’un nouvel utilisateur reprenant un identifiant numérique hérite de vieux fils. Une restauration antérieure à leur introduction ne permet pas de rattacher automatiquement ces conversations. Les exports téléchargés et les copies externes ne peuvent pas être effacés par l’application. Les sauvegardes techniques de Maintenance peuvent contenir des discussions antérieures à un effacement ; leur retrait se fait avec le nettoyage des sauvegardes de Maintenance.

## Vérifications reproductibles

```sh
php tests/messaging.php
php tests/messaging_updates.php
node tests/messaging_browser.mjs
node tests/messaging_push_browser.mjs
php tests/pwa_sessions.php
php tests/smoke.php
python3 tests/database_profiles.py
```

Ces tests utilisent des bases temporaires et des données fictives. Le test navigateur utilise Chromium installé, sans dépendance npm. La livraison push externe est simulée : elle ne remplace pas un essai réel sur les téléphones et l’hébergement de destination.
