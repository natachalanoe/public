<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/InterventionModel.php';
require_once __DIR__ . '/../models/MailTemplateModel.php';
require_once __DIR__ . '/../models/MailHistoryModel.php';

/**
 * Service de gestion des envois d'emails pour les interventions
 */
class MailService {
    private $db;
    private $interventionModel;
    private $mailTemplateModel;
    private $mailHistoryModel;
    private $config;

    public function __construct($db) {
        $this->db = $db;
        $this->interventionModel = new InterventionModel($db);
        $this->mailTemplateModel = new MailTemplateModel($db);
        $this->mailHistoryModel = new MailHistoryModel($db);
        $this->config = Config::getInstance();
    }

    /**
     * Envoie un email de création d'intervention
     * @param int $interventionId ID de l'intervention
     * @return bool Succès de l'envoi
     */
    public function sendInterventionCreated($interventionId) {
        try {
            // Vérifier si l'envoi automatique est activé
            if ($this->config->get('email_auto_send_creation', '0') != '1') {
                custom_log("Envoi automatique de création désactivé pour l'intervention $interventionId", 'INFO');
                return true;
            }

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception("Intervention $interventionId introuvable");
            }

            // Récupérer le template
            $template = $this->mailTemplateModel->getByType('intervention_created');
            if (!$template) {
                throw new Exception("Template de création d'intervention introuvable");
            }

            // Préparer les destinataires
            $recipients = $this->prepareRecipients($intervention);

            // Remplacer les variables dans le template
            $subject = $this->replaceTemplateVariables($template['subject'], $intervention);
            $body = $this->replaceTemplateVariables($template['body'], $intervention);

            // Envoyer l'email
            return $this->sendEmail($recipients, $subject, $body, 'intervention_created', $interventionId);

        } catch (Exception $e) {
            custom_log("Erreur envoi email création intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Envoie un email de fermeture d'intervention
     * @param int $interventionId ID de l'intervention
     * @return bool Succès de l'envoi
     */
    public function sendInterventionClosed($interventionId) {
        try {
            // Vérifier si l'envoi automatique est activé
            if ($this->config->get('email_auto_send_closing', '0') != '1') {
                custom_log("Envoi automatique de fermeture désactivé pour l'intervention $interventionId", 'INFO');
                return true;
            }

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception("Intervention $interventionId introuvable");
            }

            // Récupérer le template
            $template = $this->mailTemplateModel->getByType('intervention_closed');
            if (!$template) {
                throw new Exception("Template de fermeture d'intervention introuvable");
            }

            // Préparer les destinataires
            $recipients = $this->prepareRecipients($intervention);

            // Remplacer les variables dans le template
            $subject = $this->replaceTemplateVariables($template['subject'], $intervention);
            $body = $this->replaceTemplateVariables($template['body'], $intervention);

            // Envoyer l'email
            return $this->sendEmail($recipients, $subject, $body, 'intervention_closed', $interventionId);

        } catch (Exception $e) {
            custom_log("Erreur envoi email fermeture intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Prépare la liste des destinataires pour une intervention
     * @param array $intervention Données de l'intervention
     * @return array Liste des destinataires
     */
    private function prepareRecipients($intervention) {
        $recipients = [];

        // Destinataire principal : contact_client de l'intervention
        if (!empty($intervention['contact_client'])) {
            $recipients[] = [
                'email' => $intervention['contact_client'],
                'name' => 'Contact client'
            ];
        }

        // TODO: Ajouter d'autres destinataires via la table mail_recipients
        // Pour l'instant, on se contente du contact_client

        if (empty($recipients)) {
            throw new Exception("Aucun destinataire trouvé pour l'intervention " . $intervention['id']);
        }

        return $recipients;
    }

    /**
     * Redirige les emails vers l'adresse de test si configurée
     * @param array $recipients Liste des destinataires
     * @return array Liste des destinataires (modifiée si test_email configuré)
     */
    private function redirectToTestEmail($recipients) {
        $testEmail = $this->config->get('test_email', '');
        
        if (!empty($testEmail)) {
            // Remplacer tous les destinataires par l'email de test
            $redirectedRecipients = [];
            foreach ($recipients as $recipient) {
                $redirectedRecipients[] = [
                    'email' => $testEmail,
                    'name' => $recipient['name'] . ' [REDIRIGÉ: ' . $recipient['email'] . ']',
                    'original_email' => $recipient['email'] // Garder trace de l'email original
                ];
            }
            return $redirectedRecipients;
        }
        
        return $recipients;
    }

    /**
     * Envoie un email avec gestion de la redirection de test
     * @param array $recipients Liste des destinataires
     * @param string $subject Sujet de l'email
     * @param string $body Corps de l'email
     * @param string $templateType Type de template
     * @param int $interventionId ID de l'intervention
     * @param string $attachmentPath Chemin vers la pièce jointe (optionnel)
     * @return bool Succès de l'envoi
     */
    private function sendEmail($recipients, $subject, $body, $templateType, $interventionId, $attachmentPath = null) {
        try {
            // Rediriger vers l'email de test si configuré
            $finalRecipients = $this->redirectToTestEmail($recipients);
            
            // Ajouter une note dans le sujet si redirection active
            $testEmail = $this->config->get('test_email', '');
            if (!empty($testEmail)) {
                $subject = '[TEST] ' . $subject;
            }
            
            // Envoyer à chaque destinataire
            foreach ($finalRecipients as $recipient) {
                $this->sendSingleEmail($recipient, $subject, $body, $templateType, $interventionId, $attachmentPath);
            }
            
            return true;
            
        } catch (Exception $e) {
            custom_log("Erreur lors de l'envoi de l'email : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Envoie un email à un destinataire unique
     * @param array $recipient Destinataire
     * @param string $subject Sujet
     * @param string $body Corps
     * @param string $templateType Type de template
     * @param int $interventionId ID de l'intervention
     * @param string $attachmentPath Chemin vers la pièce jointe
     */
    private function sendSingleEmail($recipient, $subject, $body, $templateType, $interventionId, $attachmentPath = null) {
        // Enregistrer dans l'historique avant envoi
        $templateId = $this->mailTemplateModel->getTemplateIdByType($templateType);
        $historyId = $this->mailHistoryModel->saveToHistory($interventionId, $templateId, $recipient, $subject, $body, $attachmentPath);
        
        try {
            // Configuration SMTP
            $smtpConfig = [
                'host' => $this->config->get('mail_host'),
                'port' => $this->config->get('mail_port', '587'),
                'username' => $this->config->get('mail_username'),
                'password' => $this->config->get('mail_password'),
                'encryption' => $this->config->get('mail_encryption', 'tls'),
                'from_address' => $this->config->get('mail_from_address'),
                'from_name' => $this->config->get('mail_from_name'),
            ];

            // Vérifier la configuration SMTP
            if (empty($smtpConfig['host']) || empty($smtpConfig['from_address'])) {
                throw new Exception("Configuration SMTP incomplète");
            }

            // Utiliser la fonction mail() de PHP pour l'instant
            // TODO: Intégrer PHPMailer pour une meilleure gestion SMTP
            $headers = [
                'From: ' . $smtpConfig['from_name'] . ' <' . $smtpConfig['from_address'] . '>',
                'Reply-To: ' . $smtpConfig['from_address'],
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion()
            ];

            $success = mail($recipient['email'], $subject, $body, implode("\r\n", $headers));
            
            if ($success) {
                // Mettre à jour l'historique
                $this->mailHistoryModel->updateHistoryStatus($historyId, 'sent');
                custom_log("Email envoyé avec succès à " . $recipient['email'], 'INFO');
            } else {
                throw new Exception("Échec de l'envoi via mail()");
            }
            
        } catch (Exception $e) {
            // Mettre à jour l'historique avec l'erreur
            $this->mailHistoryModel->updateHistoryStatus($historyId, 'failed', $e->getMessage());
            custom_log("Erreur envoi email à " . $recipient['email'] . " : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Formate les commentaires solution pour l'email
     * @param array $comments Liste des commentaires solution uniquement
     * @return string HTML formaté des commentaires
     */
    private function formatSolutionComments($comments) {
        if (empty($comments)) {
            return '<p><em>Aucune solution documentée pour cette intervention.</em></p>';
        }
        
        $html = '<h3>Solution(s) appliquée(s) :</h3><ul>';
        foreach ($comments as $comment) {
            $html .= '<li>';
            $html .= '<strong>' . h($comment['created_by_name']) . '</strong> ';
            $html .= '<small>(' . date('d/m/Y H:i', strtotime($comment['created_at'])) . ')</small><br>';
            $html .= nl2br(h($comment['comment']));
            $html .= '</li>';
        }
        $html .= '</ul>';
        
        return $html;
    }

    /**
     * Remplace les variables dans le template
     * @param string $template Template HTML
     * @param array $intervention Données de l'intervention
     * @return string Template avec variables remplacées
     */
    private function replaceTemplateVariables($template, $intervention) {
        // Variables de base
        $replacements = [
            // Format standard {variable}
            '{intervention_id}' => $intervention['id'] ?? '',
            '{intervention_reference}' => $intervention['reference'] ?? '',
            '{intervention_title}' => $intervention['title'] ?? '',
            '{client_name}' => $intervention['client_name'] ?? '',
            '{site_name}' => $intervention['site_name'] ?? '',
            '{room_name}' => $intervention['room_name'] ?? '',
            '{technician_name}' => $intervention['technician_name'] ?? '',
            '{intervention_description}' => $intervention['description'] ?? '',
            '{intervention_duration}' => $intervention['duration'] ?? '',
            '{intervention_priority}' => $intervention['priority_name'] ?? '',
            '{intervention_type}' => $intervention['type_name'] ?? '',
            '{intervention_status}' => $intervention['status_name'] ?? '',
            '{tickets_used}' => $intervention['tickets_used'] ?? '0',
            '{intervention_url}' => BASE_URL . 'interventions/view/' . $intervention['id'],
            '{intervention_client_url}' => BASE_URL . 'interventions_client/view/' . $intervention['id'],
            '{created_at}' => isset($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : '',
            '{closed_at}' => isset($intervention['closed_at']) ? date('d/m/Y H:i', strtotime($intervention['closed_at'])) : '',
            '{intervention_date}' => isset($intervention['created_at']) ? date('d/m/Y', strtotime($intervention['created_at'])) : '',
            
            // Format avec dièse #{variable} (pour compatibilité)
            '#{intervention_id}' => $intervention['id'] ?? '',
            '#{intervention_reference}' => $intervention['reference'] ?? '',
            '#{intervention_title}' => $intervention['title'] ?? '',
            '#{client_name}' => $intervention['client_name'] ?? '',
            '#{site_name}' => $intervention['site_name'] ?? '',
            '#{room_name}' => $intervention['room_name'] ?? '',
            '#{technician_name}' => $intervention['technician_name'] ?? '',
            '#{intervention_description}' => $intervention['description'] ?? '',
            '#{intervention_duration}' => $intervention['duration'] ?? '',
            '#{intervention_priority}' => $intervention['priority_name'] ?? '',
            '#{intervention_type}' => $intervention['type_name'] ?? '',
            '#{intervention_status}' => $intervention['status_name'] ?? '',
            '#{tickets_used}' => $intervention['tickets_used'] ?? '0',
            '#{intervention_url}' => BASE_URL . 'interventions/view/' . $intervention['id'],
            '#{intervention_client_url}' => BASE_URL . 'interventions_client/view/' . $intervention['id'],
            '#{created_at}' => isset($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : '',
            '#{closed_at}' => isset($intervention['closed_at']) ? date('d/m/Y H:i', strtotime($intervention['closed_at'])) : '',
            '#{intervention_date}' => isset($intervention['created_at']) ? date('d/m/Y', strtotime($intervention['created_at'])) : '',
        ];
        
        // Pour les templates de fermeture, ajouter les commentaires solution
        if (strpos($template, '{solution_comments}') !== false) {
            $solutionComments = $this->interventionModel->getSolutionComments($intervention['id']);
            $replacements['{solution_comments}'] = $this->formatSolutionComments($solutionComments);
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
