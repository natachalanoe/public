<?php
/**
 * Script de test pour vérifier la configuration email avant création d'intervention
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/classes/MailService.php';

// Vérifier que nous sommes en mode développement
if (!defined('DEBUG') || !DEBUG) {
    die("Ce script ne peut être exécuté qu'en mode développement.");
}

echo "<h1>Test de configuration email pour création d'intervention</h1>";

try {
    $config = Config::getInstance();
    
    echo "<h2>1. Configuration SMTP :</h2>";
    $smtpConfig = [
        'mail_host' => $config->get('mail_host'),
        'mail_port' => $config->get('mail_port'),
        'mail_username' => $config->get('mail_username'),
        'mail_from_address' => $config->get('mail_from_address'),
        'mail_from_name' => $config->get('mail_from_name'),
    ];
    
    $smtpOk = true;
    foreach ($smtpConfig as $key => $value) {
        $status = !empty($value) ? '✅' : '❌';
        echo "<p>$status <strong>$key:</strong> " . ($value ?: 'Non configuré') . "</p>";
        if (empty($value)) $smtpOk = false;
    }
    
    echo "<h2>2. Paramètres d'envoi automatique :</h2>";
    $autoSendCreation = $config->get('email_auto_send_creation', '0');
    echo "<p>" . ($autoSendCreation == '1' ? '✅' : '❌') . " <strong>Envoi automatique création:</strong> " . ($autoSendCreation == '1' ? 'Activé' : 'Désactivé') . "</p>";
    
    $testEmail = $config->get('test_email', '');
    echo "<p>" . (!empty($testEmail) ? '✅' : '⚠️') . " <strong>Email de test:</strong> " . ($testEmail ?: 'Non configuré') . "</p>";
    
    echo "<h2>3. Templates d'emails :</h2>";
    $stmt = $db->query("SELECT * FROM mail_templates WHERE template_type = 'intervention_created' AND is_active = 1");
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        echo "<p>✅ <strong>Template création:</strong> " . h($template['name']) . "</p>";
        echo "<p><strong>Sujet:</strong> " . h($template['subject']) . "</p>";
    } else {
        echo "<p>❌ <strong>Template création:</strong> Aucun template actif trouvé</p>";
    }
    
    echo "<h2>4. Test avec une intervention existante :</h2>";
    $stmt = $db->query("SELECT i.*, c.name as client_name, s.name as site_name, r.name as room_name,
                               CONCAT(u.first_name, ' ', u.last_name) as technician_name
                        FROM interventions i
                        LEFT JOIN clients c ON i.client_id = c.id
                        LEFT JOIN sites s ON i.site_id = s.id
                        LEFT JOIN rooms r ON i.room_id = r.id
                        LEFT JOIN users u ON i.technician_id = u.id
                        WHERE i.contact_client IS NOT NULL AND i.contact_client != ''
                        ORDER BY i.id DESC LIMIT 1");
    $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($intervention) {
        echo "<p>✅ <strong>Intervention test trouvée:</strong> " . h($intervention['reference']) . "</p>";
        echo "<p><strong>Contact client:</strong> " . h($intervention['contact_client']) . "</p>";
        echo "<p><strong>Client:</strong> " . h($intervention['client_name']) . "</p>";
        
        // Test d'envoi
        if ($smtpOk && $autoSendCreation == '1' && $template) {
            echo "<h3>Test d'envoi :</h3>";
            try {
                $mailService = new MailService($db);
                $result = $mailService->sendInterventionCreated($intervention['id']);
                
                if ($result) {
                    echo "<p style='color: green;'>✅ <strong>Test d'envoi réussi !</strong></p>";
                } else {
                    echo "<p style='color: red;'>❌ <strong>Test d'envoi échoué</strong></p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ <strong>Erreur lors du test:</strong> " . h($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ <strong>Impossible de tester l'envoi</strong> - Configuration incomplète</p>";
        }
    } else {
        echo "<p>❌ <strong>Aucune intervention avec contact_client trouvée</strong></p>";
    }
    
    echo "<h2>5. Historique des emails :</h2>";
    $stmt = $db->query("SELECT mh.*, mt.name as template_name, i.reference as intervention_reference
                        FROM mail_history mh
                        LEFT JOIN mail_templates mt ON mh.template_id = mt.id
                        LEFT JOIN interventions i ON mh.intervention_id = i.id
                        ORDER BY mh.created_at DESC LIMIT 5");
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($history)) {
        echo "<p>Aucun email envoyé pour le moment.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Date</th><th>Template</th><th>Intervention</th><th>Destinataire</th><th>Statut</th></tr>";
        foreach ($history as $email) {
            $statusColor = $email['status'] == 'sent' ? 'green' : ($email['status'] == 'failed' ? 'red' : 'orange');
            echo "<tr>";
            echo "<td>" . date('d/m/Y H:i', strtotime($email['created_at'])) . "</td>";
            echo "<td>" . h($email['template_name']) . "</td>";
            echo "<td>" . h($email['intervention_reference']) . "</td>";
            echo "<td>" . h($email['recipient_email']) . "</td>";
            echo "<td style='color: $statusColor;'>" . h($email['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . h($e->getMessage()) . "</p>";
}
?>
