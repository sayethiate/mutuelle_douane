<?php
// sync.php
require_once 'config.php';

header('Content-Type: application/json');

$db = getDBConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'export':
            // Exporter toutes les données pour sauvegarde
            $data = [];
            
            // Participants
            $stmt = $db->query("SELECT * FROM participants");
            $data['participants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Votes
            $stmt = $db->query("SELECT * FROM votes");
            $data['votes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Configuration
            $stmt = $db->query("SELECT * FROM configuration WHERE id = 1");
            $data['configuration'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($data);
            break;
            
        case 'import':
            // Importer des données (pour restauration)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                echo json_encode(['error' => 'Données invalides']);
                exit;
            }
            
            $db->beginTransaction();
            
            // Vider les tables
            $db->exec("DELETE FROM votes");
            $db->exec("DELETE FROM participants");
            
            // Importer les participants
            if (!empty($data['participants'])) {
                $stmt = $db->prepare("INSERT INTO participants 
                    (grade, prenom, nom, telephone, matricule, is_candidat, motivation, a_vote, date_inscription, date_vote) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                foreach ($data['participants'] as $participant) {
                    $stmt->execute([
                        $participant['grade'],
                        $participant['prenom'],
                        $participant['nom'],
                        $participant['telephone'],
                        $participant['matricule'],
                        $participant['is_candidat'] ?? false,
                        $participant['motivation'] ?? '',
                        $participant['a_vote'] ?? false,
                        $participant['date_inscription'] ?? date('Y-m-d H:i:s'),
                        $participant['date_vote'] ?? null
                    ]);
                }
            }
            
            // Importer les votes
            if (!empty($data['votes'])) {
                $stmt = $db->prepare("INSERT INTO votes 
                    (matricule_electeur, matricule_candidat, date_vote) 
                    VALUES (?, ?, ?)");
                    
                foreach ($data['votes'] as $vote) {
                    $stmt->execute([
                        $vote['matricule_electeur'],
                        $vote['matricule_candidat'],
                        $vote['date_vote'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Mettre à jour la configuration
            if (!empty($data['configuration'])) {
                $stmt = $db->prepare("UPDATE configuration SET 
                    date_ouverture_inscription = ?, 
                    date_fermeture_inscription = ?, 
                    date_vote = ?, 
                    date_fin_vote = ?,
                    inspecteur_delegates = ?,
                    controleur_delegates = ?,
                    agent_delegates = ?,
                    prepose_delegates = ?,
                    retraite_delegates = ?
                    WHERE id = 1");
                    
                $stmt->execute([
                    $data['configuration']['date_ouverture_inscription'] ?? null,
                    $data['configuration']['date_fermeture_inscription'] ?? null,
                    $data['configuration']['date_vote'] ?? null,
                    $data['configuration']['date_fin_vote'] ?? null,
                    $data['configuration']['inspecteur_delegates'] ?? 10,
                    $data['configuration']['controleur_delegates'] ?? 20,
                    $data['configuration']['agent_delegates'] ?? 20,
                    $data['configuration']['prepose_delegates'] ?? 40,
                    $data['configuration']['retraite_delegates'] ?? 6
                ]);
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} catch(PDOException $e) {
    $db->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>