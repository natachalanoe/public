<?php
/**
 * Test simple de configuration - Version ultra-basique
 * À supprimer après diagnostic
 */

// Configuration de sécurité
$ALLOWED_IPS = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!in_array($clientIP, $ALLOWED_IPS) && !isset($_GET['force'])) {
    die("Accès restreint. Ajoutez ?force=1 à l'URL");
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Simple Configuration - Avision</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-section { margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .code { background: #f8f9fa; padding: 8px; border-radius: 3px; font-family: monospace; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Test Simple Configuration</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        // Test 1: Informations de base
        echo '<div class="test-section">';
        echo '<h2>📋 Informations de base</h2>';
        echo '<div class="code">';
        echo "PHP Version: " . PHP_VERSION . "<br>";
        echo "OS: " . PHP_OS . "<br>";
        echo "Répertoire actuel: " . getcwd() . "<br>";
        echo "Script: " . __FILE__ . "<br>";
        echo '</div>';
        echo '</div>';

        // Test 2: Vérification des fichiers
        echo '<div class="test-section">';
        echo '<h2>📁 Vérification des fichiers</h2>';
        
        $filesToCheck = [
            '../config/config.php',
            '../../config/config.php',
            'config/config.php',
            '../config/database.php',
            '../../config/database.php'
        ];
        
        foreach ($filesToCheck as $file) {
            if (file_exists($file)) {
                echo "<div class='success'>✅ $file existe</div>";
            } else {
                echo "<div class='error'>❌ $file n'existe pas</div>";
            }
        }
        echo '</div>';

        // Test 3: Contenu du répertoire
        echo '<div class="test-section">';
        echo '<h2>📂 Contenu du répertoire</h2>';
        echo '<div class="code">';
        $files = scandir('.');
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- $file<br>";
            }
        }
        echo '</div>';
        echo '</div>';

        // Test 4: Test de chargement de configuration
        echo '<div class="test-section">';
        echo '<h2>🔧 Test de chargement de configuration</h2>';
        
        try {
            if (file_exists('../config/config.php')) {
                echo "<div class='info'>Tentative de chargement de ../config/database.php</div>";
                if (file_exists('../config/database.php')) {
                    require_once '../config/database.php';
                    echo "<div class='success'>✅ database.php chargé avec succès</div>";
                } else {
                    echo "<div class='error'>❌ database.php non trouvé</div>";
                }
                
                echo "<div class='info'>Tentative de chargement de ../config/config.php</div>";
                require_once '../config/config.php';
                echo "<div class='success'>✅ Configuration chargée avec succès</div>";
                
                if (class_exists('Config')) {
                    echo "<div class='success'>✅ Classe Config trouvée</div>";
                    $config = Config::getInstance();
                    echo "<div class='success'>✅ Instance Config créée</div>";
                    
                    // Test de quelques valeurs
                    $testValues = [
                        'mail_host' => $config->get('mail_host', 'Non configuré'),
                        'oauth2_enabled' => $config->get('oauth2_enabled', 'Non configuré'),
                        'oauth2_client_id' => $config->get('oauth2_client_id', 'Non configuré')
                    ];
                    
                    echo '<div class="code">';
                    foreach ($testValues as $key => $value) {
                        echo "$key: $value<br>";
                    }
                    echo '</div>';
                } else {
                    echo "<div class='error'>❌ Classe Config non trouvée</div>";
                }
            } else {
                echo "<div class='error'>❌ Fichier de configuration non trouvé</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erreur: " . $e->getMessage() . "</div>";
        } catch (Error $e) {
            echo "<div class='error'>❌ Erreur fatale: " . $e->getMessage() . "</div>";
        }
        echo '</div>';

        // Test 5: Test de connectivité simple
        echo '<div class="test-section">';
        echo '<h2>🌐 Test de connectivité simple</h2>';
        
        // Test DNS simple
        $hosts = ['smtp.office365.com', 'outlook.office365.com'];
        foreach ($hosts as $host) {
            $ip = gethostbyname($host);
            if ($ip !== $host) {
                echo "<div class='success'>✅ $host → $ip</div>";
            } else {
                echo "<div class='error'>❌ $host → Non résolu</div>";
            }
        }
        echo '</div>';

        // Test 6: Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations</h2>';
        echo '<ul>';
        echo '<li><strong>Configuration manquante :</strong> Vérifiez que le fichier config.php existe</li>';
        echo '<li><strong>Erreur 500 :</strong> Vérifiez les logs du serveur pour plus de détails</li>';
        echo '<li><strong>Permissions :</strong> Vérifiez les permissions des fichiers de configuration</li>';
        echo '<li><strong>Chemin :</strong> Vérifiez que le chemin vers la configuration est correct</li>';
        echo '</ul>';
        echo '</div>';
        ?>

        <div class="test-section warning">
            <h2>⚠️ Important</h2>
            <p><strong>Supprimez ce fichier après diagnostic.</strong></p>
        </div>
    </div>
</body>
</html>
