---
# Modèle d'offre d'emploi StudyVibe.
# Remplissez les valeurs après les deux-points, puis déposez ce fichier dans l'espace recrutement (Offres > Importer un fichier Markdown).
# Un aperçu liste tous les problèmes avant toute publication.
# Les lignes qui commencent par # dans cet en-tête, et tout ce qui est entre <!-- et -->, sont ignorés.

title: Ingénieur backend senior
# Une équipe parmi : engineering, education, product, sales, success, marketing, data, operations, people
# (les noms en français comme « Ingénierie » ou « Ventes et partenariats » sont aussi acceptés)
team: engineering
location: Douala, Cameroun
# onsite (sur site), hybrid (hybride) ou remote (à distance)
work_mode: hybride
# full_time (temps plein), part_time (temps partiel), internship (stage), contract (contrat), volunteer (bénévolat)
type: temps plein
level: Confirmé
openings: 1
# Dernier jour pour postuler, AAAA-MM-JJ (facultatif, dans le futur)
closes: 2026-12-31
salary: À discuter avec les candidats présélectionnés
# 4 lettres ou chiffres qui ouvrent la référence de chaque candidature (facultatif : généré à partir du titre)
code:
# open = publiée à l'approbation, draft (brouillon) = enregistrée en privé
status: open

# Lecture automatique des candidatures : on ou off.
screening: on
# Les candidatures sous ce score (10 à 80) sont mises de côté dans la section « Lecture auto » pour que vous les examiniez. Rien n'est refusé automatiquement.
screening_threshold: 35
# 0 = jamais. Sinon, une candidature écartée que personne n'a examinée reçoit un refus poli après ce nombre de jours.
auto_decline_days: 0
min_years: 5
# highschool, associate, bachelor, master, doctorate (vide = aucune exigence)
min_education: bachelor
---

## Résumé

Une ou deux phrases, de 20 à 400 caractères. C'est le texte de la liste des postes ouverts.

## Le poste

Écrivez des paragraphes séparés par une ligne vide. Dites ce que fait l'équipe, pourquoi le poste existe et à quoi
ressemble la réussite. 6 000 caractères au maximum.

## Missions

- Une mission concrète par ligne, qui commence par un verbe.
- Jusqu'à 25 lignes de 300 caractères.

## Profil recherché

- Ce que le candidat doit avoir. Une exigence par ligne.
- Soyez précis : « 5 ans de PHP », pas « bonne expérience ».

## Un plus

- Ce qui aide sans être obligatoire.

## Ce que nous offrons

- Ce que le poste apporte. Un avantage par ligne.

## Lecture automatique

### Indispensables

<!-- Un mot-clé par ligne. Un « ! » devant = condition éliminatoire. « a|b » = l'un ou l'autre suffit.
     Laissez vide : les mots-clés sont déduits du profil recherché. -->

- !php
- mysql|mariadb

### Atouts

- docker
