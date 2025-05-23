<?php
// participants.php
require_once 'config.php';

header('Content-Type: application/json');

$db = getDBConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $matricule = $_GET['matricule'] ?? '';
            if ($matricule) {
                $stmt = $db->prepare("SELECT * FROM participants WHERE matricule = ?");
                $stmt->execute([$matricule]);
                $participant = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($participant ?: ['error' => 'Participant non trouvé']);
            } else {
                $stmt = $db->query("SELECT * FROM participants");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;
            
        case 'add':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("INSERT INTO participants 
                (grade, prenom, nom, telephone, matricule, is_candidat, motivation) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
                
            $stmt->execute([
                $data['grade'],
                $data['prenom'],
                $data['nom'],
                $data['telephone'],
                $data['matricule'],
                $data['isCandidat'] ?? false,
                $data['motivation'] ?? ''
            ]);
            
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $matricule = $data['matricule'];
            
            $stmt = $db->prepare("UPDATE participants SET 
                grade = ?, prenom = ?, nom = ?, telephone = ?, is_candidat = ?, motivation = ?, a_vote = ?
                WHERE matricule = ?");
                
            $stmt->execute([
                $data['grade'],
                $data['prenom'],
                $data['nom'],
                $data['telephone'],
                $data['isCandidat'] ?? false,
                $data['motivation'] ?? '',
                $data['aVote'] ?? false,
                $matricule
            ]);
            
            echo json_encode(['success' => $stmt->rowCount() > 0]);
            break;
            
        case 'delete':
            $matricule = $_GET['matricule'] ?? '';
            $stmt = $db->prepare("DELETE FROM participants WHERE matricule = ?");
            $stmt->execute([$matricule]);
            echo json_encode(['success' => $stmt->rowCount() > 0]);
            break;
            
        case 'count':
            $stmt = $db->query("SELECT COUNT(*) FROM participants");
            echo json_encode(['count' => $stmt->fetchColumn()]);
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>