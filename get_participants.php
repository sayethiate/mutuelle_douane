<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    $query = "SELECT * FROM participants ORDER BY date_inscription DESC";
    $stmt = $db->query($query);
    
    $participants = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $participants[] = [
            'id' => $row['id'],
            'grade' => $row['grade'],
            'prenom' => $row['prenom'],
            'nom' => $row['nom'],
            'telephone' => $row['telephone'],
            'matricule' => $row['matricule'],
            'is_candidat' => (bool)$row['is_candidat'],
            'motivation' => $row['motivation'],
            'a_vote' => (bool)$row['a_vote'],
            'date_inscription' => $row['date_inscription'],
            'date_vote' => $row['date_vote']
        ];
    }
    
    echo json_encode($participants);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>