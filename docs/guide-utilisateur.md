# Guide utilisateur

## Saisir une date

Tous les champs de date proposent une saisie manuelle au format **jj/mm/aaaa** (par exemple **22/09/2026**) et une icône calendrier à droite pour choisir une date. Les noms des mois et des jours suivent la langue choisie dans liike ; la semaine commence le lundi selon les locales de l’application (français suisse, anglais britannique, allemand suisse, italien suisse et espagnol). Les champs date et heure acceptent **22/09/2026 14:30**. Cela concerne les échéances, le début et la fin des événements, ainsi que les suivis administratifs dans les fenêtres modales. Les dates impossibles sont refusées ; un champ facultatif peut être vidé.

## Se connecter

### Créer un compte

Le lien **S’inscrire** ouvre une page distincte pour les élèves et les enseignants. Un élève renseigne ses coordonnées et son groupe classe ; il peut saisir immédiatement le code transmis par son enseignant ou rejoindre un cours plus tard. Un enseignant choisit son identifiant, son mot de passe et le nom de son premier cours.

Les identifiants sont vérifiés sans distinction de casse. Si le code proposé existe déjà, liike ajoute automatiquement un chiffre supérieur à zéro : `LIROS`, `LIROS1`, `LIROS2`, etc.

Un courriel contient ensuite un lien d’activation valable **15 minutes**. Avant le clic, le compte ne peut pas se connecter. Une inscription non validée est supprimée automatiquement à expiration. Le formulaire public applique des plafonds par adresse réseau, par heure, par jour et sur le nombre de comptes publics simultanément en attente ; il impose aussi un bref délai humain et utilise un champ-piège invisible aux visiteurs. Une classe peut effectuer 30 demandes depuis le même réseau en 15 minutes. Les plafonds sont de 50 demandes acceptées par heure pour la plateforme et de 50 comptes publics simultanément en attente de validation. Les administrateurs actifs reçoivent un courriel lorsqu’un plafond est atteint, sans répétition à chaque tentative bloquée. Les créations et imports effectués depuis l’espace d’un enseignant authentifié ne sont pas soumis à ces plafonds anti-robot.

### Enseignant

L’espace enseignant protège la gestion des contenus, parcours et inscriptions :

```text
Identifiant : nora
Mot de passe : Elan-Nora-2026!
```

### Élève

L’élève saisit son code personnel sans mot de passe. La démonstration fournit `LIROS`, `SADIA` et `NOMAR`.

Le code contient les deux premières lettres du prénom et les trois premiers caractères du nom après suppression des espaces. Il est affiché en majuscules :

- Lina ROSSI → `LIROS` ;
- Maya DA COSTA → `MADAC`.

Si deux élèves produisent le même code, le second reçoit automatiquement un suffixe numérique.

### Rester connecté dans la PWA

Dans l’application installée sur l’écran d’accueil, cocher **Rester connecté dans cette application (90 jours)** lors de la connexion. Les enseignants confirment ensuite leur mot de passe. Cette option n’est pas proposée dans une fenêtre de navigateur classique.

Pendant 90 jours, la PWA peut rétablir la connexion sans ressaisir les identifiants. Cette reconnexion automatique conserve la session laissée sur l’ordinateur, tant qu’elle est encore valide. Une connexion manuelle d’élève reste soumise à la règle habituelle sur les connexions simultanées.

**Se déconnecter** supprime le jeton présent dans cette PWA. Après une reconnexion automatique, cette déconnexion ne ferme pas la session de l’ordinateur. Il existe un seul jeton par personne : mémoriser une nouvelle PWA remplace le précédent. Une modification du mot de passe ou du code de connexion, un changement de statut du compte ou une collision entre connexions classiques révoque le jeton. La reconnexion nécessite un accès réseau.

### Rejoindre un cours avec une invitation

Chaque parcours actif possède un code unique et un lien d’invitation. L’enseignant les trouve dans **Élèves**, depuis le menu à trois points de **Élèves & inscriptions**, puis **Invitation au parcours**, et peut les copier en un clic.

- un élève déjà connecté confirme le cours puis le rejoint immédiatement ;
- un élève qui ouvre le lien sans compte peut créer son compte, saisir ses informations et valider son courriel ; son inscription au cours est alors déjà préparée ;
- le code peut aussi être saisi depuis le menu **Connecté·e comme**, avec **Rejoindre un cours** sous **Modifier mon profil**.

Les codes sont comparés sans distinction de casse. Un cours archivé ne peut pas être rejoint. Si l’enseignant a archivé une participation, l’élève doit lui demander de la réactiver : le code d’invitation ne contourne pas cette décision.

## Parcours élève

### Onglet « Parcours »

L’élève voit immédiatement :

- le cours sélectionné ;
- sa progression globale ;
- le nombre d’étapes réalisées et confirmées ;
- la prochaine étape utile ;
- son échéance et son éventuel statut d’évaluation ;
- son score de rewards.

La liste « Vue d’ensemble » distingue trois états : à faire, envoyé à l’enseignant et confirmé.

Lorsqu’une étape visible a été ajoutée, déplacée ou modifiée, lorsqu’un type d’acquis a changé à la suite d’une confirmation ou d’une notation enseignante, ou lorsqu’un encouragement a été attribué depuis la connexion précédente, un encadré **Depuis la dernière visite** apparaît avec des liens directs vers les éléments concernés. Les ajouts, les mises à jour, les types d’acquis concernés (compétences, objectifs ou évaluations) et les encouragements y sont distingués, sans révéler le détail des acquis modifiés. L’encadré est propre à chaque élève et à chaque parcours, et reste visible pendant toute la session. À la connexion suivante, le repère est réinitialisé : seul l’intervalle entre les deux dernières connexions est conservé, sans journal durable des changements. Aucune liste n’est affichée lors de la toute première connexion.

Les parcours sont classés du plus récemment consulté au plus ancien. Les quatre premiers apparaissent côte à côte ; s’il y en a davantage, les suivants sont accessibles avec le bouton à trois points verticaux placé à droite.

### Réaliser une étape

Une page peut contenir du texte Markdown, une image, un document, une vidéo ou un lecteur audio, une intégration externe et un travail à rendre sous forme de lien ou de texte court. Les objectifs et compétences mobilisés sont affichés en tête de page.

À la fin, l’élève choisit son niveau :

| Niveau | Signification |
|---:|---|
| 0 | Je découvre et j’ai besoin d’aide |
| 1 | Je commence et je réussis avec un modèle |
| 2 | Je maîtrise et je réussis seul·e |
| 3 | Je peux expliquer et aider une autre personne |

Il peut ajouter une note puis envoyer son auto-positionnement. Un nouvel envoi remplace son auto-positionnement précédent et remet la confirmation enseignante en attente.

