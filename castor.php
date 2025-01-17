<?php

use Castor\Attribute\AsTask;
use function Castor\io;

#[AsTask(description: 'Cloner un dépôt, configure et lance un projet Symfony avec serveurs et navigation')]
function cloneProject(): void
{
    io()->title('Initialisation du projet Symfony');

    $currentDir = getcwd();
    io()->text("Dossier actuel : $currentDir");

    // demander si on veut remonter d'un niveau pour cloner le dépôt hors du dossier courant
    $upLevel = io()->confirm('Voulez-vous cloner le dépôt dans un dossier parent ?', true);
    if ($upLevel) {
        chdir('..');
        $currentDir = getcwd();
        io()->text("Nouveau dossier actuel : $currentDir");
    }

    // Étape 1 : Clonage du dépôt Git
    $repoUrl = io()->ask('Entrez l\'URL ou le SSH du dépôt Git');
    $projectName = io()->ask('Entrez le nom du dossier pour le projet', 'project');
    $projectPath = $currentDir . DIRECTORY_SEPARATOR . $projectName;

    if (is_dir($projectPath)) {
        io()->error("Le dossier '$projectName' existe déjà. Supprimez-le ou choisissez un autre nom.");
        return;
    }

    io()->section('On clone le dépôt Git...');
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
            ['mysql', 'sqlite'],
            'mysql'
        );

        if ($dbType === 'sqlite') {
            $dbName = io()->ask('Entrez le nom du fichier de la base de donnée SqLite', 'data.db');
            // # DATABASE_URL="sqlite:///%kernel.project_dir%/var/app.db"
            $dbDsn = "DATABASE_URL=\"sqlite:///%kernel.project_dir%/var/$dbName\"";
            $dbPath = 'var' . DIRECTORY_SEPARATOR . $dbName;
            // si le fichier n'existe pas on le crée avec un fichier vide
            if (!file_exists($dbPath)) {
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                touch($dbPath);
            }
        } else {
            // MySQL ou MariaDB
            $dbUser = io()->ask('Entrez l\'identifiant de connexion la base de données', 'root');
            $dbPassword = io()->ask('Entrez le mot de passe de connexion de la base de données', 'root');
            $dbHost = io()->ask('Entrez l\'hôte de la base de données', 'localhost');
            $dbPort = io()->ask('Entrez le port de connexion à la base de données', '3306');
            $dbName = io()->ask('Entrez le nom de la base de données qui va être crée', '_2025_edith');

            // Fonction pour vérifier l'existence de la base de données
            function checkDatabaseExists($dbUser, $dbPassword, $dbHost, $dbPort, $dbName)
            {
                try {
                    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort", $dbUser, $dbPassword);
                    $query = $pdo->prepare("SHOW DATABASES LIKE ?");
                    $query->execute([$dbName]);
                    return $query->fetch() !== false;
                } catch (PDOException $e) {
                    throw new RuntimeException("Erreur de connexion MySQL : " . $e->getMessage());
                }
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
    $installComposer = io()->confirm('Voulez-vous installer les dépendances Composer maintenant ?', true);

    if ($installComposer) {
        io()->section('Installation des dépendances Composer...');
        exec('composer install', $output, $returnVar);
        if ($returnVar === 0) {
            io()->success('Dépendances Composer installées.');
        } else {
            io()->error('Échec de l\'installation des dépendances Composer.');
            return;
        }
    } else {
        io()->success('Installation des dépendances Composer ignorée.');
    }

    // Étape 4 : Installation des dépendances NPM
    if (file_exists('package.json')) {
        // demander si on veut installer les dépendances NPM  ou pas
        $installNpm = io()->confirm('Voulez-vous installer les dépendances NPM maintenant ?', true);

        if ($installNpm) {
            io()->section('Installation des dépendances NPM...');
            exec('npm install', $output, $returnVar);
            if ($returnVar === 0) {
                io()->success('Dépendances NPM installées.');
            } else {
                io()->error('Échec de l\'installation des dépendances NPM.');
                return;
            }
        } else {
            io()->success('Installation des dépendances NPM ignorée.');
        }
    }

    // Étape 5 : Création de la base de données
    io()->section('Création de la base de données...');
    exec('php bin/console doctrine:database:create', $output, $returnVar);
    if ($returnVar === 0) {
        io()->success('Base de données créée.');
    } else {
        io()->error('Échec de la création de la base de données.');
        return;
    }

    // Étape 6 : Mise à jour du schéma de la base de données
    io()->section('Mise à jour du schéma Doctrine...');
    exec('php bin/console doctrine:schema:update --force --complete', $output, $returnVar);
    if ($returnVar === 0) {
        io()->success('Schéma Doctrine mis à jour.');
    } else {
        io()->error('Échec de la mise à jour du schéma Doctrine.');
        return;
    }

    // Étape 7 : Vérification des droits sur le dossier public
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
        sleep(5); // Attente de 5 secondes
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

    // Étape 1 : Lancer le serveur Symfony uniquement s'il n'est pas déjà en cours
    io()->section('Démarrage du serveur Symfony...');

    $serverRunning = false;
    $pid = null;

    if ($isMac) {
        // Vérifier si le serveur Symfony est déjà en cours
        exec('lsof -i :8000', $output, $returnVar);
        if (!empty($output)) {
            $serverRunning = true;
            // Extraire le PID du serveur Symfony en cours d'exécution
            preg_match('/\d+/', $output[0], $matches);
            $pid = $matches[0] ?? null;
        }
    } elseif ($isWindows) {
        // Vérifier si le serveur Symfony est déjà en cours (port 8000 utilisé)
        exec('netstat -ano | findstr :8000', $output, $returnVar);
        if (!empty($output)) {
            $serverRunning = true;
            // Extraire le PID à partir de la sortie de netstat (dernier champ)
            preg_match('/\d+/', end($output), $matches);
            $pid = $matches[0] ?? null;
        }
    } elseif ($isLinux) {
        // Vérifier si le serveur Symfony est déjà en cours
        exec('lsof -i :8000', $output, $returnVar);
        if (!empty($output)) {
            $serverRunning = true;
            // Extraire le PID du serveur Symfony en cours d'exécution
            preg_match('/\d+/', $output[0], $matches);
            $pid = $matches[0] ?? null;
        }
    }

    if ($serverRunning && $pid !== null) {
        // Si le serveur est déjà en cours, le tuer
        io()->warning('Le serveur Symfony est déjà en cours d\'exécution, arrêt du processus...');
        if ($isMac) {
            exec('kill -9 ' . $pid, $output, $returnVar);
        } elseif ($isWindows) {
            exec('taskkill /F /PID ' . $pid, $output, $returnVar);
        } elseif ($isLinux) {
            exec('kill -9 ' . $pid, $output, $returnVar);
        }

        if ($returnVar === 0) {
            io()->success('Serveur Symfony arrêté avec succès.');
        } else {
            io()->error('Erreur lors de l\'arrêt du serveur Symfony.');
            return;
        }
    }

    // Démarrer le serveur Symfony
    io()->section('Démarrage du serveur Symfony...');

    if ($isMac) {
        exec('osascript -e \'tell application "Terminal" to do script "cd ' . getcwd() . ' && symfony server:start"\'', $output, $returnVar);
    } elseif ($isWindows) {
        pclose(popen('start "" cmd.exe /K "cd /d ' . getcwd() . ' && symfony server:start"', 'r'));
    } elseif ($isLinux) {
        exec('gnome-terminal -- bash -c "cd ' . getcwd() . ' && symfony server:start; exec bash"', $output, $returnVar);
        if ($returnVar !== 0) {
            exec('xterm -e "cd ' . getcwd() . ' && symfony server:start; exec bash"', $output, $returnVar);
        }
    }

    io()->success('Serveur Symfony démarré.');

    sleep(2); // Attente de 2 secondes

    // Étape 2 : Démarrer la surveillance des assets
    io()->section('Démarrage de la surveillance des assets...');

    if ($isMac) {
        exec('osascript -e \'tell application "Terminal" to do script "cd ' . getcwd() . ' && npm run watch"\'', $output, $returnVar);
    } elseif ($isWindows) {
        pclose(popen('start "" cmd.exe /K "cd /d ' . getcwd() . ' && npm run watch"', 'r'));
    } elseif ($isLinux) {
        exec('gnome-terminal -- bash -c "cd ' . getcwd() . ' && npm run watch; exec bash"', $output, $returnVar);
        if ($returnVar !== 0) {
            exec('xterm -e "cd ' . getcwd() . ' && npm run watch; exec bash"', $output, $returnVar);
        }
    }

    io()->success('Surveillance des assets activée dans un nouveau terminal.');
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
