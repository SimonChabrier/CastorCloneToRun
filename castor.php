<?php

use Castor\Attribute\AsTask;
use function Castor\io;

#[AsTask(description: 'Cloner un dépôt, configurer et lancer un projet Symfony Edith avec serveurs et navigation')]
function cloneProject(): void
{
    io()->title('Initialisation du projet Symfony Edith');

    $currentDir = getcwd();
    io()->text("Dossier actuel : $currentDir");

    // Étape 1 : Clonage du dépôt Git
    $repoUrl = io()->ask('Entrez l\'URL ou le SSH du dépôt Git');
    $projectName = io()->ask('Entrez le nom du dossier pour le projet', 'project');
    $projectPath = $currentDir . DIRECTORY_SEPARATOR . $projectName;

    if (is_dir($projectPath)) {
        io()->error("Le dossier '$projectName' existe déjà. Supprimez-le ou choisissez un autre nom.");
        return;
    }

    io()->section('Clonage du dépôt...');
    exec("git clone $repoUrl $projectName", $output, $returnVar);

    if ($returnVar === 0) {
        io()->success("Dépôt cloné avec succès dans '$projectName'.");
        chdir($projectPath);
    } else {
        io()->error('Échec du clonage du dépôt. Vérifiez l\'URL et réessayez.');
        return;
    }

    // Étape 2 : Copier le fichier .env et le renommer en .env.local
    io()->section('Configuration du fichier .env.local...');
    if (file_exists('.env')) {
        copy('.env', '.env.local');
        io()->success('Fichier .env.local créé à partir de .env.');

        // Demander le type de base de données
        $dbType = io()->choice(
            'Quel type de base de données utilisez-vous ?',
            ['mysql', 'mariadb', 'sqlite'],
            'mysql'
        );

        if ($dbType === 'sqlite') {
            $dbPath = io()->ask('Entrez le chemin du fichier SQLite', '%kernel.project_dir%/var/data.db');
            $dbDsn = "DATABASE_URL=\"sqlite:///$dbPath\"";
        } else {
            // MySQL ou MariaDB
            $dbUser = io()->ask('Entrez l\'identifiant de connexion la base de données', 'root');
            $dbPassword = io()->ask('Entrez le mot de passe de connexion de la base de données', 'root');
            $dbHost = io()->ask('Entrez l\'hôte de la base de données', 'localhost');
            $dbPort = io()->ask('Entrez le port de connexion à la base de données', '3306');
            $dbName = io()->ask('Entrez le nom de la base de données qui va être crée', 'symfony_project');

            // Fonction pour vérifier l'existence de la base de données
            function checkDatabaseExists($dbUser, $dbPassword, $dbHost, $dbPort, $dbName)
            {
                $dbExists = exec("mysql -u $dbUser -p$dbPassword -h $dbHost -P $dbPort -e \"SHOW DATABASES LIKE '$dbName'\" | grep $dbName");
                return !empty($dbExists);
            }

            // Vérification de l'existence de la base de données
            while (checkDatabaseExists($dbUser, $dbPassword, $dbHost, $dbPort, $dbName)) {
                io()->warning("La base de données '$dbName' existe déjà. Veuillez choisir un autre nom.");
                $dbName = io()->ask('Entrez un autre nom de base de données', 'symfony_project');
            }

            // Une fois un nom valide, on génère la chaîne DSN
            $dbDsn = "DATABASE_URL=\"$dbType://$dbUser:$dbPassword@$dbHost:$dbPort/$dbName\"";
        }

        // Mise à jour du fichier .env.local
        $envContent = file_get_contents('.env.local');
        $envContent = preg_replace('/^DATABASE_URL=.*$/m', $dbDsn, $envContent);
        file_put_contents('.env.local', $envContent);

        io()->success('Variables d\'environnement personnalisées.');
    } else {
        io()->error('Fichier .env introuvable. Impossible de créer .env.local.');
        return;
    }

    // Étape 3 : Installation des dépendances avec Composer
    io()->section('Installation des dépendances Composer...');
    exec('composer install', $output, $returnVar);
    if ($returnVar === 0) {
        io()->success('Dépendances Composer installées.');
    } else {
        io()->error('Échec de l\'installation des dépendances Composer.');
        return;
    }

    // Étape 4 : Installation des dépendances NPM
    if (file_exists('package.json')) {
        io()->section('Installation des dépendances NPM...');
        exec('npm install', $output, $returnVar);
        if ($returnVar === 0) {
            io()->success('Dépendances NPM installées.');
        } else {
            io()->error('Échec de l\'installation des dépendances NPM.');
            return;
        }
    } else {
        io()->success('Aucun fichier package.json trouvé, installation NPM ignorée.');
    }

    // Étape 6 : Création de la base de données
    io()->section('Création de la base de données...');
    exec('php bin/console doctrine:database:create', $output, $returnVar);
    if ($returnVar === 0) {
        io()->success('Base de données créée.');
    } else {
        io()->error('Échec de la création de la base de données.');
        return;
    }

    // Étape 7 : Mise à jour du schéma de la base de données
    io()->section('Mise à jour du schéma Doctrine...');
    exec('php bin/console doctrine:schema:update --force --complete', $output, $returnVar);
    if ($returnVar === 0) {
        io()->success('Schéma Doctrine mis à jour.');
    } else {
        io()->error('Échec de la mise à jour du schéma Doctrine.');
        return;
    }

    // Étape 8 : Vérification des droits sur le dossier public
    io()->section('Vérification des droits sur le dossier public...');
    if (!is_writable('public')) {
        io()->warning('Le dossier public n\'a pas les permissions d\'écriture nécessaires. Vérifiez les droits.');
        // on demande si on veut changer les droits
        $changeRights = io()->confirm('Voulez-vous changer les droits du dossier public ?', false);
        if ($changeRights) {
            exec('chmod -R 755 public', $output, $returnVar); // 755 = rwxr-xr-x donc tout le monde peut lire et exécuter
            if ($returnVar === 0) {
                io()->success('Droits sur le dossier public modifiés.');
            } else {
                io()->error('Échec de la modification des droits sur le dossier public.');
            }
        }
    } else {
        io()->success('Droits sur le dossier public vérifiés.');
    }

    // demander si on veur lancer les serveurs et ouvrir le navigateur
    $launchServers = io()->confirm('Voulez-vous lancer les serveurs Symfony et ouvrir le navigateur maintenant ?', true);
    if ($launchServers) {
        runProject(); // appel de runProject pour lancer les serveurs
        sleep(5); // Petite pause pour laisser le temps aux serveurs de démarrer
        openProject(); // appel de openProject pour ouvrir le navigateur
    } else {
        io()->text('Vous pouvez lancer le serveur de développement avec la commande "php castor.php runProject"');
        io()->text('Vous pouvez ouvrir le navigateur avec la commande "php castor.php openProject"');
    }

    // demander si on veur supprimer le .git et initialiser un nouveau dépôt
    $resetGit = io()->confirm('Voulez-vous supprimer le dépôt Git et initialiser un nouveau dépôt ?', false);
    if ($resetGit) {
        resetProject(); // appel de resetProject pour supprimer le .git et initialiser un nouveau dépôt
    }

    io()->success('Initialisation du projet terminée.');
}