### Consulter ses acquis

L’écran **Acquis** propose deux synthèses :

- les compétences du cours ;
- les objectifs du cours.

Chaque carte compare le niveau moyen auto-évalué au niveau moyen confirmé. Le nombre de situations confirmées indique sur combien d’étapes la synthèse repose réellement.

### Consulter ses rewards

Les rewards sont des encouragements séparés de l’évaluation des compétences. L’élève voit :

- son total de points ;
- la répartition par type de reward ;
- les derniers encouragements et leurs messages.

## Parcours enseignant

### Modifier son profil

Le menu du profil, en haut à droite, donne accès à **Modifier mon profil**. L’enseignant peut y modifier son prénom, son NOM, son identifiant de connexion et, s’il le souhaite, son mot de passe. Le nom est toujours enregistré en majuscules et le nouvel identifiant s’applique à la connexion suivante.

### Inscrire les élèves

L’écran **Élèves** contient l’annuaire, le partage du code/lien d’invitation et les formulaires d’inscription manuelle.

Pour créer un accès, l’enseignant renseigne :

- prénom ;
- nom, automatiquement converti en majuscules ;
- courriel ;
- groupe classe ;
- numéro de téléphone facultatif ;
- un ou plusieurs cours.

Le code personnel est prévisualisé pendant la saisie. Après création, il apparaît dans l’annuaire et un email de bienvenue est préparé dans la boîte de notifications.

Le menu à trois points de **Élèves & inscriptions** ouvre **Nouvel accès**, **Inscription groupée**, **Invitation au parcours** et l’import/export. La recherche accepte le nom, le prénom et le courriel ; les filtres limitent l’annuaire par groupe ou parcours. La recherche et les filtres, seuls ou combinés, sont retrouvés au retour dans cette page. Ils sont mémorisés dans le navigateur pour le compte connecté, y compris après une déconnexion ; les comptes conservent des choix distincts. Une inscription déjà existante n’est pas dupliquée.

Une adresse électronique secondaire privée peut être ajoutée à la fiche d’un élève. Seuls les enseignants autorisés et le superadmin peuvent la voir ou la modifier. Lorsqu’elle est renseignée, sa mise à jour est intégrée à la partie **Admin** de la fiche.

### Archiver ou supprimer

Dans l’annuaire, **Admin** ouvre les commandes d’une participation et du compte :

- **Archiver** une participation la masque à l’élève mais conserve progression, validations et rewards ;
- **Retirer définitivement** supprime la participation et tout son historique dans ce cours (progression, validations, QCM, visites, notes privées et accès ciblés), sans toucher au compte ni à ses autres cours ;
- **Archiver le compte** bloque la connexion et archive ses participations ;
- **Effacer définitivement** supprime le compte et toutes ses données.

Si le compte participe au cours d’un autre enseignant, son archivage ou sa suppression globale est bloqué. Une confirmation explicite précède chaque suppression définitive.

### Suivre un cours

Le logo **liike** ouvre le tableau de bord (`?view=teacher`). La navigation enseignante propose **Élèves**, **Bibliothèque** et **Parcours** ; sur mobile, **Bibliothèque** est nommée **Contenus**.

Le chevron placé avant **Progression des élèves** affiche ou masque la liste, fermée par défaut. Le dernier choix est mémorisé dans le navigateur pour le compte connecté, indépendamment du parcours consulté. Il s’agit uniquement d’un réglage d’affichage.

Dans cette liste, ouvrir un élève. La dernière visite connue apparaît à droite de chaque étape. Le bouton **Historique des visites** ouvre un tableau par jour et par page avec la première visite, la dernière activité, le nombre de sessions et le temps actif estimé.

Le navigateur enregistre l’ouverture immédiatement, puis actualise le temps actif toutes les 60 secondes et à la fermeture ou au masquage de l’onglet. Les données de plus d’un mois sont automatiquement supprimées.

Sous le titre du tableau de bord, le **Calendrier des échéances** rassemble les dates limites de tous les parcours actifs dont vous faites partie de l’équipe enseignante, quel que soit le cours sélectionné. La vue **Jour** est sélectionnée par défaut et affiche uniquement les échéances du jour choisi. Les boutons **Jour**, **Semaine** et **Mois**, les flèches et **Aujourd’hui** permettent de naviguer. Les entrées indiquent leur parcours et ouvrent l’étape. Les titres sont limités à 40 caractères, points de suspension compris, avec le titre complet au survol. Chaque journée s’agrandit selon le nombre de tuiles, sans défilement interne ; les étapes masquées restent accessibles en édition pour l’enseignant. Les parcours archivés ne figurent pas dans ce calendrier. Sur mobile, la période est présentée en liste chronologique des jours ayant des échéances. Les dates d’événements sans échéance ne sont pas ajoutées automatiquement.

Le tableau de bord affiche la progression moyenne, le nombre de validations en attente et la prochaine évaluation. La case **Progression moyenne** présente le pourcentage de progression puis la moyenne des notes sur 10 (par exemple **61% / 7,00**). Chaque élève actif du cours compte à parts égales, à partir de sa moyenne pondérée des évaluations suivies. Les élèves sans note sont exclus du calcul ; un tiret apparaît si aucune note n’est disponible. La liste des élèves montre leur avancement, les confirmations attendues et leur score de rewards. Après **Progression**, la colonne **Moyennes** présente la moyenne pondérée des évaluations sur 10 puis la moyenne simple des niveaux autoévalués sur 3, chacune au dixième (par exemple **7,0 | 2,5**). Les calculs portent sur les étapes suivies du cours ; seules les autoévaluations remises et encore activées sont retenues. Une valeur absente est indiquée par **—**. Le tri **Moyennes** compare d’abord les notes d’évaluation, puis les niveaux autoévalués. Une évaluation QCM rejoint **À confirmer** lorsque tous les QCM de l’étape ont été remis ; l’étape n’est comptée qu’une fois, même si elle contient plusieurs QCM. Le compteur de chaque élève additionne les autoévaluations et les évaluations prêtes à corriger, y compris les travaux remis. Son carré est rouge pastel dès qu’au moins une évaluation est en attente ; il reste orangé pour les autres confirmations. Le compteur de la tuile du parcours suit la même règle : rouge pastel dès qu’un élève a une évaluation à confirmer. Son nombre reste celui des élèves ayant une confirmation en attente. Le survol indique le nombre d’évaluations dans le total.

Une annonce globale du parcours s’adresse à tous ses élèves actifs. Pour choisir les destinataires, utiliser **Courriel / Annonce** dans le menu Actions à trois points du tableau de bord. Ce menu regroupe aussi les modèles, Correspondance, Réunion, Paiement et Notifications ; ses entrées sont triées selon la langue affichée.

