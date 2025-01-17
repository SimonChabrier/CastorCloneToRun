<?php

use Castor\Attribute\AsTask;

use function Castor\check;
use function Castor\io;

#[AsTask(description: 'Cloner un dépôt, configure et lance un projet Symfony avec serveurs et navigation')]
function cloneProject(): void
{

    $installNpm = false;

    io()->title('Initialisation du projet Symfony');

    $currentDir = getcwd();
    io()->text("Dossier actuel : $currentDir");

    // demander si on veut remonter d'un niveau pour cloner le dépôt hors du dossier courant
    $upLevel = io()->confirm('Voulez-vous cloner le dépôt dans un dossier parent ?', false);
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
        $delete = io()->confirm("Le dossier '$projectName' existe déjà. voulez vous le supprimer ?", false);
        if ($delete) {
            exec("rm -rf $projectName", $output, $returnVar);
            if ($returnVar === 0) {
                io()->success("Dossier '$projectName' supprimé.");
            } else {
                io()->error("Échec de la suppression du dossier '$projectName'.");
                return;
            }
        } else {
            io()->error("Le dossier '$projectName' existe déjà. Veuillez le supprimer ou choisir un autre nom. La commande est annulée.");
            return;
        }
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

    // informer l'utilisateur de la compatibilité des versions 
    io()->section('Vérification de la compatibilité des versions PHP...');
    $phpVersion = checkSystemPhp();
    $phpProjectMaxVersion = checkProjectPhpMaxVersion();
    // si la verison php du système est supérieure à la version php requise par le projet on retourne un warning pour informer l'utilisateur que des dépandances peuvent ne pas être compatibles
    if (version_compare($phpVersion, $phpProjectMaxVersion, '>')) {
        io()->warning("La version PHP du système est supérieure à la version PHP requise par le projet. Certaines dépendances peuvent ne pas être compatibles et le script peut échouer.");
    }

    // Étape 3 : Installation des dépendances avec Composer
    $installComposer = io()->confirm('Voulez-vous installer les dépendances Composer maintenant ?', true);

    if ($installComposer) {
        io()->section('Installation des dépendances Composer...');
        exec('composer install', $output, $returnVar);
        if ($returnVar === 0) {
            io()->success('Dépendances Composer installées.');
        } else {
            io()->warning('Échec de l\'installation des dépendances Composer car certaines dépendances sont pas compatibles avec la version PHP du système.');
            // demander si on veut recommander en faisant un composer update
            $updateComposer = io()->confirm('Voulez-vous essayer de mettre à jour les dépendances Composer avec "composer update" ?', false);
            if ($updateComposer) {
                exec('composer update --with-all-dependencies', $output, $returnVar);
                if ($returnVar === 0) {
                    io()->success('Dépendances Composer mises à jour.');
                } else {
                    io()->error('Échec de la mise à jour des dépendances Composer.');
                    return;
                }
            } else {
                return;
            }
        }
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
    io()->section('Mise à jour de la base de données...');

    // regarder si il y a un dossier migrations
    if (is_dir('migrations')) {
        // demander si on veut
        $migrate = io()->confirm('Voulez-vous exécuter les migrations Doctrine ?', true);
        if ($migrate) {
            exec('php bin/console doctrine:migrations:migrate --no-interaction', $output, $returnVar);
            if ($returnVar === 0) {
                io()->success('Migrations Doctrine exécutées.');
            } else {
                io()->error('Échec de l\'exécution des migrations Doctrine.');
                return;
            }
        }
    } else {
        exec('php bin/console doctrine:schema:update --force --complete', $output, $returnVar);
        if ($returnVar === 0) {
            io()->success('Schéma Doctrine mis à jour.');
        } else {
            io()->error('Échec de la mise à jour du schéma Doctrine.');
            return;
        }
    }

    // est ce qu'il y a des fixtures
    if (is_dir('src/DataFixtures')) {
        // demander si on veut
        $loadFixtures = io()->confirm('Voulez-vous charger les fixtures Doctrine ?', true);
        if ($loadFixtures) {
            exec('php bin/console doctrine:fixtures:load --no-interaction', $output, $returnVar);
            if ($returnVar === 0) {
                io()->success('Fixtures Doctrine chargées.');
            } else {
                io()->error('Échec du chargement des fixtures Doctrine.');
                return;
            }
        }
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
    if ($launchServers && $installNpm) {
        runProject(); // appel de runProject pour lancer les serveurs
        runAssets(); // appel de runAssets pour lancer les serveurs
        sleep(5); // Attente de 5 secondes
        openProject();
    } elseif ($launchServers && !$installNpm) {
        runProject(); // appel de runProject pour lancer les serveurs
        sleep(5); // Attente de 5 secondes
        openProject();
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

// Commande pour identifier si Xamp Mamp ou Wamp est installé
#[AsTask(description: 'Vérifier si XAMPP, MAMP ou WAMP est installé')]
function checkLocalServer(): void
{
    io()->title('Vérification de XAMPP, MAMP ou WAMP');
    $system = checkOS();

    // si mac on cherche MAMP
    if ($system === 'macOS') {
        $isMamp = is_dir('/Applications/MAMP');
        if ($isMamp) {
            io()->success('MAMP est installé.');
        } else {
            io()->warning('MAMP n\'est pas installé.');
        }
    } elseif ($system === 'Windows') {
        // si windows on cherche XAMPP ou WAMP
        $isXampp = is_dir('C:\xampp');
        $isWamp = is_dir('C:\wamp');
        if ($isXampp) {
            io()->success('XAMPP est installé.');
        } elseif ($isWamp) {
            io()->success('WAMP est installé.');
        } else {
            io()->warning('Ni XAMPP ni WAMP n\'est installé.');
        }
    } elseif ($system === 'Linux') {
        // si linux on cherche LAMP
        $isLamp = is_dir('/opt/lampp');
        if ($isLamp) {
            io()->success('LAMP est installé.');
        } else {
            io()->warning('LAMP n\'est pas installé.');
        }
    }
}

// chercher la version php activée sur le système pour voir si elles correspondent
#[AsTask(description: 'Vérifier la version PHP installée sur le système')]
function checkSystemPhp(): string
{
    io()->title('Vérification de la version PHP');
    return phpversion();
}

// Vérifier la version de php demandé dans le composer.json
#[AsTask(description: 'Vérifier la version PHP requise dans le fichier composer.json')]
function checkProjectPhpMaxVersion(): string
{
    io()->title('Vérification de la version PHP requise par le coposer.json');
    // regarder toutes les versions de php dans le composer.json requise pour chaque package on cherche par exemple ça : "php": ">=7.2.5", et retourne la version la plus haute
    preg_match_all('/"php": ">=([0-9.]+)"/', file_get_contents('composer.json'), $matches);
    $phpVersions = $matches[1] ?? [];
    $phpMaxVersion = max($phpVersions);
    io()->success("Version PHP requise par le projet a été trouvée das composer.json : $phpMaxVersion");
    return $phpMaxVersion;
}

// command epour vérifier le système d'exploitation
#[AsTask(description: 'Vérifier le système d\'exploitation')]
function checkOS(): string
{
    io()->title('Vérification du système d\'exploitation');

    $isMac = strtoupper(substr(PHP_OS, 0, 3)) === 'DAR';  // macOS
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';  // Windows
    $isLinux = strtolower(PHP_OS) === 'linux';  // Linux

    if ($isMac) {
        io()->success('Système d\'exploitation : macOS');
    } elseif ($isWindows) {
        io()->success('Système d\'exploitation : Windows');
    } elseif ($isLinux) {
        io()->success('Système d\'exploitation : Linux');
    } else {
        io()->warning('Système d\'exploitation non reconnu.');
    }

    // on retourne le nom du système d'exploitation
    return $isMac ? 'macOS' : ($isWindows ? 'Windows' : ($isLinux ? 'Linux' : 'Inconnu'));
}

#[AsTask(description: 'Lancer les serveurs Symfony et de surveillance des assets')]
function runProject(): void
{
    // Vérifier le système d'exploitation
    $ystem = checkOS();

    // Étape 1 : Lancer le serveur Symfony uniquement s'il n'est pas déjà en cours
    io()->section('Démarrage du serveur Symfony...');

    $serverRunning = false;
    $pid = null;

    // ouverture du terminal pour lancer le serveur
    if ($ystem === 'macOS') {
        // Vérifier si le serveur Symfony est déjà en cours
        exec('lsof -i :8000', $output, $returnVar);
        if (!empty($output)) {
            $serverRunning = true;
            // Extraire le PID du serveur Symfony en cours d'exécution
            preg_match('/\d+/', $output[0], $matches);
            $pid = $matches[0] ?? null;
        }
    } elseif ($ystem === 'Windows') {
        // Vérifier si le serveur Symfony est déjà en cours (port 8000 utilisé)
        exec('netstat -ano | findstr :8000', $output, $returnVar);
        if (!empty($output)) {
            $serverRunning = true;
            // Extraire le PID à partir de la sortie de netstat (dernier champ)
            preg_match('/\d+/', end($output), $matches);
            $pid = $matches[0] ?? null;
        }
    } elseif ($ystem === 'Linux') {
        // Vérifier si le serveur Symfony est déjà en cours
        exec('lsof -i :8000', $output, $returnVar);
        if (!empty($output)) {
            $serverRunning = true;
            // Extraire le PID du serveur Symfony en cours d'exécution
            preg_match('/\d+/', $output[0], $matches);
            $pid = $matches[0] ?? null;
        }
    }

    // Si un serveur est déjà en cours, le tuer et le redémarrer
    if ($serverRunning && $pid !== null) {
        // Si le serveur est déjà en cours, le tuer
        io()->warning('Le serveur Symfony est déjà en cours d\'exécution, arrêt du processus...');
        if ($ystem === 'macOS') {
            exec('kill -9 ' . $pid, $output, $returnVar);
        } elseif ($ystem === 'Windows') {
            exec('taskkill /F /PID ' . $pid, $output, $returnVar);
        } elseif ($ystem === 'Linux') {
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
    if ($ystem === 'macOS') {
        exec('osascript -e \'tell application "Terminal" to do script "cd ' . getcwd() . ' && symfony server:start"\'', $output, $returnVar);
    } elseif ($ystem === 'Windows') {
        pclose(popen('start "" cmd.exe /K "cd /d ' . getcwd() . ' && symfony server:start"', 'r'));
    } elseif ($ystem === 'Linux') {
        exec('gnome-terminal -- bash -c "cd ' . getcwd() . ' && symfony server:start; exec bash"', $output, $returnVar);
        if ($returnVar !== 0) {
            exec('xterm -e "cd ' . getcwd() . ' && symfony server:start; exec bash"', $output, $returnVar);
        }
    }

    io()->success('Serveur Symfony démarré.');
}

// laner les assets
#[AsTask(description: 'Lancer le serveur de surveillance des assets')]
function runAssets(): void
{
    io()->title('Lancement du serveur de surveillance des assets');

    // Vérifier le système d'exploitation
    $ystem = checkOS();

    // Démarrer le serveur de surveillance des assets
    io()->section('Démarrage du serveur de surveillance des assets...');

    if ($ystem === 'macOS') {
        exec('osascript -e \'tell application "Terminal" to do script "cd ' . getcwd() . ' && npm run watch"\'', $output, $returnVar);
    } elseif ($ystem === 'Windows') {
        pclose(popen('start "" cmd.exe /K "cd /d ' . getcwd() . ' && npm run watch"', 'r'));
    } elseif ($ystem === 'Linux') {
        exec('gnome-terminal -- bash -c "cd ' . getcwd() . ' && npm run watch; exec bash"', $output, $returnVar);
        if ($returnVar !== 0) {
            exec('xterm -e "cd ' . getcwd() . ' && npm run watch; exec bash"', $output, $returnVar);
        }
    }

    io()->success('Serveur de surveillance des assets démarré.');
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
