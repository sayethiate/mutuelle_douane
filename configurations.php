<?php
// configurations.php
require_once 'config.php';

header('Content-Type: application/json');

$db = getDBConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $stmt = $db->query("SELECT * FROM configuration WHERE id = 1");
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['error' => 'Configuration non trouvée']);
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            
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
                $data['dateOuvertureInscription'] ?? null,
                $data['dateFermetureInscription'] ?? null,
                $data['dateVote'] ?? null,
                $data['dateFinVote'] ?? null,
                $data['inspecteurDelegates'] ?? 10,
                $data['controleurDelegates'] ?? 20,
                $data['agentDelegates'] ?? 20,
                $data['preposeDelegates'] ?? 40,
                $data['retraiteDelegates'] ?? 6
            ]);
            
            echo json_encode(['success' => $stmt->rowCount() > 0]);
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>