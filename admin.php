<?php
// admin.php
require_once 'config.php';

// Vérification de l'authentification
if (!isset($_SERVER['PHP_AUTH_USER']) {
    header('WWW-Authenticate: Basic realm="Administration Elections"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentification requise';
    exit;
} else {
    $valid_user = 'admin';
    $valid_pass = 'admin123'; // À changer en production
    
    if ($_SERVER['PHP_AUTH_USER'] !== $valid_user || $_SERVER['PHP_AUTH_PW'] !== $valid_pass) {
        header('HTTP/1.0 401 Unauthorized');
        echo 'Identifiants incorrects';
        exit;
    }
}

// Interface d'administration
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - Élections</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin-bottom: 30px; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>Administration des Élections</h1>
    
    <div class="section">
        <h2>Participants</h2>
        <table>
            <tr>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Grade</th>
                <th>Candidat</th>
                <th>A voté</th>
            </tr>
            <?php
            $db = getDBConnection();
            $stmt = $db->query("SELECT * FROM participants ORDER BY nom, prenom");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                    <td>{$row['matricule']}</td>
                    <td>{$row['nom']}</td>
                    <td>{$row['prenom']}</td>
                    <td>{$row['grade']}</td>
                    <td>".($row['is_candidat'] ? 'Oui' : 'Non')."</td>
                    <td>".($row['a_vote'] ? 'Oui' : 'Non')."</td>
                </tr>";
            }
            ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Votes</h2>
        <table>
            <tr>
                <th>Électeur</th>
                <th>Candidat</th>
                <th>Date</th>
            </tr>
            <?php
            $stmt = $db->query("
                SELECT v.*, e.nom AS electeur_nom, e.prenom AS electeur_prenom,
                       c.nom AS candidat_nom, c.prenom AS candidat_prenom
                FROM votes v
                JOIN participants e ON v.matricule_electeur = e.matricule
                JOIN participants c ON v.matricule_candidat = c.matricule
                ORDER BY v.date_vote DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                    <td>{$row['electeur_prenom']} {$row['electeur_nom']} ({$row['matricule_electeur']})</td>
                    <td>{$row['candidat_prenom']} {$row['candidat_nom']} ({$row['matricule_candidat']})</td>
                    <td>{$row['date_vote']}</td>
                </tr>";
            }
            ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Configuration</h2>
        <?php
        $stmt = $db->query("SELECT * FROM configuration WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <form method="post" action="update_config.php">
            <table>
                <tr>
                    <th>Paramètre</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>Date ouverture inscriptions</td>
                    <td><input type="datetime-local" name="date_ouverture_inscription" 
                               value="<?= $config['date_ouverture_inscription'] ?>"></td>
                </tr>
                <tr>
                    <td>Date fermeture inscriptions</td>
                    <td><input type="datetime-local" name="date_fermeture_inscription" 
                               value="<?= $config['date_fermeture_inscription'] ?>"></td>
                </tr>
                <tr>
                    <td>Date vote</td>
                    <td><input type="datetime-local" name="date_vote" 
                               value="<?= $config['date_vote'] ?>"></td>
                </tr>
                <tr>
                    <td>Date fin vote</td>
                    <td><input type="datetime-local" name="date_fin_vote" 
                               value="<?= $config['date_fin_vote'] ?>"></td>
                </tr>
                <tr>
                    <td>Délégués Inspecteurs</td>
                    <td><input type="number" name="inspecteur_delegates" 
                               value="<?= $config['inspecteur_delegates'] ?>"></td>
                </tr>
                <tr>
                    <td>Délégués Contrôleurs</td>
                    <td><input type="number" name="controleur_delegates" 
                               value="<?= $config['controleur_delegates'] ?>"></td>
                </tr>
                <tr>
                    <td>Délégués Agents</td>
                    <td><input type="number" name="agent_delegates" 
                               value="<?= $config['agent_delegates'] ?>"></td>
                </tr>
                <tr>
                    <td>Délégués Préposés</td>
                    <td><input type="number" name="prepose_delegates" 
                               value="<?= $config['prepose_delegates'] ?>"></td>
                </tr>
                <tr>
                    <td>Délégués Retraités</td>
                    <td><input type="number" name="retraite_delegates" 
                               value="<?= $config['retraite_delegates'] ?>"></td>
                </tr>
            </table>
            <button type="submit">Mettre à jour</button>
        </form>
    </div>
    
    <div class="section">
        <h2>Sauvegarde et restauration</h2>
        <p><a href="export_data.php">Exporter toutes les données</a></p>
        
        <form method="post" action="import_data.php" enctype="multipart/form-data">
            <p>Importer des données:</p>
            <input type="file" name="backup_file" accept=".json">
            <button type="submit">Importer</button>
        </form>
    </div>
</body>
</html>