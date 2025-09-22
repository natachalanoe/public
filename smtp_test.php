<?php
/**
 * Script de test SMTP pour diagnostiquer les problèmes de ports
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
    <title>Test SMTP - Avision</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test SMTP - Diagnostic des Ports</h1>
        
        <?php
        function testSmtpPort($host, $port, $description) {
            $startTime = microtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);
            $endTime = microtime(true);
            
            if ($socket) {
                fclose($socket);
                $responseTime = round(($endTime - $startTime) * 1000, 2);
                return [
                    'success' => true,
                    'message' => "Port $port ouvert - Temps: {$responseTime}ms",
                    'response_time' => $responseTime
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Port $port fermé - Erreur: $errstr (Code: $errno)",
                    'error' => $errstr,
                    'error_code' => $errno
                ];
            }
        }

        function testSmtpConnection($host, $port, $description) {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $protocol = ($port == 465) ? 'ssl://' : 'tcp://';
            $address = $protocol . $host . ':' . $port;
            
            $startTime = microtime(true);
            $socket = @stream_socket_client($address, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            $endTime = microtime(true);
            
            if ($socket) {
                $response = fgets($socket, 1024);
                fclose($socket);
                $responseTime = round(($endTime - $startTime) * 1000, 2);
                
                return [
                    'success' => true,
                    'message' => "Connexion SMTP réussie - Temps: {$responseTime}ms",
                    'response' => trim($response),
                    'response_time' => $responseTime
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Connexion SMTP échouée - Erreur: $errstr (Code: $errno)",
                    'error' => $errstr,
                    'error_code' => $errno
                ];
            }
        }

        // Test des ports SMTP
        $smtpTests = [
            ['smtp.office365.com', 587, 'SMTP TLS (Office 365)'],
            ['smtp.office365.com', 465, 'SMTP SSL (Office 365)'],
            ['smtp.gmail.com', 587, 'SMTP TLS (Gmail)'],
            ['smtp.gmail.com', 465, 'SMTP SSL (Gmail)'],
            ['smtp.orange.fr', 587, 'SMTP TLS (Orange)'],
            ['smtp.free.fr', 587, 'SMTP TLS (Free)']
        ];

        echo '<div class="test-section">';
        echo '<h2>🔌 Test des Ports SMTP</h2>';
        
        foreach ($smtpTests as $test) {
            list($host, $port, $description) = $test;
            $result = testSmtpPort($host, $port, $description);
            $class = $result['success'] ? 'success' : 'error';
            
            echo "<div class='$class'>";
            echo "<strong>$description ($host:$port) :</strong><br>";
            echo $result['message'];
            echo "</div>";
        }
        echo '</div>';

        // Test des connexions SMTP
        echo '<div class="test-section">';
        echo '<h2>📧 Test des Connexions SMTP</h2>';
        
        $connectionTests = [
            ['smtp.office365.com', 587, 'Connexion SMTP TLS Office 365'],
            ['smtp.office365.com', 465, 'Connexion SMTP SSL Office 365']
        ];
        
        foreach ($connectionTests as $test) {
            list($host, $port, $description) = $test;
            $result = testSmtpConnection($host, $port, $description);
            $class = $result['success'] ? 'success' : 'error';
            
            echo "<div class='$class'>";
            echo "<strong>$description :</strong><br>";
            echo $result['message'];
            if (isset($result['response'])) {
                echo "<br>Réponse serveur : <code>{$result['response']}</code>";
            }
            echo "</div>";
        }
        echo '</div>';

        // Test de résolution DNS SMTP
        echo '<div class="test-section">';
        echo '<h2>🌐 Test DNS SMTP</h2>';
        
        $dnsTests = [
            'smtp.office365.com',
            'outlook.office365.com',
            'smtp.gmail.com'
        ];
        
        foreach ($dnsTests as $host) {
            $ips = gethostbynamel($host);
            if ($ips) {
                echo "<div class='success'>";
                echo "<strong>$host :</strong> ✅ Résolu<br>";
                echo "IPs : " . implode(', ', $ips);
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>$host :</strong> ❌ Non résolu";
                echo "</div>";
            }
        }
        echo '</div>';

        // Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations</h2>';
        echo '<ul>';
        echo '<li><strong>Ports fermés :</strong> Contactez votre hébergeur pour ouvrir les ports 587 et 465</li>';
        echo '<li><strong>Firewall :</strong> Vérifiez la configuration du firewall serveur</li>';
        echo '<li><strong>Alternative :</strong> Utilisez un service SMTP relais (SendGrid, Mailgun, etc.)</li>';
        echo '<li><strong>OAuth2 :</strong> Si SMTP ne fonctionne pas, OAuth2 sera votre seule option</li>';
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
