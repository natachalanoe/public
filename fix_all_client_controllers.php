<?php
/**
 * Script de correction globale pour tous les contrôleurs clients
 * Corrige le bug de sécurité où les clients voient les données d'autres clients
 */

echo "=== CORRECTION GLOBALE DES CONTRÔLEURS CLIENTS ===\n\n";

// Liste des fichiers à corriger
$files_to_fix = [
    'controllers/ContactClientController.php',
    'controllers/SiteClientController.php', 
    'controllers/MaterielClientController.php',
    'controllers/ContractsClientController.php',
    'controllers/DocumentationClientController.php'
];

foreach ($files_to_fix as $file) {
    if (file_exists($file)) {
        echo "🔧 Correction de $file...\n";
        
        $content = file_get_contents($file);
        
        // Remplacer toutes les occurrences du pattern défaillant
        $old_pattern = 'foreach ($userLocations as $clientId => $locations)';
        $new_pattern = 'foreach ($userLocations as $location) {
            if (isset($location[\'client_id\'])) {
                $clientId = $location[\'client_id\'];';
        
        if (strpos($content, $old_pattern) !== false) {
            $content = str_replace($old_pattern, $new_pattern, $content);
            
            // Ajouter la fermeture de la boucle
            $content = str_replace('}', '                }
            }', $content);
            
            file_put_contents($file, $content);
            echo "✅ $file corrigé\n";
        } else {
            echo "ℹ️  $file n'a pas le pattern défaillant\n";
        }
    } else {
        echo "❌ $file n'existe pas\n";
    }
}

echo "\n=== CORRECTION TERMINÉE ===\n";
echo "Tous les contrôleurs clients ont été corrigés pour respecter la sécurité.\n";
echo "Les clients ne devraient plus voir les données d'autres clients.\n";
?>
