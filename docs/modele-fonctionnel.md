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

Une compétence ou un objectif peut être rattaché à plusieurs étapes. Pour une inscription donnée, l’application calcule :

- la moyenne des niveaux élève validés sur ces étapes ;
- la moyenne des niveaux enseignant confirmés ;
- le nombre de situations validées et confirmées ;
- le nombre total d’étapes mobilisant l’élément du référentiel.

L’affichage convertit une moyenne sur 3 en pourcentage pour la barre visuelle :

```text
pourcentage = niveau moyen / 3 × 100
```

La moyenne reste une synthèse de navigation, pas une règle institutionnelle définitive. Une future version pourra pondérer les étapes ou appliquer une règle du type « dernier niveau confirmé ».

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

L’écriture métier ne dépend donc pas du succès immédiat de `mail()`.
