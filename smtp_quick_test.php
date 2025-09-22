<?php
/**
 * Test rapide des ports SMTP - Version simplifiée
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
    <title>Test Rapide SMTP - Avision</title>
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
        <h1>⚡ Test Rapide SMTP</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        function quickSmtpTest($host, $port, $description) {
            $startTime = microtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            $endTime = microtime(true);
            
            if ($socket) {
                fclose($socket);
                $time = round(($endTime - $startTime) * 1000, 2);
                return [
                    'success' => true,
                    'message' => "Port $port ouvert - {$time}ms"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Port $port fermé - $errstr"
                ];
            }
        }

        // Test 1: Ports SMTP Office 365
        echo '<div class="test-section">';
        echo '<h2>🔌 Test des Ports SMTP</h2>';
        
        $tests = [
            ['smtp.office365.com', 587, 'SMTP TLS'],
            ['smtp.office365.com', 465, 'SMTP SSL']
        ];
        
        foreach ($tests as $test) {
            list($host, $port, $desc) = $test;
            $result = quickSmtpTest($host, $port, $desc);
            $class = $result['success'] ? 'success' : 'error';
            
            echo "<div class='$class'>";
            echo "<strong>$desc ($host:$port) :</strong> ";
            echo $result['message'];
            echo "</div>";
        }
        echo '</div>';

        // Test 2: Connexion SMTP simple
        echo '<div class="test-section">';
        echo '<h2>📧 Test de Connexion SMTP</h2>';
        
        try {
            $socket = stream_socket_client("tcp://smtp.office365.com:587", $errno, $errstr, 10);
            if ($socket) {
                $response = fgets($socket, 1024);
                fclose($socket);
                
                if (preg_match('/^220/', $response)) {
                    echo "<div class='success'>";
                    echo "✅ Connexion SMTP réussie<br>";
                    echo "Réponse serveur : <code>" . trim($response) . "</code>";
                    echo "</div>";
                } else {
                    echo "<div class='error'>";
                    echo "❌ Réponse serveur invalide : <code>" . trim($response) . "</code>";
                    echo "</div>";
                }
            } else {
                echo "<div class='error'>";
                echo "❌ Impossible de se connecter : $errstr";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "❌ Erreur : " . $e->getMessage();
            echo "</div>";
        }
        echo '</div>';

        // Test 3: DNS
        echo '<div class="test-section">';
        echo '<h2>🌐 Test DNS</h2>';
        
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

        // Test 4: Configuration OAuth2
        echo '<div class="test-section">';
        echo '<h2>🔧 Configuration OAuth2</h2>';
        
        try {
            if (file_exists('../config/config.php')) {
                require_once '../config/config.php';
                $config = Config::getInstance();
                
                $oauth2Config = [
                    'enabled' => $config->get('oauth2_enabled', '0'),
                    'client_id' => $config->get('oauth2_client_id', ''),
                    'tenant_id' => $config->get('oauth2_tenant_id', ''),
                    'access_token' => $config->get('oauth2_access_token', ''),
                    'token_expires' => $config->get('oauth2_token_expires', '')
                ];
                
                echo "<div class='code'>";
                echo "OAuth2 activé : " . ($oauth2Config['enabled'] ? '✅ Oui' : '❌ Non') . "<br>";
                echo "Client ID : " . (empty($oauth2Config['client_id']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Tenant ID : " . (empty($oauth2Config['tenant_id']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Access Token : " . (empty($oauth2Config['access_token']) ? '❌ Non configuré' : '✅ Configuré') . "<br>";
                echo "Token expire : " . ($oauth2Config['token_expires'] ?: '❌ Non configuré') . "<br>";
                echo "</div>";
                
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
            } else {
                echo "<div class='error'>Fichier de configuration non trouvé</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Erreur : " . $e->getMessage() . "</div>";
        }
        echo '</div>';

        // Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Diagnostic</h2>';
        echo '<ul>';
        echo '<li><strong>Ports fermés :</strong> Contactez l\'hébergeur pour ouvrir les ports 587 et 465</li>';
        echo '<li><strong>Connexion échoue :</strong> Vérifiez le firewall applicatif</li>';
        echo '<li><strong>Token expiré :</strong> Re-autorisez l\'application OAuth2</li>';
        echo '<li><strong>DNS échoue :</strong> Vérifiez la configuration DNS du serveur</li>';
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
