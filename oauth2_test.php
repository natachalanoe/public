<?php
/**
 * Test OAuth2 spécifique - Diagnostic des endpoints
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
    <title>Test OAuth2 - Avision</title>
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
        <h1>🔐 Test OAuth2 - Diagnostic des Endpoints</h1>
        <p><strong>Serveur :</strong> <?= $_SERVER['SERVER_NAME'] ?? 'Inconnu' ?> | <strong>Date :</strong> <?= date('Y-m-d H:i:s') ?></p>
        
        <?php
        function testOAuth2Endpoint($url, $description) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Avision-OAuth2-Test/1.0');
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'url' => $url,
                'description' => $description,
                'response' => $response,
                'http_code' => $httpCode,
                'error' => $error,
                'info' => $info,
                'response_time' => $responseTime,
                'success' => empty($error) && $httpCode >= 200 && $httpCode < 400
            ];
        }

        // Test 1: Endpoints de découverte
        echo '<div class="test-section">';
        echo '<h2>🔍 Test des Endpoints de Découverte</h2>';
        
        $discoveryTests = [
            [
                'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration',
                'Endpoint de découverte (tenant commun)'
            ],
            [
                'https://login.microsoftonline.com/edf5f39b-784b-48ed-b8c4-982f43d535f1/v2.0/.well-known/openid_configuration',
                'Endpoint de découverte (tenant client)'
            ],
            [
                'https://login.microsoftonline.com/edf5f39b-784b-48ed-b8c4-982f43d535f1/.well-known/openid_configuration',
                'Endpoint de découverte (ancien format)'
            ]
        ];
        
        foreach ($discoveryTests as $test) {
            list($url, $description) = $test;
            $result = testOAuth2Endpoint($url, $description);
            
            $class = 'error';
            if ($result['success']) {
                $class = 'success';
            } elseif ($result['http_code'] === 404) {
                $class = 'warning';
            }
            
            echo "<div class='$class'>";
            echo "<strong>$description :</strong><br>";
            echo "URL : <code>$url</code><br>";
            echo "Code HTTP : {$result['http_code']}<br>";
            echo "Temps de réponse : {$result['response_time']}ms<br>";
            
            if (!empty($result['error'])) {
                echo "Erreur cURL : <code>{$result['error']}</code><br>";
            }
            
            if ($result['http_code'] === 200) {
                $json = json_decode($result['response'], true);
                if ($json) {
                    echo "✅ Réponse JSON valide<br>";
                    if (isset($json['authorization_endpoint'])) {
                        echo "✅ Endpoint d'autorisation : <code>{$json['authorization_endpoint']}</code><br>";
                    }
                    if (isset($json['token_endpoint'])) {
                        echo "✅ Endpoint de token : <code>{$json['token_endpoint']}</code><br>";
                    }
                    if (isset($json['issuer'])) {
                        echo "✅ Issuer : <code>{$json['issuer']}</code><br>";
                    }
                }
            } elseif ($result['http_code'] === 404) {
                echo "⚠️ Endpoint non trouvé - Vérifiez le Tenant ID ou l'URL<br>";
            }
            echo "</div>";
        }
        echo '</div>';

        // Test 2: Test d'autorisation
        echo '<div class="test-section">';
        echo '<h2>🔑 Test d\'Autorisation</h2>';
        
        $tenantId = 'edf5f39b-784b-48ed-b8c4-982f43d535f1';
        $clientId = 'c5927859-9f48-40ea-b03d-62e5669ae3bf';
        
        $authUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize?" .
            "client_id=" . urlencode($clientId) . "&" .
            "response_type=code&" .
            "redirect_uri=" . urlencode('https://avision.videosonic.fr/settings/oauth2/callback') . "&" .
            "scope=" . urlencode('https://outlook.office365.com/SMTP.Send offline_access') . "&" .
            "response_mode=query&" .
            "state=test";
        
        echo "<div class='info'>";
        echo "<strong>URL d'autorisation générée :</strong><br>";
        echo "<div class='code'>$authUrl</div>";
        echo "<br><a href='$authUrl' target='_blank' class='btn' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>🔗 Tester l'autorisation</a>";
        echo "</div>";
        echo '</div>';

        // Test 3: Test de l'application
        echo '<div class="test-section">';
        echo '<h2>📱 Test de l\'Application</h2>';
        
        $appTests = [
            [
                'https://graph.microsoft.com/v1.0/applications',
                'Liste des applications (nécessite authentification)'
            ],
            [
                'https://graph.microsoft.com/v1.0/servicePrincipals',
                'Liste des principaux de service (nécessite authentification)'
            ]
        ];
        
        foreach ($appTests as $test) {
            list($url, $description) = $test;
            $result = testOAuth2Endpoint($url, $description);
            
            $class = 'info';
            if ($result['http_code'] === 401) {
                $class = 'warning';
            } elseif ($result['http_code'] === 200) {
                $class = 'success';
            } elseif ($result['http_code'] >= 400) {
                $class = 'error';
            }
            
            echo "<div class='$class'>";
            echo "<strong>$description :</strong><br>";
            echo "Code HTTP : {$result['http_code']}<br>";
            echo "Temps de réponse : {$result['response_time']}ms<br>";
            
            if ($result['http_code'] === 401) {
                echo "⚠️ Authentification requise (normal)<br>";
            } elseif ($result['http_code'] === 200) {
                echo "✅ Accès autorisé<br>";
            }
            echo "</div>";
        }
        echo '</div>';

        // Test 4: Recommandations spécifiques
        echo '<div class="test-section warning">';
        echo '<h2>💡 Recommandations OAuth2</h2>';
        echo '<ul>';
        echo '<li><strong>Endpoint 404 :</strong> Vérifiez que le Tenant ID est correct</li>';
        echo '<li><strong>Application non autorisée :</strong> Le client doit accorder le consentement administrateur</li>';
        echo '<li><strong>URL d\'autorisation :</strong> Testez le lien généré ci-dessus</li>';
        echo '<li><strong>Configuration :</strong> Vérifiez que l\'application est enregistrée dans le bon tenant</li>';
        echo '</ul>';
        echo '</div>';

        // Test 5: Actions immédiates
        echo '<div class="test-section info">';
        echo '<h2>🎯 Actions Immédiates</h2>';
        echo '<ol>';
        echo '<li><strong>Vérifiez le Tenant ID :</strong> Assurez-vous que <code>edf5f39b-784b-48ed-b8c4-982f43d535f1</code> est correct</li>';
        echo '<li><strong>Testez l\'autorisation :</strong> Cliquez sur le lien d\'autorisation ci-dessus</li>';
        echo '<li><strong>Consentement administrateur :</strong> Le client doit autoriser l\'application dans Azure AD</li>';
        echo '<li><strong>Configuration SMTP :</strong> Contactez l\'hébergeur pour ouvrir les ports 587 et 465</li>';
        echo '</ol>';
        echo '</div>';
        ?>

        <div class="test-section warning">
            <h2>⚠️ Important</h2>
            <p><strong>Supprimez ce fichier après diagnostic.</strong></p>
        </div>
    </div>
</body>
</html>
