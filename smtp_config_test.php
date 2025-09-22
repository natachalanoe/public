<?php
/**
 * Test de configuration SMTP - Version ultra-rapide
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
    <title>Test Configuration SMTP - Avision</title>
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
        <h1>⚙️ Test Configuration SMTP</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        // Test 1: Informations système
        echo '<div class="test-section">';
        echo '<h2>📋 Informations Système</h2>';
        echo '<div class="code">';
        echo "PHP Version: " . PHP_VERSION . "<br>";
        echo "OS: " . PHP_OS . "<br>";
        echo "Extensions: ";
        $extensions = ['curl', 'openssl', 'json', 'mbstring'];
        foreach ($extensions as $ext) {
            echo extension_loaded($ext) ? "✅$ext " : "❌$ext ";
        }
        echo "<br>Max execution time: " . ini_get('max_execution_time') . "s<br>";
        echo "Memory limit: " . ini_get('memory_limit') . "<br>";
        echo '</div>';
        echo '</div>';

        // Test 2: Configuration SMTP
        echo '<div class="test-section">';
        echo '<h2>📧 Configuration SMTP</h2>';
        
        try {
            // Essayer plusieurs chemins possibles
            $configPaths = [
                '../config/config.php',
                '../../config/config.php',
                'config/config.php'
            ];
            
            $configFound = false;
            foreach ($configPaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $configFound = true;
                    echo "<div class='info'>Configuration trouvée dans : $path</div>";
                    break;
                }
            }
            
            if (!$configFound) {
                echo "<div class='error'>Aucun fichier de configuration trouvé dans : " . implode(', ', $configPaths) . "</div>";
                echo "<div class='code'>";
                echo "Répertoire actuel : " . getcwd() . "<br>";
                echo "Fichiers dans le répertoire :<br>";
                $files = scandir('.');
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        echo "- $file<br>";
                    }
                }
                echo "</div>";
                return;
            }
                $config = Config::getInstance();
                
                $smtpConfig = [
                    'host' => $config->get('mail_host', ''),
                    'port' => $config->get('mail_port', ''),
                    'username' => $config->get('mail_username', ''),
                    'password' => $config->get('mail_password', ''),
                    'encryption' => $config->get('mail_encryption', ''),
                    'from_address' => $config->get('mail_from_address', ''),
                    'from_name' => $config->get('mail_from_name', '')
                ];
                
                echo '<div class="code">';
                echo "Host SMTP : " . ($smtpConfig['host'] ?: '❌ Non configuré') . "<br>";
                echo "Port : " . ($smtpConfig['port'] ?: '❌ Non configuré') . "<br>";
                echo "Username : " . ($smtpConfig['username'] ? '✅ Configuré' : '❌ Non configuré') . "<br>";
                echo "Password : " . ($smtpConfig['password'] ? '✅ Configuré' : '❌ Non configuré') . "<br>";
                echo "Encryption : " . ($smtpConfig['encryption'] ?: '❌ Non configuré') . "<br>";
                echo "From Address : " . ($smtpConfig['from_address'] ?: '❌ Non configuré') . "<br>";
                echo "From Name : " . ($smtpConfig['from_name'] ?: '❌ Non configuré') . "<br>";
                echo '</div>';
            } else {
                echo "<div class='error'>Fichier de configuration non trouvé</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Erreur : " . $e->getMessage() . "</div>";
        }
        echo '</div>';

        // Test 3: Configuration OAuth2
        echo '<div class="test-section">';
        echo '<h2>🔐 Configuration OAuth2</h2>';
        
        try {
            // Utiliser le même chemin que pour SMTP
            $configPaths = [
                '../config/config.php',
                '../../config/config.php',
                'config/config.php'
            ];
            
            $configFound = false;
            foreach ($configPaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $configFound = true;
                    break;
                }
            }
            
            if ($configFound) {
                $config = Config::getInstance();
                
                $oauth2Config = [
                    'enabled' => $config->get('oauth2_enabled', '0'),
                    'client_id' => $config->get('oauth2_client_id', ''),
                    'client_secret' => $config->get('oauth2_client_secret', ''),
                    'tenant_id' => $config->get('oauth2_tenant_id', ''),
                    'redirect_uri' => $config->get('oauth2_redirect_uri', ''),
                    'access_token' => $config->get('oauth2_access_token', ''),
                    'refresh_token' => $config->get('oauth2_refresh_token', ''),
                    'token_expires' => $config->get('oauth2_token_expires', '')
                ];
                
                echo '<div class="code">';
                echo "OAuth2 activé : " . ($oauth2Config['enabled'] ? '✅ Oui' : '❌ Non') . "<br>";
                echo "Client ID : " . (empty($oauth2Config['client_id']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Client Secret : " . (empty($oauth2Config['client_secret']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Tenant ID : " . (empty($oauth2Config['tenant_id']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Redirect URI : " . (empty($oauth2Config['redirect_uri']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Access Token : " . (empty($oauth2Config['access_token']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Refresh Token : " . (empty($oauth2Config['refresh_token']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Token expire : " . ($oauth2Config['token_expires'] ?: '❌ Non configuré') . "<br>";
                echo '</div>';
                
                // Vérifier si le token est expiré
                if (!empty($oauth2Config['token_expires'])) {
                    $expires = strtotime($oauth2Config['token_expires']);
                    $now = time();
                    if ($expires <= $now) {
                        echo "<div class='warning'>⚠️ Token OAuth2 expiré</div>";
                    } else {
                        $remaining = $expires - $now;
                        echo "<div class='success'>✅ Token OAuth2 valide (expire dans " . round($remaining/60) . " minutes)</div>";
                    }
                }
            }
        } catch (Exception $e) {
            echo "<div class='error'>Erreur : " . $e->getMessage() . "</div>";
        }
        echo '</div>';

        // Test 4: Test de connectivité simple (sans timeout)
        echo '<div class="test-section">';
        echo '<h2>🌐 Test de Connectivité Simple</h2>';
        
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

        // Test 5: Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations</h2>';
        echo '<ul>';
        echo '<li><strong>Configuration incomplète :</strong> Remplissez tous les champs requis</li>';
        echo '<li><strong>Token expiré :</strong> Re-autorisez l\'application OAuth2</li>';
        echo '<li><strong>Ports bloqués :</strong> Contactez l\'hébergeur pour ouvrir les ports 587 et 465</li>';
        echo '<li><strong>Firewall :</strong> Vérifiez que le firewall n\'bloque pas les connexions SMTP</li>';
        echo '</ul>';
        echo '</div>';

        // Test 6: Actions
        echo '<div class="test-section info">';
        echo '<h2>🎯 Actions</h2>';
        echo '<ul>';
        echo '<li><strong>Test d\'envoi :</strong> Utilisez le bouton "Tester envoi email" dans les paramètres</li>';
        echo '<li><strong>Re-autorisation :</strong> Cliquez sur "Autoriser l\'application" si le token est expiré</li>';
        echo '<li><strong>Désactiver OAuth2 :</strong> Utilisez le bouton "Désactiver OAuth2" en cas de problème</li>';
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
