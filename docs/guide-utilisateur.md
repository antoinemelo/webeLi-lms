# Guide utilisateur

## Se connecter

### Créer un compte

Le lien **S’inscrire** ouvre une page distincte pour les élèves et les enseignants. Un élève renseigne ses coordonnées et son groupe classe ; il peut saisir immédiatement le code transmis par son enseignant ou rejoindre un cours plus tard. Un enseignant choisit son identifiant, son mot de passe et le nom de son premier cours.

Les identifiants sont vérifiés sans distinction de casse. Si le code proposé existe déjà, liike ajoute automatiquement un chiffre supérieur à zéro : `LIROS`, `LIROS1`, `LIROS2`, etc.

Un courriel contient ensuite un lien d’activation valable **15 minutes**. Avant le clic, le compte ne peut pas se connecter. Une inscription non validée est supprimée automatiquement à expiration. Le formulaire public applique des plafonds par adresse réseau, par heure, par jour et sur le nombre de comptes publics simultanément en attente ; il impose aussi un bref délai humain et utilise un champ-piège invisible aux visiteurs. Les créations et imports effectués depuis l’espace d’un enseignant authentifié ne sont pas soumis à ces plafonds anti-robot.

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

Le menu à trois points de **Élèves & inscriptions** ouvre **Nouvel accès**, **Inscription groupée**, **Invitation au parcours** et l’import/export. La recherche accepte le nom, le prénom et le courriel ; les filtres limitent l’annuaire par groupe ou parcours. Une inscription déjà existante n’est pas dupliquée.

Une adresse électronique secondaire privée peut être ajoutée à la fiche d’un élève. Seuls les enseignants autorisés et le superadmin peuvent la voir ou la modifier. Lorsqu’elle est renseignée, sa mise à jour est intégrée à la partie **Gérer** de la fiche.

### Archiver ou supprimer

Dans l’annuaire, **Gérer** ouvre les commandes d’une participation et du compte :

- **Archiver** une participation la masque à l’élève mais conserve progression, validations et rewards ;
- **Retirer définitivement** supprime la participation et tout son historique dans ce cours (progression, validations, QCM, visites, notes privées et accès ciblés), sans toucher au compte ni à ses autres cours ;
- **Archiver le compte** bloque la connexion et archive ses participations ;
- **Effacer définitivement** supprime le compte et toutes ses données.

Si le compte participe au cours d’un autre enseignant, son archivage ou sa suppression globale est bloqué. Une confirmation explicite précède chaque suppression définitive.

### Suivre un cours

Depuis **Suivi**, ouvrir un élève. La dernière visite connue apparaît à droite de chaque étape. Le bouton **Historique des visites** ouvre un tableau par jour et par page avec la première visite, la dernière activité, le nombre de sessions et le temps actif estimé.

Le navigateur enregistre l’ouverture immédiatement, puis actualise le temps actif toutes les 60 secondes et à la fermeture ou au masquage de l’onglet. Les données de plus d’un mois sont automatiquement supprimées.

Le tableau de bord affiche la progression moyenne, le nombre de validations en attente et la prochaine évaluation. La case **Progression moyenne** présente le pourcentage de progression puis la moyenne des notes sur 10 (par exemple **61% / 7,00**). Chaque élève actif du cours compte à parts égales, à partir de sa moyenne pondérée des évaluations suivies. Les élèves sans note sont exclus du calcul ; un tiret apparaît si aucune note n’est disponible. La liste des élèves montre leur avancement, les confirmations attendues et leur score de rewards. Après **Progression**, la colonne **Moyennes** présente la moyenne pondérée des évaluations sur 10 puis la moyenne simple des niveaux autoévalués sur 3, chacune au dixième (par exemple **7,0 | 2,5**). Les calculs portent sur les étapes suivies du cours ; seules les autoévaluations remises et encore activées sont retenues. Une valeur absente est indiquée par **—**. Le tri **Moyennes** compare d’abord les notes d’évaluation, puis les niveaux autoévalués. Une évaluation QCM rejoint **À confirmer** lorsque tous les QCM de l’étape ont été remis ; l’étape n’est comptée qu’une fois, même si elle contient aussi une autoévaluation.

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

### Suivi administratif

Dans **Élèves**, le statut actif/inactif apparaît à côté des initiales et **Gérer**, à droite du code, ouvre une fenêtre avec Informations, Inscriptions et Historique. Le suivi pédagogique détaillé reste accessible depuis **Suivi**.

