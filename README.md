# Vite & Gourmand – Projet ECF

## Présentation

Ce projet est une application web réalisée avec Symfony dans le cadre de l’ECF
Développeur Web et Web Mobile.

L’objectif est de permettre à un client de consulter des menus traiteur et de
passer une commande en ligne après inscription.

## Fonctionnalités principales

- Inscription et connexion des utilisateurs
- Consultation de la liste des menus
- Détail d’un menu avec ses conditions
- Création d’une commande avec calcul du prix
- Consultation des commandes de l’utilisateur connecté

## Règles de gestion

- Le nombre de personnes doit être supérieur ou égal au minimum du menu
- Une remise de 10% est appliquée si le minimum est dépassé de 5 personnes
- La livraison est gratuite pour Bordeaux
- Hors Bordeaux, la livraison coûte 5€ + 0,59€ par kilomètre

## Technologies utilisées

- Symfony 8
- PHP 8
- MySQL
- Twig
- Doctrine ORM

## Lancement du projet

1. Configurer la base de données dans le fichier `.env`
2. Créer la base :
   `php bin/console doctrine:database:create`
3. Lancer les migrations :
   `php bin/console doctrine:migrations:migrate`
4. Charger les menus de test :
   `php bin/console doctrine:fixtures:load`
5. Démarrer le serveur :
   `symfony serve`

## Comptes

Les utilisateurs peuvent s’inscrire via le formulaire d’inscription.
Les menus de test sont chargés via les fixtures.

## NoSQL (MongoDB)

MongoDB est utilisé pour stocker des événements liés aux commandes (création, changement de statut, annulation).
Objectif : traçabilité/statistiques indépendantes de la base relationnelle MySQL.
Les données sont visibles via MongoDB Compass et via la route admin /admin/nosql.
