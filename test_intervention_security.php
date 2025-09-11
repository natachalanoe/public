<?php
/**
 * Script de test pour vérifier la sécurité des interventions
 * Teste que l'utilisateur 16 (client) ne voit que les interventions du client 35
 */

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DÉBUT DU TEST ===\n";

try {
    // Inclure les fichiers nécessaires
    require_once __DIR__ . '/includes/init.php';
    echo "✅ init.php chargé avec succès\n";
    
    require_once __DIR__ . '/includes/functions.php';
    echo "✅ functions.php chargé avec succès\n";
    
    // Simuler la connexion de l'utilisateur 16
    $_SESSION['user'] = [
        'id' => 16,
        'username' => 'test_user_16',
        'type' => 'client',
        'client_id' => 35
    ];
    
    echo "✅ Session simulée pour l'utilisateur 16\n";
    
    // Récupérer les localisations de l'utilisateur
    $userLocations = getUserLocations();
    echo "✅ Localisations récupérées :\n";
    print_r($userLocations);
    
    // Tester le modèle InterventionsClientModel
    require_once __DIR__ . '/models/InterventionsClientModel.php';
    $model = new InterventionsClientModel($db);
    echo "✅ Modèle InterventionsClientModel chargé\n";
    
    echo "\n=== TEST DES INTERVENTIONS ===\n";
    
    // Récupérer toutes les interventions accessibles
    $interventions = $model->getAllByLocations($userLocations);
    echo "Nombre d'interventions accessibles : " . count($interventions) . "\n";
    
    if (!empty($interventions)) {
        echo "\nDétail des interventions :\n";
        foreach ($interventions as $intervention) {
            echo "- ID: {$intervention['id']}, Référence: {$intervention['reference']}, Client: {$intervention['client_name']} (ID: {$intervention['client_id']})\n";
        }
    } else {
        echo "Aucune intervention trouvée.\n";
    }
    
    // Vérifier qu'il n'y a que des interventions du client 35
    $wrongClientInterventions = array_filter($interventions, function($intervention) {
        return $intervention['client_id'] != 35;
    });
    
    if (!empty($wrongClientInterventions)) {
        echo "\n❌ ERREUR DE SÉCURITÉ : L'utilisateur voit des interventions d'autres clients !\n";
        foreach ($wrongClientInterventions as $intervention) {
            echo "- Intervention ID {$intervention['id']} du client {$intervention['client_name']} (ID: {$intervention['client_id']})\n";
        }
    } else {
        echo "\n✅ SÉCURITÉ OK : L'utilisateur ne voit que ses propres interventions.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR : " . $e->getMessage() . "\n";
    echo "Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "\n";
    echo "Trace :\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ ERREUR FATALE : " . $e->getMessage() . "\n";
    echo "Fichier : " . $e->getFile() . " ligne " . $e->getLine() . "\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
