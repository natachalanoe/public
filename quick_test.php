<?php
/**
 * Test rapide de connectivité - Version simplifiée
 * À supprimer après diagnostic
 */

// Configuration de sécurité
$ALLOWED_IPS = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!in_array($clientIP, $ALLOWED_IPS) && !isset($_GET['force'])) {
    die("Accès restreint. Ajoutez ?force=1 à l'URL");
}

// Augmenter les limites de temps
set_time_limit(60);
ini_set('max_execution_time', 60);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Rapide - Avision</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-section { margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .code { background: #f8f9fa; padding: 8px; border-radius: 3px; font-family: monospace; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚡ Test Rapide de Connectivité</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        // Test 1: Informations de base
        echo '<div class="test-section">';
        echo '<h2>📋 Informations de base</h2>';
        echo '<div class="code">';
        echo "PHP Version: " . PHP_VERSION . "<br>";
        echo "OS: " . PHP_OS . "<br>";
        echo "Extensions: ";
        $extensions = ['curl', 'openssl', 'json'];
        foreach ($extensions as $ext) {
            echo extension_loaded($ext) ? "✅$ext " : "❌$ext ";
        }
        echo "<br>Max execution time: " . ini_get('max_execution_time') . "s<br>";
        echo "Memory limit: " . ini_get('memory_limit') . "<br>";
        echo '</div>';
        echo '</div>';

        // Test 2: Test DNS simple
        echo '<div class="test-section">';
        echo '<h2>🌐 Test DNS</h2>';
        
        $hosts = ['login.microsoftonline.com', 'smtp.office365.com'];
        foreach ($hosts as $host) {
            $ip = gethostbyname($host);
            if ($ip !== $host) {
                echo "<div class='success'>✅ $host → $ip</div>";
            } else {
                echo "<div class='error'>❌ $host → Non résolu</div>";
            }
        }
        echo '</div>';

        // Test 3: Test de port simple
        echo '<div class="test-section">';
        echo '<h2>🔌 Test de ports</h2>';
        
        $ports = [
            ['login.microsoftonline.com', 443, 'HTTPS Microsoft'],
            ['smtp.office365.com', 587, 'SMTP TLS'],
            ['smtp.office365.com', 465, 'SMTP SSL']
        ];
        
        foreach ($ports as $test) {
            list($host, $port, $desc) = $test;
            $startTime = microtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            $endTime = microtime(true);
            
            if ($socket) {
                fclose($socket);
                $time = round(($endTime - $startTime) * 1000, 2);
                echo "<div class='success'>✅ $desc ($host:$port) - {$time}ms</div>";
            } else {
                echo "<div class='error'>❌ $desc ($host:$port) - $errstr</div>";
            }
        }
        echo '</div>';

        // Test 4: Test cURL simple
        echo '<div class="test-section">';
        echo '<h2>🔗 Test cURL</h2>';
        
        $urls = [
            'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration',
            'https://graph.microsoft.com/v1.0/me'
        ];
        
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $time = round(($endTime - $startTime) * 1000, 2);
            
            if (empty($error)) {
                $class = ($httpCode >= 200 && $httpCode < 500) ? 'success' : 'warning';
                echo "<div class='$class'>";
                echo "✅ $url<br>";
                echo "Code: $httpCode | Temps: {$time}ms";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "❌ $url<br>";
                echo "Erreur: $error | Temps: {$time}ms";
                echo "</div>";
            }
        }
        echo '</div>';

        // Test 5: Configuration OAuth2
        echo '<div class="test-section">';
        echo '<h2>🔧 Configuration OAuth2</h2>';
        
        try {
            if (file_exists('../../config/config.php')) {
                require_once '../../config/config.php';
                $config = Config::getInstance();
                
                $clientId = $config->get('oauth2_client_id', '');
                $tenantId = $config->get('oauth2_tenant_id', '');
                $redirectUri = $config->get('oauth2_redirect_uri', '');
                $clientSecret = $config->get('oauth2_client_secret', '');
                
                echo '<div class="code">';
                echo "Client ID: " . (empty($clientId) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Tenant ID: " . (empty($tenantId) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Redirect URI: " . (empty($redirectUri) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Client Secret: " . (empty($clientSecret) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo '</div>';
                
                // Test avec le tenant ID
                if (!empty($tenantId)) {
                    $testUrl = "https://login.microsoftonline.com/$tenantId/v2.0/.well-known/openid_configuration";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $testUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);
                    
                    if (empty($error)) {
                        $class = ($httpCode === 200) ? 'success' : 'warning';
                        echo "<div class='$class'>";
                        echo "Test tenant ID: Code $httpCode";
                        if ($httpCode === 200) {
                            echo " ✅ Endpoint accessible";
                        } elseif ($httpCode === 404) {
                            echo " ⚠️ Endpoint non trouvé - Vérifiez le Tenant ID";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='error'>Test tenant ID: Erreur - $error</div>";
                    }
                }
            } else {
                echo "<div class='error'>Fichier de configuration non trouvé</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Erreur: " . $e->getMessage() . "</div>";
        }
        echo '</div>';

        // Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations</h2>';
        echo '<ul>';
        echo '<li><strong>Timeout 504 :</strong> Contactez votre hébergeur pour augmenter les timeouts</li>';
        echo '<li><strong>Ports SMTP :</strong> Demandez l\'ouverture des ports 587 et 465</li>';
        echo '<li><strong>OAuth2 :</strong> Vérifiez que l\'application est autorisée dans le tenant</li>';
        echo '<li><strong>Sécurité :</strong> Supprimez ce fichier après diagnostic</li>';
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
