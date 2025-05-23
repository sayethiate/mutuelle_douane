<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'kama5994_mutdouanes');
define('DB_PASS', 'u{2gjz2(#?JV');
define('DB_NAME', 'kama5994_Mutdouanes ');

function getDBConnection() {
    try {
        $db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch(PDOException $e) {
        die("Erreur de connexion à la base de données: " . $e->getMessage());
    }
}

// Création des tables si elles n'existent pas
function initializeDatabase() {
    $db = getDBConnection();
    
    // Table participants
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade VARCHAR(50) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        nom VARCHAR(100) NOT NULL,
        telephone VARCHAR(20) NOT NULL,
        matricule VARCHAR(20) UNIQUE NOT NULL,
        is_candidat BOOLEAN DEFAULT FALSE,
        motivation TEXT,
        a_vote BOOLEAN DEFAULT FALSE,
        date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_vote DATETIME NULL
    )");
    
    // Table candidats
    $db->exec("CREATE TABLE IF NOT EXISTS candidats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        matricule VARCHAR(20) NOT NULL,
        nom_complet VARCHAR(200) NOT NULL,
        motivation TEXT,
        grade VARCHAR(50) NOT NULL,
        FOREIGN KEY (matricule) REFERENCES participants(matricule)
    )");
    
    // Table votes
    $db->exec("CREATE TABLE IF NOT EXISTS votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        matricule_electeur VARCHAR(20) NOT NULL,
        matricule_candidat VARCHAR(20) NOT NULL,
        date_vote DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (matricule_electeur) REFERENCES participants(matricule),
        FOREIGN KEY (matricule_candidat) REFERENCES participants(matricule)
    )");
    
    // Table configuration
    $db->exec("CREATE TABLE IF NOT EXISTS configuration (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date_ouverture_inscription DATETIME NULL,
        date_fermeture_inscription DATETIME NULL,
        date_vote DATETIME NULL,
        date_fin_vote DATETIME NULL,
        inspecteur_delegates INT DEFAULT 10,
        controleur_delegates INT DEFAULT 20,
        agent_delegates INT DEFAULT 20,
        prepose_delegates INT DEFAULT 40,
        retraite_delegates INT DEFAULT 6
    )");
    
    // Vérifier si la configuration existe, sinon créer une entrée par défaut
    $stmt = $db->query("SELECT COUNT(*) FROM configuration");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO configuration (id) VALUES (1)");
    }
}
?>