# Script d'Automatisation de Projet Symfony avec Castor / Symfony Project Automation Script with Castor

## French Description
Ce script d'automatisation, développé avec [Castor](https://castor.jolicode.com/), simplifie la configuration d'un projet Symfony en automatisant plusieurs tâches. Il permet de :

- Clone un dépôt Git d'un projet Symfony.
- Configure le fichier `.env.local` en fonction des préférences de base de données (MySQL, MariaDB, ou SQLite).
- Installe les dépendances via Composer et Node.js.
- Crée et configurer la base de données avec Doctrine.
- Vérifie et modifier les droits d'écriture sur le dossier public.
- Lance les serveurs Symfony et de surveillance des assets.
- Demande si on souhaite conserver ou réinitialiser le dépôt Git du projet.

L'objectif est de faciliter la mise en place d'un environnement de développement Symfony en quelques étapes simples.

## English Description
This automation script, developed with [Castor](https://castor.jolicode.com/), simplifies the setup of a Symfony project by automating several tasks. It allows you to:

- Clone a Git repository of a Symfony project.
- Configure the `.env.local` file according to the preferred database type (MySQL, MariaDB, or SQLite).
- Install dependencies via Composer and Node.js.
- Create and configure the database using Doctrine.
- Check and modify write permissions on the public directory.
- Launch Symfony servers and asset watchers.
- Ask if you want to keep or reset the Git repository of the project.

The goal is to streamline the setup of a Symfony development environment in just a few simple steps.

## Castor

Castor est un outil de génération de scripts d'automatisation pour les projets PHP. Il permet de créer des scripts d'installation, de configuration, de déploiement, de maintenance, etc. pour vos projets PHP. Castor est développé par [JoliCode](https://jolicode.com/).

Castor is a task automation script generator for PHP projects. It allows you to create installation, configuration, deployment, maintenance, etc. scripts for your PHP projects. Castor is developed by [JoliCode](https://jolicode.com/).
