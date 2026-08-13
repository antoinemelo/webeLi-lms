# liike — micro-CMS de parcours pédagogiques

liike est un prototype volontairement simple : PHP 8.2, SQLite et HTML/JS sans dépendance Composer. La présentation repose sur Bootstrap 5.3 et Bootstrap Icons, tous deux servis localement, complétés par un thème CSS léger propre aux composants pédagogiques. Il reprend de `/cms/dev` les idées utiles (contenus en blocs, taxonomies, contraintes SQLite et boîte d’événements), dans un projet beaucoup plus petit.

## Documentation

- [Vue d’ensemble de la documentation](docs/README.md)
- [Guide utilisateur](docs/guide-utilisateur.md)
- [Modèle fonctionnel et règles métier](docs/modele-fonctionnel.md)
- [Architecture technique et base SQLite](docs/architecture.md)
- [Installation, exploitation et emails](docs/exploitation.md)
- [Limites connues et prochaines étapes](docs/limitations-roadmap.md)

## Démarrer

Avec le serveur WebeLi commun :

```bash
cd /home/amelo/Documents/DEV/WebeLi/web/server
./start.sh
```

Puis ouvrir `http://127.0.0.1:8080/lms/`.

Pour lancer liike seul :

```bash
cd /home/amelo/Documents/DEV/WebeLi/web/lms/dev
php -S 127.0.0.1:8098 -t public
```

Puis ouvrir `http://127.0.0.1:8098`. Dans les deux modes, la base `storage/apr.sqlite` et les données de démonstration sont créées automatiquement.

## Accès de démonstration

Enseignante :

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

## Préparer une publication Git

`apr.py` peut aussi produire un dépôt de diffusion directement exploitable par une instance Web. Le dépôt distant par défaut est `git@github.com:antoinemelo/webeLi-lms.git` et la branche est `main` :

```bash
python3 scripts/apr.py \
  --git-release ../git
```

Cette commande crée le dépôt local, configure `origin`, génère `VERSION` et `RELEASE.json`, puis crée un commit. Elle ne contacte pas GitHub. Après contrôle du contenu, le push doit être demandé explicitement :

```bash
python3 scripts/apr.py \
  --git-release ../git \
  --force \
  --git-push
```

`storage/apr.sqlite` et les fichiers propres à une installation sous `uploads/` sont ignorés et ne sont jamais ajoutés au dépôt. Une publication ultérieure conserve l’historique Git et s’arrête si le dépôt local contient des modifications non validées.

## Ce qui est couvert

- bibliothèque de pages indépendantes, prêtes ou en brouillon ;
- recherche de pages par texte, statut, tags et objectifs ;
- import/export JSON des pages, parcours et élèves ;
- blocs Markdown, image, fichier et iframe, avec import local ;
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
- exports PDF du tableau d’un parcours et du détail de chaque étape ;
- objectifs et compétences définis au niveau du cours, puis liés aux étapes ;
- auto-positionnement élève 0–3 et confirmation enseignante 0–3 ;
- vues de progression par étape, compétence et objectif ;
- historique enseignant des pages consultées, du temps actif et de la dernière visite, avec rétention d’un mois ;
- rewards configurables par cours, attribués à la confirmation et score cumulatif ;
- boîte de notifications alimentée lors d’une mise à jour ou validation ;
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

Ce découplage évite de ralentir une validation et permet de relancer les échecs.

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
```
