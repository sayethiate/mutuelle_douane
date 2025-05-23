<?php
// update_config.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDBConnection();
    
    try {
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
            $_POST['date_ouverture_inscription'] ?: null,
            $_POST['date_fermeture_inscription'] ?: null,
            $_POST['date_vote'] ?: null,
            $_POST['date_fin_vote'] ?: null,
            $_POST['inspecteur_delegates'] ?? 10,
            $_POST['controleur_delegates'] ?? 20,
            $_POST['agent_delegates'] ?? 20,
            $_POST['prepose_delegates'] ?? 40,
            $_POST['retraite_delegates'] ?? 6
        ]);
        
        header("Location: admin.php?success=1");
    } catch(PDOException $e) {
        die("Erreur lors de la mise à jour: " . $e->getMessage());
    }
} else {
    header("Location: admin.php");
}
?>