#[AsTask(description: 'Lancer les serveurs Symfony et de surveillance des assets')]
function runProject(): void
{
    // Vérifier le système d'exploitation
    $isMac = strtoupper(substr(PHP_OS, 0, 3)) === 'DAR';  // macOS
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';  // Windows
    $isLinux = strtolower(PHP_OS) === 'linux';  // Linux

    // Étape 9 : Lancer le serveur Symfony
    io()->section('Démarrage du serveur Symfony...');
    if ($isMac) {
        // macOS : utiliser osascript pour ouvrir un terminal
        exec('osascript -e \'tell application "Terminal" to do script "cd ' . getcwd() . ' && symfony server:start; exit"\'', $output, $returnVar);
    } elseif ($isWindows) {
        // Windows : utiliser start pour ouvrir un nouveau terminal (cmd)
        exec('start cmd.exe /K "cd /d ' . getcwd() . ' && symfony server:start && exit"', $output, $returnVar);
    } elseif ($isLinux) {
        // Linux : essayer d'ouvrir un terminal avec gnome-terminal ou xterm
        exec('gnome-terminal -- bash -c "cd ' . getcwd() . ' && symfony server:start; exec bash"', $output, $returnVar);
        if ($returnVar !== 0) {
            // Si gnome-terminal échoue, essayer avec xterm
            exec('xterm -e "cd ' . getcwd() . ' && symfony server:start; exec bash"', $output, $returnVar);
        }
    }

    if ($returnVar === 0) {
        io()->success('Serveur Symfony démarré dans un nouveau terminal.');
    } else {
        io()->error('Échec du démarrage du serveur Symfony.');
        return;
    }

    // Petite pause pour s'assurer que le terminal est bien ouvert
    sleep(2);

    // Étape 10 : Monitorer les assets
    io()->section('Démarrage de la surveillance des assets...');
    if ($isMac) {
        // macOS : utiliser osascript pour ouvrir un terminal
        exec('osascript -e \'tell application "Terminal" to do script "cd ' . getcwd() . ' && npm run watch; exit"\'', $output, $returnVar);
    } elseif ($isWindows) {
        // Windows : utiliser start pour ouvrir un nouveau terminal (cmd)
        exec('start cmd.exe /K "cd /d ' . getcwd() . ' && npm run watch && exit"', $output, $returnVar);
    } elseif ($isLinux) {
        // Linux : essayer d'ouvrir un terminal avec gnome-terminal ou xterm
        exec('gnome-terminal -- bash -c "cd ' . getcwd() . ' && npm run watch; exec bash"', $output, $returnVar);
        if ($returnVar !== 0) {
            // Si gnome-terminal échoue, essayer avec xterm
            exec('xterm -e "cd ' . getcwd() . ' && npm run watch; exec bash"', $output, $returnVar);
        }
    }

    if ($returnVar === 0) {
        io()->success('Surveillance des assets activée dans un nouveau terminal.');
    } else {
        io()->error('Échec de la surveillance des assets.');
    }

    // Petite pause pour s'assurer que le terminal est bien ouvert
    sleep(2);
}

