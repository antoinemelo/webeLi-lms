<?php
declare(strict_types=1);

return [
    'audience'=>'Apprenants', 'title'=>'Mon guide liike',
    'subtitle'=>'Trouver mon travail, avancer à mon rythme et comprendre mes progrès.',
    'pages'=>[
        [
            'id'=>'premiers-pas', 'title'=>'Mes premiers pas',
            'lead'=>'liike rassemble tes activités, tes travaux et tes acquis. Commence par rejoindre le bon parcours : tu retrouveras ensuite tout ton travail au même endroit.',
            'cards'=>[
                ['title'=>'1. Activer mon accès', 'html'=>'<p>Ouvre le lien d’invitation de ton enseignant ou utilise le code du parcours. Si tu crées ton compte, renseigne ton prénom, ton nom, ton courriel et ton groupe classe, puis valide le lien reçu par courriel dans les <strong>15 minutes</strong>.</p><p>Pour te connecter, utilise ton <strong>code personnel d’apprenant</strong>. Il est différent du code qui sert à rejoindre un parcours. Si ton enseignant a déjà créé ton accès, suis les indications qu’il te transmet.</p>'],
                ['title'=>'2. Me repérer', 'html'=>'<ul><li><strong>Parcours</strong> : les étapes et les activités à réaliser.</li><li><strong>Acquis</strong> : les compétences, les objectifs et les évaluations.</li><li><strong>Encouragements</strong> : les points et les messages reçus pour tes efforts.</li></ul><p>Sur téléphone, ces rubriques sont en bas de l’écran. Si tu suis plusieurs parcours, choisis celui qui t’intéresse dans les boutons en haut de la page ; le menu à trois points donne accès aux suivants.</p>'],
                ['title'=>'3. Retrouver mon compte', 'html'=>'<p>Touche tes initiales pour ouvrir le menu du profil. Tu peux y modifier tes informations, rejoindre un autre cours et retrouver ce guide. <strong>Ton numéro de téléphone se modifie uniquement depuis ton propre profil.</strong></p><p>L’icône de discussion ouvre les échanges autorisés. La cloche signale les annonces à lire.</p>'],
            ],
            'tip_title'=>'Sur mon téléphone', 'tip'=>'Tu peux ajouter liike à l’écran d’accueil avec les commandes de ton navigateur. Dans l’application installée, l’option de connexion mémorisée permet de retrouver ton compte à la réouverture, avec une connexion Internet. Sur un appareil partagé, décoche cette option et déconnecte-toi après utilisation.',
        ],
        [
            'id'=>'mon-parcours', 'title'=>'Avancer dans mon parcours',
            'lead'=>'Chaque étape te donne un objectif et une activité. Les regroupements organisent ces étapes en thèmes ou en séquences.',
            'cards'=>[
                ['title'=>'Choisir la prochaine activité', 'html'=>'<p>Le grand bouton du parcours ouvre en priorité l’étape accessible dont la <strong>date future est la plus proche</strong>. S’il n’y en a pas, il ouvre la première étape qui reste à réaliser. Tu peux aussi choisir une autre étape dans la vue d’ensemble.</p><p>Le titre, la durée prévue et les éventuelles dates t’aident à t’organiser. Le chevron d’un regroupement affiche ou masque ses étapes. Tu ne vois que les activités auxquelles ton enseignant t’a donné accès.</p>'],
                ['title'=>'Lire, explorer, réaliser', 'html'=>'<p>Une étape peut proposer du texte, une image, un document à télécharger, une vidéo, un son, une activité externe, un QCM ou un travail à rendre. Lis la consigne avant de commencer et vérifie les objectifs affichés.</p><p>Quand le bouton <strong>Télécharger le PDF</strong> est proposé, tu peux conserver une version du contenu. Certaines activités interactives demandent de revenir dans l’application pour répondre.</p>'],
                ['title'=>'Comprendre les états', 'html'=>'<ul><li><strong>À faire</strong> : l’étape reste à réaliser.</li><li><strong>Envoyé / à confirmer</strong> : ton travail ou ton autoévaluation attend le retour de l’enseignant.</li><li><strong>Confirmé</strong> : le suivi de cette étape est validé.</li></ul><p>Selon les réglages, une simple consultation suffit, une autoévaluation est demandée ou un travail doit être remis. Suis les boutons proposés en fin d’activité.</p>'],
            ],
            'tip_title'=>'Ce qui a changé', 'tip'=>'L’encadré « Depuis la dernière visite » signale les nouveautés du parcours, les acquis mis à jour et les encouragements reçus. Une activité absente ou fermée peut simplement être prévue pour plus tard : demande à ton enseignant si tu as un doute.',
        ],
        [
            'id'=>'repondre-et-rendre', 'title'=>'Répondre et rendre mon travail',
            'lead'=>'Un brouillon sert à préparer une réponse. Une remise sert à la transmettre : prends le temps de vérifier ce que tu envoies.',
            'cards'=>[
                ['title'=>'Faire un QCM', 'html'=>'<p>Lis les questions et les consignes : plusieurs réponses peuvent être attendues. Un QCM d’entraînement te permet de vérifier tes réponses. Un QCM strict demande une remise explicite ; respecte les indications affichées avant de valider.</p><p>Pour une séance en classe, attends que l’enseignant ouvre la question. Réponds puis valide ton choix ; les résultats et les corrections apparaissent au moment prévu par l’enseignant.</p>'],
                ['title'=>'Remettre un lien ou un texte', 'html'=>'<ol><li>Ouvre le bloc <strong>Travail à rendre</strong> et lis la consigne.</li><li>Ajoute le lien, le texte court ou le commentaire demandé.</li><li>Utilise le brouillon pour reprendre ton travail plus tard.</li><li>Choisis <strong>Rendre mon travail</strong> et confirme l’envoi.</li></ol><p>Après la remise, les champs sont verrouillés. Si tu dois corriger ton travail, demande à ton enseignant d’autoriser une nouvelle remise. Pour un document externe, vérifie qu’il pourra ouvrir ton lien.</p>'],
                ['title'=>'M’autoévaluer honnêtement', 'html'=>'<p>Si l’étape demande une autoévaluation, choisis le niveau qui décrit le mieux ce que tu sais faire :</p><ul><li><strong>0 · Je découvre</strong> : j’ai besoin d’aide.</li><li><strong>1 · Je commence</strong> : je réussis avec un modèle.</li><li><strong>2 · Je maîtrise</strong> : je réussis seul·e.</li><li><strong>3 · Je peux expliquer</strong> : je peux aider une autre personne.</li></ul><p>Ajoute un commentaire si nécessaire, puis envoie. Un nouvel envoi remplace le précédent et remet la confirmation enseignante en attente.</p>'],
            ],
            'tip_title'=>'Un envoi est-il terminé ?', 'tip'=>'Attends le message de réussite ou le changement d’état. Si le réseau est coupé, garde la page ouverte et réessaie une fois la connexion revenue. Un brouillon enregistré ne signifie pas que le travail a été rendu.',
        ],
        [
            'id'=>'mes-progres', 'title'=>'Comprendre mes progrès',
            'lead'=>'Ta progression, tes acquis et tes encouragements donnent des informations complémentaires sur ton apprentissage.',
            'cards'=>[
                ['title'=>'Lire mes acquis', 'html'=>'<p>Dans <strong>Acquis</strong>, consulte les compétences, les objectifs et les évaluations du parcours choisi. Les niveaux que tu as déclarés sont distingués des confirmations de l’enseignant. Les évaluations peuvent recevoir une note sur 10 et une pondération.</p><p>Un thème ou une évaluation accessible permet de retrouver l’étape correspondante dans le parcours. Depuis le parcours, tu peux revenir à tes acquis pour relire les résultats et commentaires.</p><p>Une case sans résultat ne signifie pas zéro. Lis aussi les retours de l’enseignant : ils t’indiquent quoi retravailler.</p>'],
                ['title'=>'Échanger et recevoir des encouragements', 'html'=>'<p>Les <strong>Encouragements</strong> valorisent tes efforts et tes réussites. Leurs points sont distincts des notes d’évaluation.</p><p>Si les discussions sont autorisées dans ton parcours, ouvre l’icône de discussion pour contacter les personnes proposées. Les messages sont courts ; les annonces de l’enseignant se consultent aussi depuis la cloche. Les notifications du téléphone dépendent de tes autorisations.</p>'],
                ['title'=>'Trouver de l’aide', 'html'=>'<ul><li><strong>Je ne vois pas mon parcours :</strong> vérifie le parcours sélectionné et ton inscription.</li><li><strong>Je ne retrouve pas mon code :</strong> utilise l’aide à la connexion ou demande-le à ton enseignant.</li><li><strong>Mon lien d’activation a expiré :</strong> reprends l’inscription ou contacte ton enseignant.</li><li><strong>Je ne peux plus modifier une remise :</strong> demande sa réouverture à ton enseignant.</li><li><strong>Une activité externe ne s’ouvre pas :</strong> vérifie le réseau et signale le problème avec le titre de l’activité.</li></ul>'],
            ],
            'tip_title'=>'Participer sans compte', 'tip'=>'Pour un QCM anonyme, ton enseignant peut afficher un QR code ou un lien de séance. Rejoins la séance sans donner de nom ni créer de compte. Les réponses restent temporaires et anonymes : elles ne deviennent pas des notes dans ton parcours.',
        ],
    ],
];
