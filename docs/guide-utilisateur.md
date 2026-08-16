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

### Rejoindre un cours avec une invitation

Chaque parcours actif possède un code unique et un lien d’invitation. L’enseignant les trouve dans **Élèves**, depuis le menu à trois points de **Élèves & inscriptions**, puis **Invitation au parcours**, et peut les copier en un clic.

- un élève déjà connecté confirme le cours puis le rejoint immédiatement ;
- un élève qui ouvre le lien sans compte peut créer son compte, saisir ses informations et valider son courriel ; son inscription au cours est alors déjà préparée ;
- le code peut aussi être saisi depuis le menu **Connecté·e comme**, avec **Rejoindre un cours** sous **Modifier mon profil**.

Les codes sont comparés sans distinction de casse. Un cours archivé ne peut pas être rejoint. Si l’enseignant a archivé une participation, l’élève doit lui demander de la réactiver : le code d’invitation ne contourne pas cette décision.

## Parcours élève

### Accueil « Aujourd’hui »

L’élève voit immédiatement :

- le cours sélectionné ;
- sa progression globale ;
- le nombre d’étapes réalisées et confirmées ;
- la prochaine étape utile ;
- son échéance et son éventuel statut d’évaluation ;
- son score de rewards.

La liste « Vue d’ensemble » distingue trois états : à faire, envoyé à l’enseignant et confirmé.

### Réaliser une étape

Une page peut contenir plusieurs blocs : Markdown, image, fichier téléchargeable ou iframe. Les objectifs et compétences mobilisés sont affichés en tête de page.

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
- **Retirer définitivement** supprime la participation et tout son historique dans ce cours ;
- **Archiver le compte** bloque la connexion et archive ses participations ;
- **Effacer définitivement** supprime le compte et toutes ses données.

Si le compte participe au cours d’un autre enseignant, son archivage ou sa suppression globale est bloqué. Une confirmation explicite précède chaque suppression définitive.

### Suivre un cours

Depuis **Suivi**, ouvrir un élève. La dernière visite connue apparaît à droite de chaque étape. Le bouton **Historique des visites** ouvre un tableau par jour et par page avec la première visite, la dernière activité, le nombre de sessions et le temps actif estimé.

Le navigateur enregistre l’ouverture immédiatement, puis actualise le temps actif toutes les 60 secondes et à la fermeture ou au masquage de l’onglet. Les données de plus d’un mois sont automatiquement supprimées.

Le tableau de bord affiche la progression moyenne, le nombre de validations en attente et la prochaine évaluation. La liste des élèves montre leur avancement, les confirmations attendues et leur score de rewards.

En ouvrant un élève, l’enseignant peut :