**Ajouter un suivi** enregistre une Réunion (y compris une discussion), une Correspondance ou un Paiement pour un ou plusieurs élèves. Ces actions sont également accessibles depuis le menu Actions du tableau de bord. Le compte rendu commun comporte une date et une heure, un titre de 160 caractères maximum, un contenu Markdown de 5 000 caractères maximum et, facultativement, un parcours. Il peut reprendre un modèle. Son enregistrement n’envoie pas de courriel ; Paiement est une catégorie de compte rendu, sans transaction financière.

L’historique réunit les comptes rendus et les annonces ciblées accessibles à l’enseignant, par pages de 50 éléments. L’auteur peut modifier ou supprimer un compte rendu pour tous ses participants. Chaque entrée présente son type, « créé par …, le … », les **Autres participants** actifs lorsqu’il y en a, puis **Concerne** : parcours / titre (ou seulement le titre). Un chevron ouvre le contenu ; une modification est indiquée en italique.

Le menu à trois points de la fenêtre propose **Exporter en PDF**, **Exporter en Markdown** et **Fermer**. Les exports portent le titre **SUIVI ADMINISTRATIF / PÉDAGOGIQUE** et regroupent **INFORMATIONS**, **INSCRIPTIONS** et **HISTORIQUE**, avec le contenu complet de toutes les activités autorisées, même repliées ou situées sur une autre page. Les discussions privées ont leur propre historique et leurs propres exports.

### Discussions privées

Dans **Paramètres du parcours**, le responsable peut activer **Autoriser les élèves à contacter les enseignants**. L’icône de discussion à droite du nom du compte ouvre les échanges. Les messages sont limités à 256 caractères et modifiables par leur auteur durant trois minutes. Sur téléphone, les commandes de notification et **Nouvelle discussion** sont dans le menu à trois points du titre ; le fil s’adapte à l’espace visible du clavier.

**Activer les notifications** devient **Désactiver les notifications** lorsque l’abonnement de cet appareil est actif. Le badge additionne annonces et messages non lus lorsque le système le permet. La demande d’effacement, la gestion par le responsable du parcours et l’accès global du superadmin sont détaillés dans le [guide des discussions](discussions.md).

### Gérer les contenus

La **Bibliothèque** contient toutes les pages, qu’elles soient ou non utilisées dans un parcours. Une page possède :

- un titre, un résumé, un statut et une durée estimée ;
- zéro ou plusieurs catégories parmi **Démarrage**, **Méthode**, **Activité**, **Évaluation**, **Médias**, **Lecture**, **Exercice** et **QCM** ;
- une suite ordonnée de blocs **Texte Markdown**, **Image**, **Document**, **Vidéo / audio**, **Intégration externe (iframe)** ou **Travail à rendre**.

Seuls **Image** et **Document** proposent un import de fichier ou une adresse, avec une seule source active à la fois. Image affiche l’image et sa description alternative ; Document propose le téléchargement. La taille maximale dépend des limites PHP, sans dépasser 10 Mo. Le bloc Markdown propose un aperçu et n’accepte pas d’import de fichier.

Pour intégrer une vidéo ou un lecteur audio, choisir **Vidéo / audio**, puis coller une adresse HTTP(S) de média ou le code `<iframe …></iframe>` du lecteur. Les liens YouTube de partage (`youtu.be`, `watch`, `shorts`, `live`) sont automatiquement convertis en adresse de lecteur, avec conservation du point de départ. Un code iframe conserve son titre et ses proportions ; un lecteur compact garde sa hauteur. Exception : les anciens codes RTS de 58 pixels sont affichés au format 16:9. Pour une simulation, une carte, une application ou une page externe, choisir **Intégration externe (iframe)** et régler sa hauteur entre 100 et 2 000 pixels. Le titre saisi dans le bloc est prioritaire pour l’accessibilité. Les exports proposent un lien vers le lecteur ou la page externe. Le site externe doit autoriser l’intégration de son contenu.

Le bloc **Travail à rendre** permet de demander un **lien vers un document**, un **texte court**, ou un **lien et un commentaire**. La consigne s’écrit en Markdown dans le contenu du bloc ; la légende sert de titre. La remise peut être obligatoire ou facultative. Le texte et le commentaire sont limités à **512 caractères**, avec un compteur visible. Le lien, séparé du texte, accepte une adresse HTTP(S) de 2 048 caractères maximum.

