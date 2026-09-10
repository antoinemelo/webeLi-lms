# Modèle fonctionnel et règles métier

## Concepts

```text
Utilisateur
 ├── enseigne un Cours
 └── rejoint un Cours par une Inscription

Page ── Blocs + Tags
  │
  └── Étape de parcours dans un Cours
        ├── position, échéance, évaluation, consigne
        ├── Objectifs du cours
        └── Compétences du cours
              │
              └── Progression par inscription
                    ├── auto-positionnement élève 0–3
                    ├── confirmation enseignante 0–3
                    └── Rewards attribués
```

## Séparation contenu / parcours

La `page` décrit la ressource elle-même. L’`étape de parcours` (`pathway_items`) décrit son usage dans un cours.

Cette séparation est structurante :

- une page peut être préparée sans être publiée dans un parcours ;
- une page peut être réutilisée dans plusieurs cours ;
- son ordre, son échéance et son caractère évaluatif peuvent varier ;
- les objectifs et compétences restent cohérents avec le référentiel propre au cours.

## Validation

La progression est unique pour le couple **inscription + étape**.

1. L’élève choisit un niveau 0–3, ajoute éventuellement une note et valide.
2. L’enseignant reçoit une notification en attente.
3. L’enseignant confirme son propre niveau 0–3 et peut ajouter un retour.
4. L’élève reçoit une notification de confirmation.

Si l’élève soumet à nouveau une étape, l’ancienne confirmation enseignante est effacée. Cela rend visible le fait qu’un nouveau travail doit être relu.

Les dates `student_validated_at` et `teacher_validated_at` portent l’état métier. Une valeur de niveau sans date de validation n’est pas considérée dans les synthèses.

## Acquisition des compétences et objectifs

Une compétence ou un objectif peut être rattaché à plusieurs étapes suivies. Pour une inscription donnée, l’application calcule :

- la moyenne des niveaux élève validés sur ces étapes ;
- une moyenne enseignante pondérée sur l’échelle 0–3 ;
- le nombre de situations validées et confirmées ;
- le nombre total d’étapes mobilisant l’élément du référentiel.

Une activité ordinaire contribue avec le niveau 0–3 confirmé par l’enseignant et un poids de `0,5`. Une évaluation contribue avec sa note convertie sur la même échelle et sa pondération configurée :

```text
niveau de l’évaluation = note / 10 × 3
moyenne = somme(niveau × poids) / somme(poids)
```

Une évaluation suivie reste incluse lorsqu’elle est masquée aux élèves. Une évaluation sans note et une activité sans confirmation sont omises du numérateur comme du dénominateur. Si une évaluation comporte aussi une autoévaluation, seule sa note officielle contribue à la moyenne enseignante.

L’affichage convertit une moyenne sur 3 en pourcentage pour la barre visuelle :

```text
pourcentage = niveau moyen / 3 × 100
```

La moyenne reste une synthèse de navigation, pas une règle institutionnelle définitive. Une future version pourra notamment appliquer une règle du type « dernier niveau confirmé ».

## Rewards

Les types de rewards appartiennent au cours et définissent un nom, une icône, une couleur et une valeur proposée. Lors d’une confirmation, l’enseignant peut attribuer une occurrence avec un nombre de points ajusté et un message.

Le score d’un élève dans un cours est la somme de toutes les occurrences :

```sql
SELECT SUM(points)
FROM reward_awards
WHERE enrollment_id = :inscription;
```

Les points ne modifient jamais le niveau d’une compétence ou la progression du parcours. Ils constituent uniquement un retour motivationnel.

## Notifications

Les actions métier insèrent un email dans `notification_outbox` :

| Événement | Destinataire | Déclencheur |
|---|---|---|
| `student.validated` | enseignant | validation ou nouvelle soumission élève |
| `teacher.confirmed` | élève | confirmation du niveau |
| `reward.awarded` | élève | attribution d’un reward |
| `page.updated` | élèves concernés | mise à jour d’une page présente dans leur cours |
| `course.announcement` | élèves destinataires et enseignant pour le récapitulatif d’un envoi groupé | annonce globale ou ciblée dans leur parcours |

L’écriture métier ne dépend donc pas du succès immédiat de `mail()`.

Les annonces ciblées conservent le contenu personnalisé pour chaque destinataire. Chaque élève reçoit un seul courriel, avec sa deuxième adresse en CC si elle a été sélectionnée. L’enseignant reçoit un seul récapitulatif du groupe, avec les variables originales et les destinataires ; il n’est pas ajouté systématiquement en CCI. Les modèles appartiennent à leur auteur et sont réutilisables dans tous ses parcours. Le corps des copies techniques de la file est effacé après envoi réussi ; date, destinataire, objet, copies et état restent conservés. Le contenu utile demeure dans l’annonce.

## Remises et suivi administratif

Un bloc **Travail à rendre** recueille un lien HTTP(S), un texte limité à 512 caractères, ou les deux. Un brouillon ne valide pas l’étape. La remise explicite verrouille la réponse ; l’enseignant peut autoriser une nouvelle remise, ce qui conserve la version précédente et retire la validation de l’étape. Les remises obligatoires et les QCM doivent être terminés avant la notation d’une évaluation qui les combine.

Une Réunion, une Correspondance ou un Paiement est un compte rendu administratif partagé entre un ou plusieurs élèves. Son enregistrement ne déclenche aucun courriel ni paiement. La fiche **Gestion de l’élève** rassemble les comptes rendus et annonces ciblées autorisés ; ses exports PDF et Markdown incluent tous leurs contenus. Ce suivi est distinct de la progression pédagogique.

## Discussions privées

L’activation par parcours ouvre les échanges élève–enseignant. Chaque fil relie un élève, un enseignant et un parcours ; ses messages sont limités à 256 caractères et modifiables durant trois minutes par leur auteur. Les responsables peuvent gérer les fils de leurs parcours ; le superadmin peut gérer ceux d’une personne sur tous les parcours. Les exports et effacements portent sur les fils autorisés, avec confirmation pour l’effacement. La base de discussions est séparée et ne fournit aucune entrée à l’historique administratif. Les [règles de discussion et de notification push](discussions.md) précisent les accès et la conservation.
