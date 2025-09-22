<?php
/**
 * Diagnostic OAuth2 - Test complet
 * À supprimer après diagnostic
 */

// Configuration de sécurité
$ALLOWED_IPS = ['127.0.0.1', '::1'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!in_array($clientIP, $ALLOWED_IPS) && !isset($_GET['force'])) {
    die("Accès restreint. Ajoutez ?force=1 à l'URL");
}

// Charger la configuration
require_once '../config/database.php';
require_once '../config/config.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic OAuth2 - Avision</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
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
        <h1>🔐 Diagnostic OAuth2</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        try {
            $config = Config::getInstance();
            
            // Test 1: Configuration OAuth2
            echo '<div class="test-section">';
            echo '<h2>🔧 Configuration OAuth2</h2>';
            
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
            echo '</div>';

            // Test 2: Configuration SMTP
            echo '<div class="test-section">';
            echo '<h2>📧 Configuration SMTP</h2>';
            
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
            echo '</div>';

            // Test 3: Test de connexion SMTP simple
            echo '<div class="test-section">';
            echo '<h2>🔌 Test de Connexion SMTP</h2>';
            
            $host = $smtpConfig['host'] ?: 'smtp.office365.com';
            $port = $smtpConfig['port'] ?: '587';
            
            echo "<div class='info'>Test de connexion vers $host:$port</div>";
            
            $startTime = microtime(true);
            $socket = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 10);
            $endTime = microtime(true);
            
            if ($socket) {
                $response = fgets($socket, 1024);
                fclose($socket);
                $time = round(($endTime - $startTime) * 1000, 2);
                
                if (preg_match('/^220/', $response)) {
                    echo "<div class='success'>";
                    echo "✅ Connexion SMTP réussie en {$time}ms<br>";
                    echo "Réponse serveur : <code>" . trim($response) . "</code>";
                    echo "</div>";
                } else {
                    echo "<div class='error'>";
                    echo "❌ Réponse serveur invalide : <code>" . trim($response) . "</code>";
                    echo "</div>";
                }
            } else {
                echo "<div class='error'>";
                echo "❌ Impossible de se connecter : $errstr (Code: $errno)";
                echo "</div>";
            }
            echo '</div>';

            // Test 4: Test d'envoi d'email
            echo '<div class="test-section">';
            echo '<h2>📤 Test d\'Envoi d\'Email</h2>';
            
            if ($oauth2Config['enabled'] == '1') {
                echo "<div class='info'>OAuth2 activé - Test d'envoi via OAuth2</div>";
                
                // Charger MailService
                require_once 'classes/MailService.php';
                global $db;
                $mailService = new MailService($db);
                
                $testEmail = 'test@example.com';
                $subject = "Test OAuth2 - " . date('Y-m-d H:i:s');
                $body = "Ceci est un test d'envoi d'email via OAuth2.\n\nDate: " . date('Y-m-d H:i:s') . "\nServeur: " . $_SERVER['SERVER_NAME'];
                
                try {
                    $result = $mailService->sendTestEmail($testEmail, $subject, $body);
                    if ($result) {
                        echo "<div class='success'>✅ Email de test envoyé avec succès</div>";
                    } else {
                        echo "<div class='error'>❌ Échec de l'envoi de l'email de test</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Erreur lors de l'envoi : " . $e->getMessage() . "</div>";
                }
            } else {
                echo "<div class='warning'>OAuth2 désactivé - Test d'envoi via SMTP classique</div>";
            }
            echo '</div>';

        } catch (Exception $e) {
            echo "<div class='error'>Erreur : " . $e->getMessage() . "</div>";
        }
        ?>

        <div class="test-section warning">
            <h2>⚠️ Important</h2>
            <p><strong>Supprimez ce fichier après diagnostic.</strong></p>
        </div>
    </div>
</body>
</html>