- lire son auto-positionnement et sa note ;
- confirmer un niveau de 0 à 3 ;
- rédiger une **Note / Commentaire (visible par l'apprenant)** ;
- attribuer facultativement un reward, des points et un message ;
- consulter les compétences confirmées et les derniers rewards.

### Gérer les contenus

La **Bibliothèque** contient toutes les pages, qu’elles soient ou non utilisées dans un parcours. Une page possède :

- un titre, un résumé, un statut et une durée estimée ;
- zéro ou plusieurs catégories parmi **Démarrage**, **Méthode**, **Activité**, **Évaluation**, **Médias**, **Lecture**, **Exercice** et **QCM** ;
- une suite ordonnée de blocs Markdown, image, fichier ou iframe.

Les tableaux utilisent la syntaxe Markdown à barres verticales. Les deux-points de la ligne de séparation règlent l’alignement des colonnes :

```markdown
| Élève | Travail rendu | Score |
| :--- | :---: | ---: |
| Lina | Oui | 8 |
| Sam | En cours | 6 |
```

Ils s’adaptent à l’écran avec un défilement horizontal sur mobile et sont repris avec leurs en-têtes et alignements dans les exports PDF. Pour écrire une barre verticale dans une cellule, utiliser `\|` ou la placer dans du code inline, par exemple `` `A|B` ``.

Un QCM formatif s’insère directement dans un bloc Markdown :

```markdown
:::qcm
# Question
[v] Réponse juste
[x] Réponse fausse
:::
```

Une question contenant un seul `[v]` utilise des boutons radio ; plusieurs `[v]` utilisent des cases à cocher. Chaque question vaut le même poids et n’est réussie que si toutes ses réponses justes sont cochées sans réponse fausse. La catégorie **QCM** est ajoutée automatiquement à la page. L’élève voit son résultat et peut recommencer. L’enseignant voit uniquement le pourcentage de chaque élève dans son profil et la moyenne du groupe par étape dans le tableau de bord, jamais les réponses choisies.

La recherche porte sur le titre, le résumé, les tags et les objectifs des parcours qui utilisent la page. Les listes permettent aussi de filtrer directement par statut, tag ou objectif. Le bouton à trois points, à droite du titre **Bibliothèque de contenus**, permet de créer une nouvelle page ou d’importer une page JSON dans une fenêtre dédiée. Chaque enseignant ne voit et ne modifie que sa propre bibliothèque.

Le choix **Brouillon / Prêt à utiliser** et le bouton **Enregistrer** restent visibles dans une barre persistante au-dessus des réglages pendant le défilement des blocs. Enregistrer une page déjà utilisée prépare un email pour chaque élève concerné. Une page en brouillon ou hors parcours reste invisible dans le chemin de travail des élèves.

Une page qui n’est utilisée dans aucun parcours peut être supprimée définitivement depuis son écran d’édition. Tant qu’elle est utilisée, la suppression reste bloquée et il faut d’abord la retirer de chaque parcours concerné.

#### Importer et exporter une page

Une page peut être exportée en JSON depuis son écran d’édition. Le fichier contient ses métadonnées, blocs, tags et, pour les images ou fichiers locaux sous `uploads/`, une copie Base64 de la pièce jointe.

L’import se lance depuis **Bibliothèque** avec deux modes :

- **Créer une copie modifiable** attribue une nouvelle référence et ne touche pas à la page d’origine ;
- **Écraser la page portant la même référence** remplace ses métadonnées, blocs et tags tout en conservant son usage dans les parcours.

### Organiser un parcours

Dans **Parcours**, l’enseignant sélectionne un cours. Le bouton à trois points situé à droite du titre regroupe notamment la gestion de l’équipe enseignante dans une fenêtre dédiée. L’enseignant peut ensuite :

- ajoute une page prête ;
- modifie le nom du parcours et son code unique d’invitation ;
- change l’ordre avec les flèches ;
- fixe une échéance ;
- marque une étape comme évaluation ;
- ajoute une consigne propre au cours ;
- rattache les objectifs et compétences du référentiel du cours.
- retire une page du parcours, avec confirmation explicite de la suppression des progressions liées à cette étape ;
- archive ou réactive un parcours sans perdre ses données ;
- duplique un parcours sans ses élèves ni leurs progressions, en conservant ses échéances ou en les remettant toutes à zéro.

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

## Superadministration

Un enseignant peut porter l’indicateur `is_superadmin`. Le compte de démonstration Nora possède ce droit. **Superadministration** est accessible depuis le menu **Connecté·e comme** ; elle ne possède pas d’onglet supplémentaire dans la navigation principale. Cet espace permet d’effacer définitivement :

- n’importe quel élève ou enseignant, y compris le compte actuellement connecté ;
- n’importe quelle page, même utilisée dans un parcours ;
- n’importe quel parcours, actif ou archivé.

Ces opérations suppriment en cascade les inscriptions, étapes, progressions et rewards concernés et demandent toujours une confirmation explicite.

### Définir le référentiel et les rewards

Le panneau latéral de l’écran Parcours permet d’ajouter :

- des objectifs propres au cours ;
- des compétences avec un code court ;
- des types de rewards avec une icône et un nombre de points proposé.

Une même page peut donc être utilisée dans plusieurs cours avec un ordre, une échéance, des objectifs et des compétences différents.