L’élève retrouve sa saisie après fermeture grâce à la sauvegarde automatique du brouillon et à une copie locale en cas de coupure réseau. Le bouton **Enregistrer le brouillon** permet aussi une sauvegarde explicite. Un brouillon reste privé et ne valide pas l’étape. **Rendre mon travail**, suivi d’une confirmation, enregistre une remise datée et verrouille les champs. Tous les blocs obligatoires doivent être remis avant l’autoévaluation ou la fin d’une étape sans autoévaluation. Une évaluation combinant QCM et travaux n’est disponible pour la notation qu’une fois ses QCM et ses travaux obligatoires remis.

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

Dans une étape ordinaire, l’élève voit son résultat et peut recommencer. Si la case **Cette étape est une évaluation** est cochée, le bouton devient **Terminer le QCM** et une confirmation précède la remise définitive : le score reste visible par l’élève et apparaît dans le profil de l’élève côté enseignant. Pour limiter les copier-coller, le titre, la consigne et les blocs de contenu d’une évaluation ne sont pas sélectionnables dans la vue élève ; les champs personnels et les commandes restent utilisables. Le téléchargement PDF de l’étape est également retiré et refusé côté serveur pour l’élève. Ces restrictions ne s’appliquent pas aux vues enseignantes. L’enseignant voit aussi la moyenne du groupe par étape dans le tableau de bord, y compris si l’étape a été masquée après la remise, mais jamais les réponses choisies.

Dans **Acquis → Évaluations**, une évaluation masquée reste présente pour assurer la continuité du suivi. Tant qu’elle n’est ni accessible ni notée, son vrai titre est remplacé par **Évaluation à venir**.

La recherche porte sur le titre, le résumé, les tags et les objectifs des parcours qui utilisent la page. Les listes permettent aussi de filtrer directement par statut, tag ou objectif. Le bouton à trois points, à droite du titre **Bibliothèque de contenus**, permet de créer une nouvelle page ou d’importer une page JSON dans une fenêtre dédiée. Dans l’éditeur, un rond orange apparaît à droite de **Modifier le contenu** dès qu’un changement local n’est pas encore enregistré. Chaque enseignant ne voit et ne modifie que sa propre bibliothèque.

Le choix **Brouillon / Prêt à utiliser** et le bouton **Enregistrer** restent visibles dans une barre persistante au-dessus des réglages pendant le défilement des blocs. Enregistrer une page déjà utilisée prépare un email pour chaque élève concerné. Une page en brouillon ou hors parcours reste invisible dans le chemin de travail des élèves.

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
- marquer une étape comme évaluation ;
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

#### Importer et exporter un parcours

Dans l’onglet **Parcours**, le bouton à trois points situé à droite du titre regroupe l’import/export, l’archivage, la modification du nom et du code, la duplication et la gestion de l’équipe enseignante. Chaque action s’ouvre dans une fenêtre dédiée. L’import/export produit un JSON versionné. L’export peut inclure ou omettre les objectifs, compétences et types de rewards. Il contient les étapes et les références stables des pages, mais jamais les pages elles-mêmes.

À l’import :

- **Créer une copie modifiable** crée un nouveau parcours sans élève ni progression ;
- **Écraser le parcours portant la même référence** remplace ses étapes et options ; ses progressions et rewards liés aux anciennes étapes sont supprimés ;
- l’option **Remettre toutes les échéances à zéro** ignore les dates du fichier ;
- toutes les pages référencées sont vérifiées avant écriture. Une page absente arrête entièrement l’import.

Le menu à trois points de **Gestion du parcours** donne directement accès à la **Vue synthétique du parcours** et télécharge son PDF : numéro d’étape, nom, échéance, durée et type. À droite de chaque étape, l’icône PDF télécharge directement une fiche détaillée contenant toutes les métadonnées du parcours, la consigne, les tags, objectifs, compétences et le contenu complet de la page. Les vidéos et fichiers externes sont indiqués par leur lien ; les images locales sont reproduites dans le PDF.

### Importer et exporter les élèves

Le bouton à trois points situé à droite du titre **Élèves & inscriptions** regroupe le nouvel accès, l’inscription groupée et l’import/export des élèves dans des fenêtres dédiées. L’import/export contient les profils et leurs inscriptions aux parcours de l’enseignant. Aucun mot de passe, jeton ou secret de session n’est exporté.

Le mode **Modifier/créer** conserve les autres inscriptions existantes. Le mode **Écraser** remplace, pour chaque élève importé, ses inscriptions aux parcours de l’enseignant. Un parcours référencé mais absent bloque entièrement l’import. Un fichier peut contenir au maximum **500 élèves** et peser jusqu’à **25 Mo**.

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
