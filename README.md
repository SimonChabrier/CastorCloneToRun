# TaskRunner Script
## From GitClone to Symfony Server Launch in One Command

## 🇫🇷 
### ...en passant par la configuration de la base de données, ce script d'automatisation simplifie la configuration d'un projet Symfony en automatisant plusieurs tâches.

- Exécuter la commande : `castor clone-project`

### Retourne les étapes suivantes :

- Demande si on veut cloner dans le dossier local ou remonter d'un niveau.
- Demande l'url ou ssh du dépôt Git du projet Symfony.
- Clone le dépôt Git du projet Symfony dans le dossier choisi. 
- Stope le script si le dossier existe déjà.
- Confirme le clonage du dépôt Git.
- Crée le fichier `.env.local` à partir du fichier `.env`
- Installe les dépendances via Composer et Node.js.
- Demande le driver de la base de données (MySQL, SQLite).
- Si Sqlite, crée le fichier de base de données dans `%kernel.project_dir%/var/data.db`
- Demandes les informatios de connexion à la base de données.
- Vérifie si la BDD existe déjà et si oui demande un autre nom tant que la BDD existe.
- Configure la connexion à la base de donnée dasn le fichier `.env.local`
- Demande si on veut installer les dépendances avec Composer.
- Si un `package.json` existe, demande si on veut installer les dépendances avec NPM.
- Crée la base de données et les tables avec Doctrine.
- Vérifie les doits d'écriture sur le dossier public et les ajuste si nécessaire. (755)
- Demande si on veut lancer les serveurs Symfony et de surveillance des assets.
- Vérifie si un serveur Symfony est déjà en cours d'exécution et le stoppe si nécessaire.
- Ouvre le sterminaux correspondant au lancement du serveur et à la surveillance des assets.
- Ouvre le navigateur par défaut positionné sur le projet `https://127.0.0.0.1:8000`
- Demande si on souhaite conserver ou réinitialiser le dépôt Git du projet.
- Si réinitialisation, supprime le dossier `.git` et initialise un nouveau dépôt Git.

L'objectif est de faciliter la mise en place d'un environnement de développement Symfony en quelques étapes simples.

## 🇬🇧
### From Git clone to Symfony server launch via database configuration, this automation script simplifies the setup of a Symfony project by automating several tasks.

- Execute the command: `castor clone-project`

### Returns the following steps:

- Asks whether to clone in the local folder or move up one level.
- Requests the URL or SSH of the Symfony project Git repository.
- Clones the Symfony project Git repository into the chosen folder.
- Stops the script if the folder already exists.
- Confirms the cloning of the Git repository.
- Creates the `.env.local` file from the `.env` file.
- Installs dependencies via Composer and Node.js.
- Asks for the database driver (MySQL, SQLite).
- If SQLite, creates the database file in `%kernel.project_dir%/var/data.db`.
- Requests database connection information.
- Checks if the database already exists and, if so, asks for a new name until a non-existing database name is provided.
- Configures the database connection in the `.env.local` file.
- Asks whether to install dependencies with Composer.
- If a `package.json` file exists, asks whether to install dependencies with NPM.
- Creates the database and tables with Doctrine.
- Verifies write permissions on the public folder and adjusts them if necessary (755).
- Asks whether to launch the Symfony servers and asset monitoring.
- Checks if a Symfony server is already running and stops it if necessary.
- Opens terminals for the server and asset monitoring processes.
- Opens the default browser pointing to the project at `https://127.0.0.1:8000`.
- Asks whether to keep or reset the project's Git repository.
- If reset is chosen, deletes the `.git` folder and initializes a new Git repository.


## Castor

Castor est un outil de génération de scripts d'automatisation pour les projets PHP. Il permet de créer des scripts d'installation, de configuration, de déploiement, de maintenance, etc. pour vos projets PHP. Castor est développé par [JoliCode](https://castor.jolicode.com/).

Castor is a task automation script generator for PHP projects. It allows you to create installation, configuration, deployment, maintenance, etc. scripts for your PHP projects. Castor is developed by [JoliCode](https://castor.jolicode.com/).

## Compatibilité / Compatibility
***Windows, Linux, MacOS***

## Commandes disponibles
- `castor clone-project` : commande complète pour cloner un projet Symfony.
- `castor open-project` : commande pour ouvrir le navigateur à l'adresse https://127.0.0.1:8000
- `castor reset-project` : supprime le dossier `.git` et initialise un nouveau dépôt Git.

## Available Commands
- `castor clone-project`: complete command to clone a Symfony project.
- `castor open-project`: command to open the browser at the address https://127.0.0.1:8000.
- `castor reset-project`: deletes the `.git` folder and initializes a new Git repository.