La fenêtre d’envoi permet de choisir un modèle personnel, d’adapter le titre et le contenu Markdown et de sélectionner un ou plusieurs élèves. Les modèles sont disponibles dans tous les parcours de leur auteur et acceptent `{prenom}`, `{nom}` et `{cours}` ; aucun prénom ni signature n’est ajouté automatiquement. Pour chaque élève, sa deuxième adresse peut être cochée : elle figure alors en CC dans son unique courriel. L’enseignant reçoit un seul récapitulatif de l’envoi groupé, avec le titre, le contenu original (variables conservées) et la liste des destinataires et CC. Ce récapitulatif ne confirme pas la livraison des messages.

Les annonces ciblées apparaissent en gris dans le parcours. Un chevron **Destinataires** affiche les noms et les états de lecture dans l’application. Supprimer l’annonce retire aussi les courriels encore en attente, sans rappeler ceux déjà envoyés. Les envois et les modèles n’acceptent pas de pièce jointe.

En ouvrant un élève, l’enseignant peut :

- lire son auto-positionnement et sa note ;
- confirmer un niveau de 0 à 3 ;
- noter une évaluation sur 10, ou laisser sa note vide pour annuler la validation tout en conservant le commentaire comme brouillon ;
- rédiger une **Note / Commentaire (visible par l'apprenant)** ;
- attribuer facultativement un reward, des points et un message ;
- consulter la moyenne pondérée des évaluations suivies, les compétences confirmées et les derniers rewards.

Les actions de confirmation et de notation utilisent un crayon à droite de l’étape. Le crayon orange rempli indique un niveau à confirmer ou une évaluation à noter : le premier formulaire en attente reste ouvert, les suivants s’ouvrent au clic.

Pour une **Évaluation**, le crayon reste disponible pour saisir ou modifier la note, même si les QCM ou travaux ne sont pas tous remis, ou si le questionnaire a été modifié depuis les réponses. La note enseignante sur 10 est indépendante du score QCM. Cette saisie manuelle ne crée ni réponse QCM ni remise de travail ; les indicateurs automatiques de correction attendent toujours les remises requises.

Sous **Laissez la note vide pour annuler la validation**, l’interrupteur est vert à droite (**Utilisé dans la moyenne**) par défaut. À gauche, il devient rouge (**Non comptabilisé**). Enregistrer le choix avec **Enregistrer la note**, même sans note saisie. L’exclusion concerne uniquement cet élève et cette évaluation : elle retire la note et sa pondération des moyennes, sans effacer la note ni les résultats QCM. Elle est prise en compte dans les moyennes individuelles, de groupe, de compétences et objectifs, les moyennes QCM du groupe et les exports CSV/PDF. Réactiver l’interrupteur réintègre la note conservée. Une exclusion sans note ne crée aucune validation. La file **À confirmer** reste fondée sur les remises et validations, indépendamment de ce choix de calcul.


### Exporter la progression des élèves

Dans le **Tableau de bord**, accessible par le logo liike (`?view=teacher`), ouvrir le menu **⋮** en haut à droite du titre, puis **Exporter la progression**. La même fenêtre regroupe les formats **CSV** et **PDF**, les modes **Récapitulatif** et **Détaillé**, ainsi que les noms des élèves actifs du parcours sélectionné. Tous sont cochés initialement ; la recherche, les cases individuelles et la sélection globale permettent de préparer un sous-ensemble. Le bouton unique **Exporter** lance le téléchargement.

| Mode | CSV | PDF |
|---|---|---|
| Récapitulatif | Une ligne par élève : identité, progression, moyennes, encouragements et dernière activité connue. | Tableau A4 paysage reprenant les indicateurs du suivi. |
| Détaillé | Une ligne par élève et étape ; plusieurs lignes si l’étape contient plusieurs QCM. | Fiches A4 portrait ; **chaque élève commence sur une nouvelle page**, avec continuation si nécessaire. |

Le détail comprend la note de chaque évaluation sur 10, sa pondération, les autoévaluations sur 3, les confirmations et commentaires. Chaque QCM possède une colonne **Note QCM /10** : questions correctes ÷ nombre de questions × 10, arrondi à deux décimales. Seul le **dernier résultat** de chaque QCM est disponible, y compris pour l’entraînement ; les anciennes tentatives et les réponses individuelles ne sont pas exportées. Le CSV indique aussi le nombre de tentatives et la date de dernière réponse.

Une cellule vide signifie qu’aucun résultat n’est disponible ; zéro reste une note. Les étapes devenues inaccessibles sont signalées afin de conserver les résultats utiles au bilan. Le CSV utilise UTF-8 avec BOM, le point-virgule et les horaires **Europe/Zurich**. Les indicateurs de l’élève et les notes d’étape sont répétés sur les lignes QCM : ne pas les additionner.

Depuis la fiche de suivi (`?view=student-detail`), le bouton immédiatement à droite de l’encadré des points ouvre **Gestion de l’élève**, avec les mêmes informations, inscriptions et historique que depuis **Élèves & inscriptions**.

### Suivi administratif

Dans **Élèves**, le statut actif/inactif apparaît à côté des initiales. **Admin**, à droite du code, ouvre une fenêtre avec Informations, Inscriptions et Historique. **Progression** ouvre le suivi pédagogique de la participation ; si plusieurs parcours actifs sont disponibles, choisissez celui à consulter. Le bouton est désactivé lorsqu’aucune participation ne permet ce suivi. Le suivi reste également accessible depuis le **Tableau de bord**.

Dans **Admin → Informations**, l’enseignant autorisé peut modifier le prénom, le nom, le courriel et le groupe classe. Le code de connexion reste stable. Le téléphone est consultable, mais seul l’élève le modifie depuis son profil. Une modification concurrente est signalée sans écrasement. Si le courriel d’un compte encore en attente est corrigé, un nouveau lien de validation est envoyé à cette adresse.

**Ajouter un suivi** enregistre une Réunion (y compris une discussion), une Correspondance ou un Paiement pour un ou plusieurs élèves. Ces actions sont également accessibles depuis le menu Actions du tableau de bord. Le compte rendu commun comporte une date et une heure, un titre de 160 caractères maximum, un contenu Markdown de 5 000 caractères maximum et, facultativement, un parcours. Il peut reprendre un modèle. Son enregistrement n’envoie pas de courriel ; Paiement est une catégorie de compte rendu, sans transaction financière.

L’historique réunit les comptes rendus et les annonces ciblées accessibles à l’enseignant, par pages de 50 éléments. L’auteur peut modifier ou supprimer un compte rendu pour tous ses participants. Chaque entrée présente son type, « créé par …, le … », les **Autres participants** actifs lorsqu’il y en a, puis **Concerne** : parcours / titre (ou seulement le titre). Un chevron ouvre le contenu ; une modification est indiquée en italique.

Le menu à trois points de la fenêtre propose **Exporter en PDF**, **Exporter en Markdown** et **Fermer**. Les exports portent le titre **SUIVI ADMINISTRATIF / PÉDAGOGIQUE** et regroupent **INFORMATIONS**, **INSCRIPTIONS** et **HISTORIQUE**, avec le contenu complet de toutes les activités autorisées, même repliées ou situées sur une autre page. Les discussions privées ont leur propre historique et leurs propres exports.

### Discussions privées

Dans **Paramètres du parcours**, le responsable peut activer **Autoriser les discussions dans ce parcours**. L’icône de discussion à droite du nom du compte ouvre les échanges. Les enseignants d’un même parcours peuvent aussi discuter entre eux si cette autorisation est activée : **Nouvelle discussion**, choix du parcours, puis du collègue. Les messages sont limités à 256 caractères et modifiables par leur auteur durant trois minutes. Sur téléphone, les commandes de notification et **Nouvelle discussion** sont dans le menu à trois points du titre ; le fil s’adapte à l’espace visible du clavier.

**Activer les notifications** devient **Désactiver les notifications** lorsque l’abonnement de cet appareil est actif. Le badge additionne annonces et messages non lus lorsque le système le permet. La demande d’effacement, la gestion par le responsable du parcours et l’accès global du superadmin sont détaillés dans le [guide des discussions](discussions.md).

### Gérer les contenus

La **Bibliothèque** contient toutes les pages, qu’elles soient ou non utilisées dans un parcours. Une page possède :

- un titre, un résumé, un statut et une durée estimée ;
- zéro ou plusieurs catégories parmi **Démarrage**, **Méthode**, **Activité**, **Évaluation**, **Médias**, **Lecture**, **Exercice** et **QCM** ;
- une suite ordonnée de blocs **Texte Markdown**, **Image**, **Document**, **Vidéo / audio**, **Intégration externe (iframe)** ou **Travail à rendre**.

Seuls **Image** et **Document** proposent un import de fichier ou une adresse, avec une seule source active à la fois. Image affiche l’image et sa description alternative ; Document propose le téléchargement. La taille maximale dépend des limites PHP, sans dépasser 10 Mo. Le bloc Markdown propose un aperçu et n’accepte pas d’import de fichier.

Pour intégrer une vidéo ou un lecteur audio, choisir **Vidéo / audio**, puis coller une adresse HTTP(S) de média ou le code `<iframe …></iframe>` du lecteur. Les liens YouTube de partage (`youtu.be`, `watch`, `shorts`, `live`) sont automatiquement convertis en adresse de lecteur, avec conservation du point de départ. Un code iframe conserve son titre et ses proportions ; un lecteur compact garde sa hauteur. Exception : les anciens codes RTS de 58 pixels sont affichés au format 16:9. Pour une simulation, une carte, une application ou une page externe, choisir **Intégration externe (iframe)** et régler sa hauteur entre 100 et 2 000 pixels. Le titre saisi dans le bloc est prioritaire pour l’accessibilité. Les exports proposent un lien vers le lecteur ou la page externe. Le site externe doit autoriser l’intégration de son contenu.

Le bloc **Travail à rendre** permet de demander un **lien vers un document**, un **texte court**, ou un **lien et un commentaire**. La consigne s’écrit en Markdown dans le contenu du bloc ; la légende sert de titre. La remise peut être obligatoire ou facultative. Le texte et le commentaire sont limités à **512 caractères**, avec un compteur visible. Le lien, séparé du texte, accepte une adresse HTTP(S) de 2 048 caractères maximum.

L’élève retrouve sa saisie après fermeture grâce à la sauvegarde automatique du brouillon et à une copie locale en cas de coupure réseau. Le bouton **Enregistrer le brouillon** permet aussi une sauvegarde explicite. Un brouillon reste privé et ne valide pas l’étape. **Rendre mon travail**, suivi d’une confirmation, enregistre une remise datée et verrouille les champs. Tous les blocs obligatoires doivent être remis avant l’autoévaluation ou la fin d’une étape sans autoévaluation. Une évaluation combinant QCM et travaux est signalée comme prête à noter une fois ses QCM et ses travaux obligatoires remis. L’enseignant peut toutefois saisir ou corriger sa note manuellement avant cela.

Dans la fiche de l’élève, l’enseignant peut consulter les liens et les textes remis, puis noter l’évaluation, confirmer le niveau ou confirmer la réception si l’étape n’a ni évaluation ni autoévaluation. Le pencil orange et rempli signale une confirmation attendue. **Autoriser une nouvelle remise** rouvre un bloc, conserve sa version précédente et retire la validation de l’étape pour permettre une nouvelle correction. Les remises précédentes restent consultables. L’adresse et le texte remis sont conservés ; le contenu du document externe peut évoluer chez son hébergeur.

Les réponses sont propres à l’élève et à l’étape du parcours. Les copies et exports de pages reprennent les consignes et les réglages, sans les brouillons ni les travaux des élèves.

Les titres Markdown sont disponibles du niveau `#` au niveau `######`. Une liste à puces peut commencer par `- ` ; contrairement à celle-ci, une ligne commençant par `* ` conserve son étoile comme du texte ordinaire. Une ligne contenant `---` produit un séparateur horizontal. Pour commencer la suite du contenu sur une nouvelle page dans l’export PDF, placer cette instruction sur sa propre ligne :

```html
<div style="page-break-after: always;"></div>
```

Dans la page Web, ce saut est indiqué par un trait discret. En dehors de cette instruction et de la balise sûre `<pre>` décrite ci-dessous, les balises HTML restent affichées comme du texte afin de protéger le contenu.

Les liens acceptent un titre facultatif, affiché par le navigateur au survol :

```markdown
[Corrigé des exercices](https://exemple.ch/corrige "Corrigé")
```

Pour conserver les espaces et les retours à la ligne d’un texte préformaté, utiliser un bloc de code clôturé par trois accents graves, ou la balise `<pre>` sans attribut :

````markdown
```
Première ligne
  Ligne indentée
```

<pre>
Première ligne
  Ligne indentée
</pre>
````

Le contenu de `<pre>` est toujours traité comme du texte : les éventuelles balises qu’il contient ne sont pas exécutées.

Les tableaux utilisent la syntaxe Markdown à barres verticales. Les deux-points de la ligne de séparation règlent l’alignement des colonnes :

```markdown
| Élève | Travail rendu | Score |
| :--- | :---: | ---: |
| Lina | Oui | 8 |
| Sam | En cours | 6 |
```

Ils s’adaptent à l’écran avec un défilement horizontal sur mobile et sont repris avec leurs en-têtes et alignements dans les exports PDF. Lorsque tous les séparateurs ont la même longueur (`|---|---|`), la largeur des colonnes suit automatiquement leur contenu. Lorsque leurs longueurs diffèrent, elles servent de proportions : `|---|------|` produit une première colonne d’environ un tiers et une seconde d’environ deux tiers. Pour écrire une barre verticale dans une cellule, utiliser `\|` ou la placer dans du code inline, par exemple `` `A|B` ``.

Un QCM formatif s’insère directement dans un bloc Markdown :

```markdown
:::qcm
# Question
[v] Réponse juste
[x] Réponse fausse
:::
```

Une question contenant un seul `[v]` utilise des boutons radio ; plusieurs `[v]` utilisent des cases à cocher. Les réponses sont présentées dans un ordre aléatoire à chaque affichage. Chaque question vaut le même poids et n’est réussie que si toutes ses réponses justes sont cochées sans réponse fausse. La catégorie **QCM** est ajoutée automatiquement à la page.

Les choix sont sauvegardés automatiquement comme brouillon, sans attribuer de score ni consommer la remise d’une évaluation. Attendez le message **Brouillon enregistré** : vous pouvez alors fermer la fenêtre et retrouver les cases cochées en revenant au même QCM, même après reconnexion. En cas de coupure réseau, une copie locale permet la reprise dans le même navigateur ; un avertissement indique que le serveur n’a pas encore confirmé la sauvegarde. La remise définitive efface le brouillon. Un QCM modifié par l’équipe enseignante ne réutilise pas les anciennes réponses.

Dans une étape ordinaire, l’élève voit son résultat et peut recommencer. Si le type **Évaluation** est sélectionné, le bouton devient **Terminer le QCM** et une confirmation précède la remise définitive : le score reste visible par l’élève et apparaît dans le profil de l’élève côté enseignant. Pour limiter les copier-coller, le titre, la consigne et les blocs de contenu d’une évaluation ne sont pas sélectionnables dans la vue élève ; les champs personnels et les commandes restent utilisables. Le téléchargement PDF de l’étape est également retiré et refusé côté serveur pour l’élève. Ces restrictions ne s’appliquent pas aux vues enseignantes. L’enseignant voit aussi la moyenne du groupe par étape dans le tableau de bord, y compris si l’étape a été masquée après la remise, mais jamais les réponses choisies.

Dans **Acquis → Évaluations**, une évaluation masquée reste présente pour assurer la continuité du suivi. Tant qu’elle n’est ni accessible ni notée, son vrai titre est remplacé par **Évaluation à venir**.

La recherche porte sur le titre, le résumé, les tags, les objectifs et le nom du propriétaire ou de l’auteur connu d’une copie. Les listes permettent aussi de filtrer directement par statut, tag ou objectif. Le bouton à trois points, à droite du titre **Bibliothèque de contenus**, permet de créer une nouvelle page ou d’importer une page JSON dans une fenêtre dédiée. Dans l’éditeur, un rond orange apparaît à droite de **Modifier le contenu** dès qu’un changement local n’est pas encore enregistré. Chaque enseignant accède à ses contenus ainsi qu’aux contenus partagés auxquels il est autorisé à contribuer ; le filtre initial affiche uniquement ses propres contenus.

Le choix **Brouillon / Prêt à utiliser** et le bouton **Enregistrer** restent visibles dans une barre persistante au-dessus des réglages pendant le défilement des blocs. **Enregistrer** sauvegarde sans envoyer de courriel. Le lien **Enregistrer et prévenir des modifications**, sous le statut et le bouton, sauvegarde puis prépare un courriel par élève actif ayant accès à la page. On peut donc enregistrer plusieurs corrections, puis prévenir une seule fois, même si la dernière version est déjà sauvegardée. Une erreur ou un conflit de sauvegarde empêche cet envoi. Les modifications restent signalées dans **Depuis la dernière visite** côté élève. Une page en brouillon ou hors parcours reste invisible dans le chemin de travail des élèves.

Une page qui n’est utilisée dans aucun parcours peut être supprimée définitivement depuis son écran d’édition. Tant qu’elle est utilisée, la suppression reste bloquée et il faut d’abord la retirer de chaque parcours concerné.

#### Importer et exporter une page

Une page peut être exportée en JSON depuis son écran d’édition. Le fichier contient ses métadonnées, blocs, tags et, pour les images ou fichiers locaux sous `uploads/`, une copie Base64 de la pièce jointe.

L’import se lance depuis **Bibliothèque** avec deux modes :

- **Créer une copie modifiable** attribue une nouvelle référence et ne touche pas à la page d’origine ;
- **Écraser la page portant la même référence** remplace ses métadonnées, blocs et tags tout en conservant son usage dans les parcours.

### Organiser un parcours

Dans **Parcours**, l’enseignant sélectionne un cours. Le menu à trois points situé à droite du titre est trié alphabétiquement selon la langue. Il regroupe notamment **Paramètres du parcours** et la gestion de l’équipe enseignante dans des fenêtres dédiées. L’enseignant peut ensuite :

- ajouter une page prête ;
- ouvrir directement son contenu avec **Éditer**, puis revenir au parcours d’origine avec **Parcours →** dans l’en-tête de l’éditeur ;
- modifier le nom du parcours et son code unique d’invitation ;
- changer l’ordre en saisissant directement le numéro d’une étape ou en faisant glisser ce numéro à la position voulue, à la souris comme au tactile ;
- fixer une échéance ;
- choisir son type : **Évaluation**, **Autoévaluation**, **Événement**, ou conserver une **Consultation simple** ;
- ajouter une consigne propre au cours en Markdown, notamment avec des liens ;
- rattacher les objectifs et compétences du référentiel du cours ;
- retirer une page du parcours, avec confirmation explicite de la suppression des progressions liées à cette étape ;
- archiver ou réactiver un parcours sans perdre ses données ;
- supprimer définitivement un parcours archivé dont il est propriétaire ; les données propres au parcours sont purgées, mais ses pages restent dans la bibliothèque ;
- dupliquer un parcours sans ses élèves ni leurs progressions, en conservant ses échéances ou en les remettant toutes à zéro.

La **Consigne propre à ce parcours** accepte le Markdown (liens, titres, listes, gras, italique…). Par exemple :

```markdown
LIEN >[LAB PY ](https://webe.li/tec/labs/python/ "LAB PY")
```

Le lien est cliquable dans la vue élève et dans l’aperçu enseignant. Le PDF interprète aussi la mise en forme ; le texte Markdown d’origine reste modifiable dans les réglages de l’étape.

L’organisation complète du parcours n’est jamais proposée dans la navigation élève.

#### Regrouper les étapes

Sous **＋ Ajouter une page au parcours**, **＋ Ajouter un regroupement** crée un groupe avec un titre (120 caractères maximum). Les regroupements sont facultatifs : un parcours peut conserver toutes ses étapes sans groupe, ou mélanger groupes et étapes libres.

Glisser une étape par son numéro vers le titre ou la zone vide d’un regroupement l’ajoute à la fin de ce groupe, même s’il est replié. Un dépôt sur une étape place l’étape déplacée avant ou après celle-ci, dans le même regroupement. La zone **Sans regroupement**, visible pendant le déplacement, permet de sortir une étape de son groupe. Le menu à trois points propose également **Regroupement**, puis **Déplacer**, utilisable au clavier.

Le titre ou la poignée à gauche permet de déplacer le regroupement entier, avec toutes ses étapes. Un groupe vide peut être placé avant les premières étapes, puis rempli par glisser-déposer. Le menu à trois points propose aussi **Monter**, **Descendre** et **Supprimer le regroupement** ; les flèches haut/bas du clavier déplacent le groupe lorsque son en-tête a le focus. Les groupes restent au premier niveau, sans imbrication.

Le chevron ouvre ou replie le groupe. Ce choix est mémorisé séparément pour chaque compte et chaque vue dans l’onglet du navigateur. Ouvrir les réglages d’une étape ouvre aussi son regroupement. Le petit crayon ouvre une fenêtre Bootstrap pour renommer le titre ; supprimer un regroupement conserve ses étapes, leur ordre et toute la progression.

Les numéros restent continus dans tout le parcours : chaque groupe ne recommence pas à 1. Replier un groupe ne change aucun numéro ; déplacer une étape change sa place dans la séquence, comme le réordonnancement habituel. Les groupes sont sans imbrication. Ils figurent aussi dans l’aperçu enseignant et la vue d’ensemble de l’élève. L’élève ne voit que les étapes autorisées ; un groupe sans étape accessible est masqué.

La duplication et l’import/export JSON conservent les regroupements, y compris ceux qui sont encore vides. Les anciens fichiers sans regroupement restent importables. Les regroupements n’interviennent pas dans les calculs des acquis ou de progression.

#### Types d’étapes et événements

La roue dentée ouvre ou ferme les réglages en ramenant l’affichage sur l’étape correspondante.

Dans les réglages de l’étape (roue dentée), **Type d’étape** remplace les anciennes cases indépendantes. Une étape possède un seul type ; **Évaluation** remplace le libellé « Cette étape est une évaluation ». L’autoévaluation reste le type proposé lors de l’ajout d’une page. La consultation simple permet de conserver les activités sans note ni autoévaluation.

Pour organiser une date ou une réunion :

1. Ajouter une page prête au parcours, ou régler une étape existante. Le **titre de la page** sert de nom à l’événement.
2. Choisir **Événement** et renseigner le début, la fin et, facultativement, le lieu. Les horaires sont en **Europe/Zurich** ; **Toute la journée** masque les heures. Pour plusieurs journées, saisir la dernière journée incluse comme date de fin.
3. Enregistrer l’étape. Son lecteur et l’aperçu enseignant affichent les dates et les liens **Télécharger le calendrier (.ics)** et **Ajouter à Google Calendar**.

Dans **Parcours**, le menu à trois points de l’étape permet aussi de **Télécharger le calendrier (.ics)** directement.

Le `.ics` s’importe dans un agenda compatible. Le lien Google ouvre un événement prérempli que l’utilisateur confirme dans son propre agenda. Ces ajouts ne synchronisent pas les modifications ultérieures : après un changement, mettre également à jour son agenda. Le téléchargement respecte l’accès à l’étape. Une consultation de l’événement suit les règles des étapes sans autoévaluation ; elle ne prouve pas la présence à la réunion.

Les événements et leurs horaires sont conservés dans les copies et les imports/exports JSON du parcours. **Remettre toutes les échéances à zéro** concerne les échéances pédagogiques ; les dates des événements restent à ajuster manuellement. Lors de la mise à jour d’une ancienne installation, une étape qui cumulait évaluation et autoévaluation devient **Évaluation** sans effacement des résultats historiques. Un avertissement apparaît sous **Type d’étape** uniquement lorsqu’un changement sélectionné efface des notes, niveaux, commentaires ou validations d’évaluation ou d’autoévaluation enregistrés. Il reste masqué à l’ouverture, lors de modifications sans effet sur ces données et après retour au type initial. Il précise que le changement de catégorie efface définitivement les données de validation concernées et revenir au type initial ne les restaure pas. Il invite à exporter les résultats avant toute modification. Une évaluation contenant au moins une note ne peut pas changer de type : un message renvoie au suivi des élèves pour modifier les notes. Pour changer volontairement de type, il faut d’abord retirer toutes les notes dans ce suivi (champ **Note sur 10** vide, puis **Enregistrer la note**). Une note de zéro est également protégée. Les autres changements de type gardent les règles de remise à zéro des validations concernées.

#### Aperçu élève pour l’enseignant

L’icône de chapeau ouvre **Aperçu élève** (`?view=teacher-preview`). Les étapes invisibles pour les élèves sont cachées dans la liste, la première étape et la navigation **Précédent / Suivant**. Leur adresse directe n’ouvre pas leur contenu dans cet aperçu. Les étapes restreintes restent visibles en gris clair : l’aperçu général ne représente pas un élève particulier. La gestion du parcours permet toujours à l’équipe de régler les étapes cachées.

#### Importer et exporter un parcours

Dans l’onglet **Parcours**, le bouton à trois points situé à droite du titre regroupe l’import/export, l’archivage, la modification du nom et du code, la duplication et la gestion de l’équipe enseignante. Chaque action s’ouvre dans une fenêtre dédiée. L’import/export produit un JSON versionné. L’export peut inclure ou omettre les compétences et types de rewards ; les objectifs proviennent des pages. Il contient les étapes et les références stables des pages, mais jamais les pages elles-mêmes.

À l’import :

- **Créer une copie modifiable** crée un nouveau parcours sans élève ni progression ;
- **Écraser le parcours portant la même référence** remplace ses étapes et options ; ses progressions et rewards liés aux anciennes étapes sont supprimés ;
- l’option **Remettre toutes les échéances à zéro** ignore les échéances pédagogiques du fichier, en conservant les dates des événements ;
- toutes les pages référencées sont vérifiées avant écriture. Une page absente arrête entièrement l’import.

Le menu à trois points de **Gestion du parcours** propose une seule entrée **Vue synthétique du parcours**, puis une fenêtre permet de choisir **PDF** ou **DOCX** : numéro d’étape, nom, échéance et durée. Les deux formats conservent les titres et l’ordre des regroupements, y compris les groupes vides, ainsi que les légendes d’objectifs et compétences. Le DOCX contient un tableau A4 paysage modifiable dans Word ou LibreOffice. À droite de chaque étape, le menu à trois points propose PDF, Markdown, DOCX et LaTeX. Le choix PDF télécharge directement une fiche détaillée contenant toutes les métadonnées du parcours, la consigne, les tags, objectifs, compétences et le contenu complet de la page. Les vidéos et fichiers externes sont indiqués par leur lien ; les images locales sont reproduites dans le PDF.

### Importer et exporter les élèves

Le bouton à trois points situé à droite du titre **Élèves & inscriptions** regroupe le nouvel accès, l’inscription groupée et l’import/export des élèves dans des fenêtres dédiées. L’import/export contient les profils et leurs inscriptions aux parcours de l’enseignant. Aucun mot de passe, jeton ou secret de session n’est exporté.

Un import conserve le numéro de téléphone des élèves déjà présents : sa modification reste réservée à l’élève. Le mode **Modifier/créer** conserve les autres inscriptions existantes. Le mode **Écraser** remplace, pour chaque élève importé, ses inscriptions aux parcours de l’enseignant. Un parcours référencé mais absent bloque entièrement l’import. Un fichier peut contenir au maximum **500 élèves** et peser jusqu’à **25 Mo**.

L’option **Activation des nouveaux comptes** propose deux comportements :

- **Validation par courriel par chaque élève**, sélectionnée par défaut, laisse chaque nouveau compte en attente pendant 15 minutes ;
- **Activation immédiate par l’enseignant** rend les nouveaux comptes actifs et utilisables dès la fin de l’import, après une confirmation supplémentaire. Elle active aussi un compte encore en attente s’il avait déjà été créé et était géré par ce même enseignant. Elle ne réactive jamais un compte archivé ou géré par une autre personne.

### Mettre à jour l’application

Cette fonction est visible uniquement par le superadmin dans **Superadministration**. Le tableau **Versions et mises à jour** indique la version installée et la dernière version stable publiée. **Vérifier maintenant** actualise ces informations ; lorsqu’une version plus récente existe, **Mettre à jour la version actuelle avec…** sauvegarde la base, vérifie les fichiers téléchargés et installe le nouveau code sans supprimer la base ni les documents importés. L’heure de la dernière vérification est affichée dans le fuseau Europe/Zurich.

Avec le nouvel outil de maintenance, seuls les fichiers nouveaux ou différents sont remplacés ; seuls les anciens fichiers remplacés ou supprimés sont sauvegardés. Le téléchargement et sa vérification restent complets. Le gain commence après l’installation de cet outil, pour les mises à jour suivantes. **Nettoyer les sauvegardes et mises à jour** retire les sauvegardes techniques, y compris les anciennes copies complètes, sans supprimer les bases actives ni les clés de notifications. Voir le [guide d’exploitation](exploitation.md#mettre-à-jour-depuis-la-superadministration) pour la restauration et les deux bases.

## Superadministration

Un enseignant peut porter l’indicateur `is_superadmin`. Le compte de démonstration Nora possède ce droit. **Superadministration** est accessible depuis le menu **Connecté·e comme** ; elle ne possède pas d’onglet supplémentaire dans la navigation principale. Cet espace permet d’effacer définitivement :

- n’importe quel élève ou enseignant, y compris le compte actuellement connecté ;
- n’importe quelle page, même utilisée dans un parcours ;
- n’importe quel parcours, actif ou archivé.

Ces opérations suppriment en cascade les inscriptions, étapes, progressions et rewards concernés et demandent toujours une confirmation explicite.

### Définir le référentiel et les rewards

Le panneau latéral de l’écran Parcours présente les objectifs issus automatiquement des pages qui le composent et permet d’ajouter :

- des compétences avec un code court ;
- des types de rewards avec une icône et un nombre de points proposé.

Une même page peut donc être utilisée dans plusieurs cours avec un ordre, une échéance, des objectifs et des compétences différents.


### Vue du parcours mémorisée

Le lien **Parcours** de la navigation ouvre par défaut **l’aperçu élève**. La bascule en haut de page permet de passer à l’édition ou de revenir à l’aperçu. Le dernier mode ouvert est mémorisé pendant un an dans ce navigateur, séparément pour chaque compte enseignant et chaque installation, même après déconnexion. Les liens explicitement destinés à organiser ou modifier un parcours continuent d’ouvrir l’édition.


### Passer des évaluations au parcours

Dans **Acquis → Évaluations**, cliquer sur le titre d’une évaluation ramène à l’étape correspondante dans **Parcours**, avec son regroupement ouvert. Le lien **Voir mon évaluation**, sous cette étape et dans son lecteur, revient à la bonne ligne des évaluations. Le parcours courant est conservé. Une étape devenue inaccessible garde ses résultats selon les règles existantes, mais son titre ne permet plus d’ouvrir son contenu.

### Repérer et filtrer les contenus de la bibliothèque

La bibliothèque affiche par défaut **vos contenus**, copies indépendantes comprises. Dans **Origine**, votre nom est le premier enseignant proposé ; vous pouvez cocher plusieurs enseignants ou choisir **Voir tous les contenus**. Seuls les propriétaires de contenus auxquels vous avez déjà accès sont proposés. Le nom désigne le propriétaire actuel : une copie indépendante vous appartient.

Le filtre **Partage** accepte plusieurs choix : **Mes contenus partagés** (sources communes déjà utilisées par des collègues), **Copies indépendantes effectuées** et **Utilisations externes autorisées**. Plusieurs choix d’un même filtre s’additionnent ; les filtres Origine, Partage, recherche, statut, tag et objectif se combinent. Les choix multiples restent disponibles sur téléphone. Tous les filtres de bibliothèque (recherche, statut, tag, objectif, origine et partage) sont mémorisés par compte dans ce navigateur, puis rétablis au retour. Sans choix mémorisé, seuls vos contenus sont affichés. Un filtre dont l’option a disparu est réinitialisé.

Sur une carte, cliquez sur le titre ou sur la description pour les modifier sur place, puis choisissez **Enregistrer** ou **Annuler**. Une description vide propose **Ajouter une description**. Seul le lien **Modifier →** ouvre l’éditeur complet. Les objectifs de plus de 40 caractères sont raccourcis avec `...` ; cliquez sur un objectif pour le déplier et le lire en entier, avec son explication éventuelle. Cliquez à nouveau pour le replier.

- **Blanc** : votre contenu, y compris une copie indépendante. Autoriser un partage ne suffit pas à colorer la fiche.
- **Bleu pâle** : votre source commune est déjà récupérée par un collègue dans sa bibliothèque, ou utilisée dans un parcours accessible à un autre enseignant actif. Une copie indépendante faite par un collègue ne colore pas la source en bleu.
- **Orange pâle** : le propriétaire est un autre enseignant. Son prénom et son nom apparaissent dans une étiquette orange arrondie entre le statut et la durée.
- **Pastille crème** sur vos fiches : la copie indépendante est autorisée. **Pastille gris clair** : l’utilisation externe est autorisée. Ces autorisations doivent être actives et la page prête.

Les pastilles de vos contenus se trouvent immédiatement à droite de **Prêt** ou **Brouillon**. Les quatre légendes sont regroupées en bas de la bibliothèque : **Mes contenus partagés**, **Contenus externes partagés avec moi**, **Copie indépendante autorisée** et **Utilisation externe autorisée**.

Les copies issues d’un lien de partage gardent une indication d’origine et restent indépendantes, même si la source disparaît. Les anciennes copies identifiables par leur reçu de récupération sont reprises automatiquement. Les fichiers JSON importés sans information d’origine ne permettent pas de retrouver leur auteur ; aucune origine n’est inventée.

Dans **Parcours**, la ligne de date, durée et mode porte une pastille **bleue** pour votre source commune utilisée par d’autres, ou **orange** pour une source appartenant à un autre enseignant. Le libellé accessible et l’infobulle précisent sa signification. Une simple copie indépendante ne porte aucune pastille de provenance dans le parcours. Désactiver un lien retire les nouvelles autorisations, mais ne retire pas les indicateurs d’une utilisation commune déjà acquise.

### Partager un contenu avec des collègues

Dans le parcours, **Partager ce contenu** se trouve à côté du bouton d’export PDF dans l’aperçu enseignant, et à côté des exports dans la ligne d’étape. Il est aussi accessible dans l’éditeur de la bibliothèque. La page doit être au statut **Prêt**. Seul le propriétaire peut activer le partage et sélectionner les modes autorisés. Un seul lien est proposé : le collègue qui l’ouvre choisit son mode parmi ceux autorisés.

| Mode | Utilisation | Effet des modifications |
|---|---|---|
| **Contenu partagé** | Le collègue se connecte à la même plateforme et récupère la page dans sa bibliothèque, éventuellement dans l’un de ses parcours. | La même page est utilisée et modifiable par les équipes des parcours concernés ; les modifications du contenu sont communes. Les consignes, échéances et résultats restent propres à chaque parcours. |
| **Copie indépendante** | Le collègue récupère sa propre page ou télécharge le JSON pour l’importer dans une autre installation liike. | La copie possède sa référence, ses blocs, objectifs et catégories. Aucune modification ultérieure n’est synchronisée. |
| **Utilisation externe** | Consultation sans compte, entraînement individuel ou séance collective `qcm_classe` animée par un enseignant. | Le contenu suit la page source. Les élèves participent sans inscription et sans note nominative. |

Une récupération directement dans un parcours crée une étape de consultation simple ; le collègue règle ensuite son type, ses consignes et échéances. Réutiliser le même lien de copie avec le même compte retrouve la copie déjà créée, sans la remplacer. Aucun élève, résultat, commentaire privé ou historique du parcours source n’est transféré.

Le propriétaire peut changer les modes ou désactiver le partage à tout moment. **L’URL reste identique**, y compris après réactivation. Les réglages s’appliquent aux nouvelles utilisations ; les pages déjà récupérées et les copies restent chez les collègues. Désactiver le partage ou le mode externe ferme également les séances anonymes ouvertes. Le retour en brouillon suspend l’accès externe. Le partage ne donne aucun accès au parcours source ni à ses élèves.

Une personne non connectée voit uniquement **Utilisation anonyme**, le mode **Utilisation externe** s’il est autorisé, et le bouton **Compris**. Aucun mode de récupération ni téléchargement JSON n’est accessible sans compte enseignant actif, même par une URL directe. Si l’utilisation externe est désactivée, la page indique qu’elle n’est pas disponible anonymement. Le lien facultatif **Se connecter pour récupérer ce contenu**, présent si un mode de récupération est autorisé, permet au collègue de retrouver ces modes. Les comptes élèves restent limités à l’utilisation externe.

Le lien de contenu est destiné aux collègues : après connexion enseignant, si les modes partagé ou copie sont autorisés, il permet de modifier la page ou d’en obtenir les sources, avec les réponses des QCM. Pour une séance collective, transmettre aux élèves uniquement **le lien ou le QR code de la séance**.

#### Animer un QCM en classe sans compte

1. Le collègue ouvre le lien, puis choisit **Utilisation externe** ou clique sur **Compris** dans la présentation anonyme. Aucune connexion n’est nécessaire pour consulter le contenu ou animer la séance.
2. Pour chaque bloc `:::qcm_classe`, **Animer une séance anonyme** crée une séance distincte. Les séances des différents collègues sont indépendantes. Le navigateur qui crée la séance en conserve les commandes ; le lien et le QR code servent uniquement à rejoindre la participation.
3. Il affiche le QR code, éventuellement dans **Ouvrir la projection**, ou transmet le lien de la séance. Les élèves cliquent sur **Rejoindre anonymement**, sans nom, courriel ni compte.
4. L’enseignant ouvre les questions, ferme les votes et révèle les corrections. Les résultats sont des totaux anonymes. Un vote peut être modifié tant que la question reste ouverte ; aucune note n’est ajoutée à un parcours.

Une séance est disponible 24 heures. Les identifiants aléatoires et réponses expirés sont supprimés au prochain accès à l’application. Le QR code est généré dans le navigateur, sans service externe. La participation est liée à la session du navigateur : changer de navigateur peut créer une nouvelle participation, et les résultats ne permettent pas d’identifier les élèves. Sans compte, le pilotage est lié à la session du navigateur créateur : il reste disponible au rechargement et dans la projection ouverte depuis ce navigateur. Une autre personne qui ouvre le lien ne reçoit pas ces commandes. Si cette session est effacée ou expire, il faut créer une nouvelle séance. Limites : 500 participations par séance et 20 séances simultanées par compte enseignant ou session d’animation anonyme.

Les QCM simples et stricts restent des entraînements individuels dans le lecteur externe. Les blocs de travail sont consultables, mais la remise et la notation demandent une inscription à un parcours. Les intégrations dépendent des autorisations du site intégré. Les fichiers locaux d’images et documents de moins de 10 Mo sont embarqués dans le JSON ; les autres références doivent rester accessibles depuis l’installation de destination.
