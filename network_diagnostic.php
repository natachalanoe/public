<?php
/**
 * Script de diagnostic réseau pour OAuth2 Azure AD
 * À uploader sur le serveur de production pour diagnostiquer les problèmes de connectivité
 */

// Configuration de sécurité - À supprimer après diagnostic
$ALLOWED_IPS = [
    '127.0.0.1',
    '::1',
    // Ajoutez votre IP publique ici si nécessaire
];

// Vérification de sécurité basique
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!in_array($clientIP, $ALLOWED_IPS) && !isset($_GET['force'])) {
    die("Accès restreint. Ajoutez ?force=1 à l'URL pour forcer l'accès (supprimez ce fichier après diagnostic)");
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Réseau OAuth2 - Avision</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Diagnostic Réseau OAuth2 - Avision</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>IP :</strong> <?= $_SERVER['SERVER_ADDR'] ?? 'Inconnue' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        // Fonction pour exécuter une commande système
        function runCommand($command, $description) {
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            return [
                'command' => $command,
                'description' => $description,
                'output' => $output,
                'return_code' => $returnCode,
                'success' => $returnCode === 0
            ];
        }

        // Fonction pour tester une URL avec curl
        function testCurl($url, $description, $options = []) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Avision-Diagnostic/1.0');
            
            // Appliquer les options personnalisées
            foreach ($options as $option => $value) {
                curl_setopt($ch, $option, $value);
            }
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            return [
                'url' => $url,
                'description' => $description,
                'response' => $response,
                'http_code' => $httpCode,
                'error' => $error,
                'info' => $info,
                'response_time' => round(($endTime - $startTime) * 1000, 2),
                'success' => empty($error) && $httpCode >= 200 && $httpCode < 400
            ];
        }

        // 1. Informations système
        echo '<div class="test-section info">';
        echo '<h2>📋 Informations Système</h2>';
        echo '<div class="grid">';
        echo '<div><strong>PHP Version :</strong> ' . PHP_VERSION . '</div>';
        echo '<div><strong>OS :</strong> ' . PHP_OS . '</div>';
        echo '<div><strong>Extensions chargées :</strong></div>';
        echo '<div class="code">';
        $extensions = ['curl', 'openssl', 'json', 'mbstring', 'xml'];
        foreach ($extensions as $ext) {
            $status = extension_loaded($ext) ? '✅' : '❌';
            echo "$status $ext<br>";
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // 2. Test de résolution DNS
        echo '<div class="test-section">';
        echo '<h2>🌐 Test de Résolution DNS</h2>';
        
        $dnsTests = [
            'login.microsoftonline.com',
            'graph.microsoft.com',
            'outlook.office365.com',
            'smtp.office365.com'
        ];
        
        foreach ($dnsTests as $host) {
            $result = runCommand("nslookup $host", "Résolution DNS pour $host");
            $class = $result['success'] ? 'success' : 'error';
            echo "<div class='$class'>";
            echo "<strong>$host :</strong> ";
            if ($result['success']) {
                echo "✅ Résolu avec succès<br>";
                echo "<div class='code'>" . implode('<br>', array_slice($result['output'], 0, 5)) . "</div>";
            } else {
                echo "❌ Échec de résolution<br>";
                echo "<div class='code'>" . implode('<br>', $result['output']) . "</div>";
            }
            echo "</div>";
        }
        echo '</div>';

        // 3. Test de connectivité réseau
        echo '<div class="test-section">';
        echo '<h2>🔌 Test de Connectivité Réseau</h2>';
        
        $pingTests = [
            'login.microsoftonline.com',
            'graph.microsoft.com'
        ];
        
        foreach ($pingTests as $host) {
            $result = runCommand("ping -c 3 $host", "Ping vers $host");
            $class = $result['success'] ? 'success' : 'error';
            echo "<div class='$class'>";
            echo "<strong>Ping $host :</strong> ";
            if ($result['success']) {
                echo "✅ Connectivité OK<br>";
                // Extraire le temps de réponse
                $output = implode(' ', $result['output']);
                if (preg_match('/time=([0-9.]+)/', $output, $matches)) {
                    echo "Temps de réponse : {$matches[1]}ms<br>";
                }
            } else {
                echo "❌ Pas de connectivité<br>";
            }
            echo "</div>";
        }
        echo '</div>';

        // 4. Test des ports HTTPS
        echo '<div class="test-section">';
        echo '<h2>🔒 Test des Ports HTTPS</h2>';
        
        $portTests = [
            ['login.microsoftonline.com', 443, 'Port HTTPS Microsoft'],
            ['graph.microsoft.com', 443, 'Port HTTPS Graph API'],
            ['outlook.office365.com', 443, 'Port HTTPS Outlook'],
            ['smtp.office365.com', 587, 'Port SMTP TLS'],
            ['smtp.office365.com', 465, 'Port SMTP SSL']
        ];
        
        foreach ($portTests as $test) {
            list($host, $port, $description) = $test;
            $result = runCommand("timeout 5 bash -c '</dev/tcp/$host/$port'", "Test port $port sur $host");
            $class = $result['success'] ? 'success' : 'error';
            echo "<div class='$class'>";
            echo "<strong>$description ($host:$port) :</strong> ";
            echo $result['success'] ? '✅ Port ouvert' : '❌ Port fermé/bloqué';
            echo "</div>";
        }
        echo '</div>';

        // 5. Test des endpoints OAuth2
        echo '<div class="test-section">';
        echo '<h2>🔐 Test des Endpoints OAuth2</h2>';
        
        $oauth2Tests = [
            [
                'https://login.microsoftonline.com/common/.well-known/openid_configuration',
                'Endpoint de découverte (tenant commun)'
            ],
            [
                'https://login.microsoftonline.com/edf5f39b-784b-48ed-b8c4-982f43d535f1/.well-known/openid_configuration',
                'Endpoint de découverte (tenant client)'
            ],
            [
                'https://graph.microsoft.com/v1.0/me',
                'Graph API (nécessite authentification)'
            ]
        ];
        
        foreach ($oauth2Tests as $test) {
            list($url, $description) = $test;
            $result = testCurl($url, $description);
            $class = $result['success'] ? 'success' : 'error';
            echo "<div class='$class'>";
            echo "<strong>$description :</strong><br>";
            echo "URL : <code>$url</code><br>";
            echo "Code HTTP : {$result['http_code']}<br>";
            echo "Temps de réponse : {$result['response_time']}ms<br>";
            
            if (!empty($result['error'])) {
                echo "Erreur cURL : <code>{$result['error']}</code><br>";
            }
            
            if ($result['success'] && !empty($result['response'])) {
                $json = json_decode($result['response'], true);
                if ($json) {
                    echo "Réponse JSON valide ✅<br>";
                    if (isset($json['authorization_endpoint'])) {
                        echo "Endpoint d'autorisation trouvé ✅<br>";
                    }
                    if (isset($json['token_endpoint'])) {
                        echo "Endpoint de token trouvé ✅<br>";
                    }
                } else {
                    echo "Réponse non-JSON ou invalide<br>";
                }
            }
            echo "</div>";
        }
        echo '</div>';

        // 6. Test de configuration cURL
        echo '<div class="test-section">';
        echo '<h2>⚙️ Configuration cURL</h2>';
        
        $curlInfo = curl_version();
        echo "<div class='info'>";
        echo "<strong>Version cURL :</strong> {$curlInfo['version']}<br>";
        echo "<strong>Version SSL :</strong> {$curlInfo['ssl_version']}<br>";
        echo "<strong>Protocoles supportés :</strong> " . implode(', ', $curlInfo['protocols']) . "<br>";
        echo "<strong>Fonctionnalités :</strong> " . implode(', ', $curlInfo['features']) . "<br>";
        echo "</div>";
        echo '</div>';

        // 7. Test de configuration PHP
        echo '<div class="test-section">';
        echo '<h2>🐘 Configuration PHP</h2>';
        
        $phpConfig = [
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'user_agent' => ini_get('user_agent'),
            'default_socket_timeout' => ini_get('default_socket_timeout'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
        
        echo "<div class='info'>";
        foreach ($phpConfig as $key => $value) {
            $status = '';
            if ($key === 'allow_url_fopen' && $value) $status = ' ✅';
            if ($key === 'default_socket_timeout' && $value >= 60) $status = ' ✅';
            echo "<strong>$key :</strong> $value$status<br>";
        }
        echo "</div>";
        echo '</div>';

        // 8. Test de votre configuration OAuth2 actuelle
        echo '<div class="test-section">';
        echo '<h2>🔧 Configuration OAuth2 Actuelle</h2>';
        
        // Essayer de charger la configuration
        try {
            if (file_exists('../../config/config.php')) {
                require_once '../../config/config.php';
                $config = Config::getInstance();
                
                $oauth2Config = [
                    'client_id' => $config->get('oauth2_client_id', 'Non configuré'),
                    'tenant_id' => $config->get('oauth2_tenant_id', 'Non configuré'),
                    'redirect_uri' => $config->get('oauth2_redirect_uri', 'Non configuré'),
                    'client_secret' => $config->get('oauth2_client_secret', 'Non configuré') ? 'Configuré' : 'Non configuré'
                ];
                
                echo "<div class='info'>";
                foreach ($oauth2Config as $key => $value) {
                    $status = ($value !== 'Non configuré') ? ' ✅' : ' ❌';
                    echo "<strong>$key :</strong> $value$status<br>";
                }
                echo "</div>";
                
                // Test avec la configuration actuelle
                if ($oauth2Config['tenant_id'] !== 'Non configuré') {
                    $testUrl = "https://login.microsoftonline.com/{$oauth2Config['tenant_id']}/.well-known/openid_configuration";
                    $result = testCurl($testUrl, "Test avec votre tenant ID actuel");
                    $class = $result['success'] ? 'success' : 'error';
                    echo "<div class='$class'>";
                    echo "<strong>Test avec votre configuration :</strong><br>";
                    echo "URL : <code>$testUrl</code><br>";
                    echo "Code HTTP : {$result['http_code']}<br>";
                    echo "Temps de réponse : {$result['response_time']}ms<br>";
                    if (!empty($result['error'])) {
                        echo "Erreur : <code>{$result['error']}</code><br>";
                    }
                    echo "</div>";
                }
            } else {
                echo "<div class='warning'>Fichier de configuration non trouvé</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Erreur lors du chargement de la configuration : " . $e->getMessage() . "</div>";
        }
        echo '</div>';

        // 9. Recommandations
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations</h2>';
        echo '<ul>';
        echo '<li><strong>Sécurité :</strong> Supprimez ce fichier après diagnostic</li>';
        echo '<li><strong>Firewall :</strong> Vérifiez que les ports 443 et 587 sont ouverts</li>';
        echo '<li><strong>DNS :</strong> Vérifiez la configuration DNS de votre serveur</li>';
        echo '<li><strong>Proxy :</strong> Si vous utilisez un proxy, configurez cURL en conséquence</li>';
        echo '<li><strong>SSL :</strong> Vérifiez que les certificats SSL sont à jour</li>';
        echo '</ul>';
        echo '</div>';
        ?>

        <div class="test-section info">
            <h2>🔄 Actions</h2>
            <button class="btn" onclick="location.reload()">🔄 Actualiser les tests</button>
            <button class="btn" onclick="window.print()">🖨️ Imprimer le rapport</button>
            <a href="?force=1" class="btn">🔓 Mode diagnostic complet</a>
        </div>

        <div class="test-section warning">
            <h2>⚠️ Important</h2>
            <p><strong>Ce fichier contient des informations sensibles sur votre serveur.</strong></p>
            <p><strong>Supprimez-le immédiatement après avoir terminé le diagnostic.</strong></p>
        </div>
    </div>
</body>
</html>