#[AsTask(description: 'Lancer le navigateur pour ouvrir le projet')]
function openProject(): void
{
    io()->title('Ouverture du projet Symfony Edith');

    // Vérifier le système d'exploitation
    $isMac = strtoupper(substr(PHP_OS, 0, 3)) === 'DAR';  // macOS
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';  // Windows
    $isLinux = strtolower(PHP_OS) === 'linux';  // Linux

    if ($isMac) {
        // macOS : utiliser open pour ouvrir le navigateur par défaut
        exec('open http://localhost:8000');
    } elseif ($isWindows) {
        // Windows : utiliser start pour ouvrir le navigateur par défaut
        exec('start http://localhost:8000');
    } elseif ($isLinux) {
        // Linux : essayer d'ouvrir le navigateur par défaut
        exec('xdg-open http://localhost:8000');
    }

    io()->success('Navigateur ouvert sur http://localhost:8000.');
}

// commande pour supprimer le .git et initialiser un nouveau dépôt
#[AsTask(description: 'Supprimer le dépôt Git et initialiser un nouveau dépôt')]
function resetProject(): void
{
    io()->title('Réinitialisation du projet Symfony Edith');

    // Vérifier si le dossier .git existe
    if (is_dir('.git')) {
        // Supprimer le dossier .git
        io()->section('Suppression du dépôt Git existant...');
        exec('rm -rf .git', $output, $returnVar);
        if ($returnVar === 0) {
            io()->success('Dépôt Git supprimé.');
        } else {
            io()->error('Échec de la suppression du dépôt Git.');
            return;
        }

        // Initialiser un nouveau dépôt Git
        io()->section('Initialisation d\'un nouveau dépôt Git...');
        exec('git init', $output, $returnVar);
        if ($returnVar === 0) {
            io()->success('Nouveau dépôt Git initialisé.');
        } else {
            io()->error('Échec de l\'initialisation du dépôt Git.');
            return;
        }
    } else {
        io()->warning('Aucun dépôt Git trouvé. Aucune action nécessaire.');
    }
}
