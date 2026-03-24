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
3. Mettre le schéma SQL au niveau des entités (pas de migrations versionnées dans ce projet) :
   `php bin/console doctrine:schema:update --force`  
   En production, préfère appliquer le SQL généré par `doctrine:schema:update --dump-sql` après relecture.
4. Charger les menus de test :
   `php bin/console doctrine:fixtures:load`
5. Démarrer le serveur :
   `symfony serve`

## Déploiement Render / Docker

- **Migrations** : ce projet ne lance plus de migrations au démarrage. Si les logs Render affichent encore `doctrine:migrations:migrate`, tu déploies une **ancienne révision** ou une **Start Command** personnalisée qui force cette commande : vide-la pour utiliser le `CMD` du `Dockerfile`, ou retire la ligne `migrate`.
- **Health check** : configure le chemin **`/healthz`** (léger, sans base obligatoire pour la réponse). Cela limite les *Timed Out* si `/` est lent.
- **Routeur Symfony** : le `Dockerfile` utilise `php -S … -t public public/index.php`. Sans `public/index.php`, les URLs `/api/...` renvoient 404 et le health check échoue.
- **Avertissement Doctrine (MySQL avant la v8)** : ta base est vue comme une version ancienne ; planifie une montée vers **MySQL 8+** chez ton hébergeur (recommandé avant DBAL 5).

## Comptes

Les utilisateurs peuvent s’inscrire via le formulaire d’inscription.
Les menus de test sont chargés via les fixtures.

## NoSQL (MongoDB)

MongoDB est utilisé pour stocker des événements liés aux commandes (création, changement de statut, annulation).
Objectif : traçabilité/statistiques indépendantes de la base relationnelle MySQL.
Les données sont visibles via MongoDB Compass et via la route admin /admin/nosql.
