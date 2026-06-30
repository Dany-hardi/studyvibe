# Guide d'Utilisation : Système de Télé-Évaluation Synchrone (QuizBox)

J'ai conçu et intégré un module de **télé-évaluation synchrone (QuizBox)** au sein de StudyVibe. Ce guide détaille les étapes que je suis pour configurer, animer et envoyer les résultats d'un quiz en temps réel à mes étudiants, tout en garantissant des performances optimales et une synchronisation parfaite sur tous les appareils.

---

## 1. Préparation de la Séance (Mon espace Enseignant)

Pour lancer une évaluation synchrone, je commence par la configurer sur mon tableau de bord :

1. **Création de la session** : Je définis le titre de l'évaluation et l'associe à l'un de mes cours existants.
2. **Planification temporelle** :
   * Je configure précisément la **date et l'heure de début** (l'instant exact où le quiz s'ouvrira pour tous les étudiants).
   * Je règle la **durée par défaut** de chaque question (par exemple, 30 secondes).
3. **Importation des questions** : Je charge mes questions de type QCM (options A, B, C, D) et je valide la bonne réponse pour chacune d'elles.
4. **Génération du Code unique** : Dès que la session est créée, le système génère un **code d'accès unique** (ex: `2c6641815af1`). C'est ce code que je partage avec mon groupe d'étudiants (par exemple sur WhatsApp, Teams ou directement au tableau).

---

## 2. L'entrée dans la salle d'attente (Le Lobby)

Avant l'heure de début définie, les étudiants se préparent à rejoindre l'évaluation :

1. **Connexion** : Les étudiants se rendent sur mon site StudyVibe et cliquent sur le lien d'accès direct ou saisissent le code.
2. **Identification** : Ils s'inscrivent avec leur **nom complet** et leur **adresse e-mail** (très important pour la réception future de leur correction).
3. **Attente dynamique** : 
   * Ils sont placés dans un salon d'attente (Lobby) avec un compte à rebours visuel qui s'aligne sur l'heure de début configurée.
   * Mon système met à jour en temps réel le compteur des étudiants connectés dans le lobby.

---

## 3. Le déroulement synchronisé du Quiz (Live Sync)

Dès que le compte à rebours atteint zéro, le quiz démarre **automatiquement** et en même temps pour tout le monde.

* **Aucune action manuelle requise** : Je n'ai pas besoin de cliquer sur "Suivant" pour changer de question. Le serveur calcule à la seconde près quelle question doit être affichée en fonction de l'heure actuelle.
* **Résilience au freeze** : Si un étudiant perd sa connexion ou rafraîchit sa page par inadvertance, il est automatiquement réaligné sur la question en cours avec le bon nombre de secondes restantes.
* **Soumission unique** : L'étudiant sélectionne son option (A, B, C ou D). Une fois l'option choisie, la réponse est verrouillée et enregistrée dans ma base de données. Il attend ensuite la fin du chronomètre pour passer à la question suivante.

---

## 4. Clôture de l'évaluation et Envoi des Résultats

Une fois que le temps imparti pour la dernière question est écoulé, la session passe en statut **Terminé** pour tous les étudiants. C'est à ce moment que j'interviens pour distribuer les corrections.

1. **Accès au tableau de bord** : Je me rends sur mon espace enseignant et je clique sur la session de télé-évaluation terminée.
2. **Vérification des statistiques** : Je peux voir en un coup d'œil le taux de participation, la note moyenne de la classe (calculée automatiquement sous forme de note sur le total des questions, ex: `14/20` plutôt qu'un pourcentage brut), ainsi que le statut général.
3. **Dispatch des e-mails** : 
   * Je clique sur le bouton **"Approuver et Envoyer"** (ou *Envoyer les e-mails de correction*).
   * Mon système traite les envois de manière **asynchrone par petits lots** pour éviter de saturer ma passerelle SMTP (Gmail).
   * Une barre de progression m'affiche l'avancement exact (ex: `12 / 50 e-mails envoyés`).
   * Chaque étudiant reçoit immédiatement dans sa boîte de réception son score final, le récapitulatif de ses réponses et la correction détaillée de chaque question.
