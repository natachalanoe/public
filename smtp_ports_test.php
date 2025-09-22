<?php
/**
 * Script de test des ports SMTP - Diagnostic spécifique
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
    <title>Test Ports SMTP - Avision</title>
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
        <h1>🔌 Test des Ports SMTP - Diagnostic Spécifique</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        function testSmtpPort($host, $port, $description, $timeout = 10) {
            $startTime = microtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
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

        function testSmtpConnection($host, $port, $description, $timeout = 30) {
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
            $socket = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
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

        function testSmtpHandshake($host, $port, $description) {
            try {
                $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, 30);
                if (!$socket) {
                    return [
                        'success' => false,
                        'message' => "Impossible de se connecter: $errstr"
                    ];
                }

                $steps = [];
                
                // 1. Lire la réponse initiale
                $response = fgets($socket, 1024);
                $steps[] = "1. Réponse initiale: " . trim($response);
                
                if (!preg_match('/^220/', $response)) {
                    fclose($socket);
                    return [
                        'success' => false,
                        'message' => "Réponse invalide du serveur: " . trim($response),
                        'steps' => $steps
                    ];
                }

                // 2. EHLO
                fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                $response = fgets($socket, 1024);
                $steps[] = "2. EHLO: " . trim($response);

                // 3. STARTTLS (si port 587)
                if ($port == 587) {
                    fwrite($socket, "STARTTLS\r\n");
                    $response = fgets($socket, 1024);
                    $steps[] = "3. STARTTLS: " . trim($response);
                    
                    if (preg_match('/^220/', $response)) {
                        if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                            $steps[] = "4. TLS activé avec succès";
                            
                            // EHLO après TLS
                            fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                            $response = fgets($socket, 1024);
                            $steps[] = "5. EHLO après TLS: " . trim($response);
                        } else {
                            $steps[] = "4. Échec de l'activation TLS";
                        }
                    }
                }

                fclose($socket);
                
                return [
                    'success' => true,
                    'message' => "Handshake SMTP réussi",
                    'steps' => $steps
                ];

            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => "Erreur lors du handshake: " . $e->getMessage()
                ];
            }
        }

        // Test 1: Ports SMTP Office 365
        echo '<div class="test-section">';
        echo '<h2>🔌 Test des Ports SMTP Office 365</h2>';
        
        $smtpTests = [
            ['smtp.office365.com', 587, 'SMTP TLS (Office 365)'],
            ['smtp.office365.com', 465, 'SMTP SSL (Office 365)'],
            ['smtp.office365.com', 25, 'SMTP Standard (Office 365)']
        ];
        
        foreach ($smtpTests as $test) {
            list($host, $port, $description) = $test;
            $result = testSmtpPort($host, $port, $description, 15);
            $class = $result['success'] ? 'success' : 'error';
            
            echo "<div class='$class'>";
            echo "<strong>$description ($host:$port) :</strong><br>";
            echo $result['message'];
            echo "</div>";
        }
        echo '</div>';

        // Test 2: Connexions SMTP détaillées
        echo '<div class="test-section">';
        echo '<h2>📧 Test des Connexions SMTP Détaillées</h2>';
        
        $connectionTests = [
            ['smtp.office365.com', 587, 'Connexion SMTP TLS Office 365'],
            ['smtp.office365.com', 465, 'Connexion SMTP SSL Office 365']
        ];
        
        foreach ($connectionTests as $test) {
            list($host, $port, $description) = $test;
            $result = testSmtpConnection($host, $port, $description, 30);
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

        // Test 3: Handshake SMTP complet
        echo '<div class="test-section">';
        echo '<h2>🤝 Test du Handshake SMTP Complet</h2>';
        
        $handshakeTests = [
            ['smtp.office365.com', 587, 'Handshake SMTP TLS Office 365'],
            ['smtp.office365.com', 465, 'Handshake SMTP SSL Office 365']
        ];
        
        foreach ($handshakeTests as $test) {
            list($host, $port, $description) = $test;
            $result = testSmtpHandshake($host, $port, $description);
            $class = $result['success'] ? 'success' : 'error';
            
            echo "<div class='$class'>";
            echo "<strong>$description :</strong><br>";
            echo $result['message'];
            if (isset($result['steps'])) {
                echo "<br><strong>Étapes :</strong><br>";
                echo "<div class='code'>";
                foreach ($result['steps'] as $step) {
                    echo "$step<br>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
        echo '</div>';

        // Test 4: Résolution DNS
        echo '<div class="test-section">';
        echo '<h2>🌐 Test de Résolution DNS SMTP</h2>';
        
        $dnsTests = [
            'smtp.office365.com',
            'outlook.office365.com',
            'graph.microsoft.com'
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

        // Test 5: Test de connectivité réseau
        echo '<div class="test-section">';
        echo '<h2>🔗 Test de Connectivité Réseau</h2>';
        
        $pingTests = [
            'smtp.office365.com',
            'outlook.office365.com'
        ];
        
        foreach ($pingTests as $host) {
            $result = shell_exec("ping -c 3 $host 2>&1");
            if (strpos($result, 'time=') !== false) {
                echo "<div class='success'>";
                echo "<strong>Ping $host :</strong> ✅ Connectivité OK<br>";
                echo "<div class='code'>" . htmlspecialchars($result) . "</div>";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>Ping $host :</strong> ❌ Pas de connectivité<br>";
                echo "<div class='code'>" . htmlspecialchars($result) . "</div>";
                echo "</div>";
            }
        }
        echo '</div>';

        // Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations</h2>';
        echo '<ul>';
        echo '<li><strong>Ports ouverts :</strong> Si les ports sont ouverts mais les connexions échouent, vérifiez le firewall applicatif</li>';
        echo '<li><strong>Handshake :</strong> Le handshake SMTP doit fonctionner pour OAuth2</li>';
        echo '<li><strong>DNS :</strong> Vérifiez que la résolution DNS fonctionne correctement</li>';
        echo '<li><strong>Timeout :</strong> Les timeouts peuvent indiquer des problèmes de réseau</li>';
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
