<?php
/**
 * Script de test pour le système d'emails
 * À utiliser uniquement en développement
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/classes/MailService.php';

// Vérifier que nous sommes en mode développement
if (!defined('DEBUG') || !DEBUG) {
    die("Ce script ne peut être exécuté qu'en mode développement.");
}

echo "<h1>Test du système d'emails</h1>";

try {
    // Initialiser le service email
    $mailService = new MailService($db);
    
    // Récupérer la configuration actuelle
    $config = Config::getInstance();
    
    echo "<h2>Configuration actuelle :</h2>";
    echo "<ul>";
    echo "<li><strong>SMTP Host:</strong> " . ($config->get('mail_host') ?: 'Non configuré') . "</li>";
    echo "<li><strong>SMTP Port:</strong> " . ($config->get('mail_port', '587') . "</li>";
    echo "<li><strong>From Address:</strong> " . ($config->get('mail_from_address') ?: 'Non configuré') . "</li>";
    echo "<li><strong>From Name:</strong> " . ($config->get('mail_from_name') ?: 'Non configuré') . "</li>";
    echo "<li><strong>Test Email:</strong> " . ($config->get('test_email') ?: 'Non configuré') . "</li>";
    echo "<li><strong>Auto Send Creation:</strong> " . ($config->get('email_auto_send_creation', '0') == '1' ? 'Activé' : 'Désactivé') . "</li>";
    echo "<li><strong>Auto Send Closing:</strong> " . ($config->get('email_auto_send_closing', '0') == '1' ? 'Activé' : 'Désactivé') . "</li>";
    echo "</ul>";
    
    // Vérifier les templates
    echo "<h2>Templates disponibles :</h2>";
    $stmt = $db->query("SELECT * FROM mail_templates ORDER BY template_type, name");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($templates)) {
        echo "<p style='color: orange;'>Aucun template configuré.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Nom</th><th>Type</th><th>Actif</th><th>Sujet</th></tr>";
        foreach ($templates as $template) {
            echo "<tr>";
            echo "<td>" . h($template['name']) . "</td>";
            echo "<td>" . h($template['template_type']) . "</td>";
            echo "<td>" . ($template['is_active'] ? 'Oui' : 'Non') . "</td>";
            echo "<td>" . h($template['subject']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Vérifier l'historique des emails
    echo "<h2>Historique des emails (derniers 10) :</h2>";
    $stmt = $db->query("SELECT mh.*, mt.name as template_name, i.reference as intervention_reference 
                       FROM mail_history mh 
                       LEFT JOIN mail_templates mt ON mh.template_id = mt.id 
                       LEFT JOIN interventions i ON mh.intervention_id = i.id 
                       ORDER BY mh.created_at DESC LIMIT 10");
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($history)) {
        echo "<p style='color: blue;'>Aucun email envoyé pour le moment.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Date</th><th>Template</th><th>Intervention</th><th>Destinataire</th><th>Statut</th></tr>";
        foreach ($history as $email) {
            echo "<tr>";
            echo "<td>" . date('d/m/Y H:i', strtotime($email['created_at'])) . "</td>";
            echo "<td>" . h($email['template_name']) . "</td>";
            echo "<td>" . h($email['intervention_reference']) . "</td>";
            echo "<td>" . h($email['recipient_email']) . "</td>";
            echo "<td style='color: " . ($email['status'] == 'sent' ? 'green' : ($email['status'] == 'failed' ? 'red' : 'orange')) . ";'>" . h($email['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test de récupération d'une intervention
    echo "<h2>Test de récupération d'intervention :</h2>";
    $stmt = $db->query("SELECT id, reference, title FROM interventions ORDER BY id DESC LIMIT 1");
    $lastIntervention = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastIntervention) {
        echo "<p>Dernière intervention trouvée : <strong>" . h($lastIntervention['reference']) . "</strong> - " . h($lastIntervention['title']) . "</p>";
        
        // Test des commentaires solution
        $solutionComments = $mailService->interventionModel->getSolutionComments($lastIntervention['id']);
        echo "<p>Commentaires solution : " . count($solutionComments) . " trouvé(s)</p>";
        
        if (!empty($solutionComments)) {
            echo "<ul>";
            foreach ($solutionComments as $comment) {
                echo "<li>" . h($comment['created_by_name']) . " : " . h(substr($comment['comment'], 0, 100)) . "...</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: orange;'>Aucune intervention trouvée dans la base de données.</p>";
    }
    
    echo "<h2>Instructions de test :</h2>";
    echo "<ol>";
    echo "<li>Configurez les paramètres SMTP dans <a href='" . BASE_URL . "settings/email'>Paramètres > Configuration email</a></li>";
    echo "<li>Créez ou modifiez les templates d'emails</li>";
    echo "<li>Activez l'envoi automatique dans les paramètres</li>";
    echo "<li>Configurez un email de test si nécessaire</li>";
    echo "<li>Créez ou fermez une intervention pour tester l'envoi</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . h($e->getMessage()) . "</p>";
    echo "<pre>" . h($e->getTraceAsString()) . "</pre>";
}
?>
