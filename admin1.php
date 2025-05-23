<?php
// config.php - Configuration de la base de données
class Database {
    private $host = 'localhost';
    private $username = 'kama5994_mutdouanes';
    private $password = 'u{2gjz2(#?JV';
    private $db_name = 'kama5994_Mutdouanes';
    private $pdo;

    public function connect() {
        if ($this->pdo === null) {
            try {
                $this->pdo = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                    $this->username,
                    $this->password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch(PDOException $e) {
                die("Erreur de connexion : " . $e->getMessage());
            }
        }
        return $this->pdo;
    }

    public function initTables() {
        $db = $this->connect();
        
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

        // Table participants
        $db->exec("CREATE TABLE IF NOT EXISTS participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grade VARCHAR(50) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            nom VARCHAR(100) NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            matricule VARCHAR(50) UNIQUE NOT NULL,
            is_candidat BOOLEAN DEFAULT FALSE,
            date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Table votes
        $db->exec("CREATE TABLE IF NOT EXISTS votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            matricule_votant VARCHAR(50) NOT NULL,
            candidate_id VARCHAR(50) NOT NULL,
            date_vote TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_vote (matricule_votant)
        )");

        // Table results
        $db->exec("CREATE TABLE IF NOT EXISTS election_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            candidate_id VARCHAR(50) NOT NULL,
            candidate_name VARCHAR(200) NOT NULL,
            votes_count INT DEFAULT 0,
            percentage DECIMAL(5,2) DEFAULT 0.00
        )");

        // Insérer une configuration par défaut si elle n'existe pas
        $stmt = $db->prepare("SELECT COUNT(*) FROM configuration");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $db->exec("INSERT INTO configuration (id) VALUES (1)");
        }
    }
}

// admin.php - Page d'administration
session_start();

$database = new Database();
$database->initTables();
$db = $database->connect();

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $password = $_POST['password'] ?? '';
            if ($password === 'admin123') {
                $_SESSION['admin_logged'] = true;
                echo json_encode(['success' => true, 'message' => 'Connexion réussie']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Mot de passe incorrect']);
            }
            exit;
            
        case 'logout':
            unset($_SESSION['admin_logged']);
            echo json_encode(['success' => true]);
            exit;
            
        case 'save_dates':
            if (!isset($_SESSION['admin_logged'])) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE configuration SET 
                date_ouverture_inscription = ?, 
                date_fermeture_inscription = ?, 
                date_vote = ?, 
                date_fin_vote = ?
                WHERE id = 1");
            
            $result = $stmt->execute([
                $_POST['date_ouverture'] ?: null,
                $_POST['date_fermeture'] ?: null,
                $_POST['date_vote'] ?: null,
                $_POST['date_fin_vote'] ?: null
            ]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Dates enregistrées avec succès' : 'Erreur lors de l\'enregistrement'
            ]);
            exit;
            
        case 'save_delegates_count':
            if (!isset($_SESSION['admin_logged'])) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE configuration SET 
                inspecteur_delegates = ?, 
                controleur_delegates = ?, 
                agent_delegates = ?, 
                prepose_delegates = ?, 
                retraite_delegates = ?
                WHERE id = 1");
            
            $result = $stmt->execute([
                intval($_POST['inspecteur_delegates']),
                intval($_POST['controleur_delegates']),
                intval($_POST['agent_delegates']),
                intval($_POST['prepose_delegates']),
                intval($_POST['retraite_delegates'])
            ]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Nombre de délégués mis à jour' : 'Erreur lors de la mise à jour'
            ]);
            exit;
            
        case 'get_participants':
            if (!isset($_SESSION['admin_logged'])) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
            
            $page = $_POST['page'] ?? 1;
            
            // Si page = 'all', récupérer tous les participants pour l'export
            if ($page === 'all') {
                $stmt = $db->prepare("SELECT * FROM participants ORDER BY date_inscription DESC");
                $stmt->execute();
                $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'participants' => $participants
                ]);
            } else {
                $page = intval($page);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                $stmt = $db->prepare("SELECT * FROM participants ORDER BY date_inscription DESC LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
                $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM participants");
                $stmt->execute();
                $total = $stmt->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'participants' => $participants,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]);
            }
            exit;
            
        case 'delete_participant':
            if (!isset($_SESSION['admin_logged'])) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
            
            $id = intval($_POST['id']);
            $stmt = $db->prepare("DELETE FROM participants WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Participant supprimé' : 'Erreur lors de la suppression'
            ]);
            exit;
            
        case 'count_votes':
            if (!isset($_SESSION['admin_logged'])) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
            
            // Compter les votes par catégorie et candidat
            $stmt = $db->prepare("
                SELECT p.grade, v.candidate_id, COUNT(*) as votes_count,
                       CONCAT(p2.prenom, ' ', p2.nom) as candidate_name
                FROM votes v
                JOIN participants p ON v.matricule_votant = p.matricule
                JOIN participants p2 ON v.candidate_id = p2.matricule
                GROUP BY p.grade, v.candidate_id
                ORDER BY p.grade, votes_count DESC
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Nettoyer la table des résultats précédents
            $db->exec("DELETE FROM election_results");
            
            // Calculer les pourcentages et sauvegarder
            $categories = [];
            foreach ($results as $result) {
                $categories[$result['grade']][] = $result;
            }
            
            foreach ($categories as $category => $candidates) {
                $totalVotes = array_sum(array_column($candidates, 'votes_count'));
                
                foreach ($candidates as $candidate) {
                    $percentage = $totalVotes > 0 ? ($candidate['votes_count'] / $totalVotes) * 100 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO election_results 
                        (category, candidate_id, candidate_name, votes_count, percentage) 
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $category,
                        $candidate['candidate_id'],
                        $candidate['candidate_name'],
                        $candidate['votes_count'],
                        $percentage
                    ]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Dépouillement terminé'
            ]);
            exit;
            
        case 'get_results':
            if (!isset($_SESSION['admin_logged'])) {
                echo json_encode(['success' => false, 'message' => 'Non autorisé']);
                exit;
            }
            
            $stmt = $db->prepare("SELECT * FROM election_results ORDER BY category, votes_count DESC");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            exit;
            
        case 'get_stats':
            $stmt = $db->prepare("SELECT 
                (SELECT COUNT(*) FROM participants) as total_participants,
                (SELECT COUNT(*) FROM participants WHERE is_candidat = 1) as total_candidates,
                (SELECT COUNT(*) FROM votes) as total_votes
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM configuration WHERE id = 1");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_delegates = ($config['inspecteur_delegates'] ?? 0) + 
                             ($config['controleur_delegates'] ?? 0) + 
                             ($config['agent_delegates'] ?? 0) + 
                             ($config['prepose_delegates'] ?? 0) + 
                             ($config['retraite_delegates'] ?? 0);
            
            echo json_encode([
                'success' => true,
                'stats' => array_merge($stats, ['total_delegates' => $total_delegates]),
                'config' => $config
            ]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            exit;
    }
}

// Récupération de la configuration pour l'affichage
$stmt = $db->prepare("SELECT * FROM configuration WHERE id = 1");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Élections Mutuelle des Douanes</title>
    <style>
        :root {
            --primary-color: #1a5a96;
            --secondary-color: #144272;
            --accent-color: #0077cc;
            --light-blue: #e6f2ff;
            --dark-blue: #0a2d5a;
            --orange: #FF6B00;
            --error-color: #d9534f;
            --success-color: #5cb85c;
            --warning-color: #f0ad4e;
            --info-color: #5bc0de;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #f5f9ff;
        }
        
        .header-container {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo-section {
            display: flex;
            align-items: center;
        }
        
        .logo-section h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        
        .logo-section span {
            font-size: 18px;
            margin-left: 10px;
            opacity: 0.9;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        nav ul li a:hover {
            opacity: 0.8;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-blue));
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .hero-section h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .hero-section p {
            font-size: 18px;
            max-width: 800px;
            margin: 0 auto 20px;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1, h2, h3 {
            color: var(--primary-color);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .admin-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: var(--light-blue);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-info {
            background-color: #e7f4ff;
            border-left: 4px solid var(--info-color);
            color: #31708f;
        }
        
        .alert-warning {
            background-color: #fcf8e3;
            border-left: 4px solid var(--warning-color);
            color: #8a6d3b;
        }
        
        .alert-success {
            background-color: #dff0d8;
            border-left: 4px solid var(--success-color);
            color: #3c763d;
        }
        
        .alert-error {
            background-color: #f2dede;
            border-left: 4px solid var(--error-color);
            color: #a94442;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        button:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        button.secondary {
            background-color: #6c757d;
        }
        
        button.danger {
            background-color: var(--error-color);
        }
        
        button.orange {
            background-color: var(--orange);
        }
        
        button.success {
            background-color: var(--success-color);
        }
        
        button.info {
            background-color: var(--info-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .hidden {
            display: none;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="number"],
        input[type="date"],
        input[type="datetime-local"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .action-buttons {
            margin-top: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .delegates-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .delegate-category {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e8f4f8;
            border-radius: 5px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination button {
            min-width: 30px;
        }
        
        .pagination button.active {
            background-color: var(--secondary-color);
        }
        
        .features-section {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin: 40px 0;
        }
        
        .feature-box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            width: 23%;
            min-width: 200px;
            margin: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid var(--accent-color);
        }
        
        .feature-box h3 {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 28px;
        }
        
        .feature-box p {
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav ul li {
                margin: 5px 10px;
            }
            
            button {
                width: 100%;
                margin-right: 0;
            }
            
            .feature-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="logo-section">
            <h1>Mutuelle des Douanes <span>Élections 2025</span></h1>
        </div>
        <nav>
            <ul>
                <li><a href="#">Accueil</a></li>
                <li><a href="#">Procédure</a></li>
                <li><a href="#">Résultats</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="hero-section">
        <h2>Administration des élections des délégués</h2>
        <p>Ensemble pour une élection transparente et démocratique</p>
        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 20px;">
            <div style="display: flex; align-items: center;">
                <span style="margin-right: 10px;">◆</span> Processus sécurisé
            </div>
            <div style="display: flex; align-items: center;">
                <span style="margin-right: 10px;">◆</span> Résultats en temps réel
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="header">
            <div>
                <h1>Tableau de bord Administrateur</h1>
            </div>
            <button id="logoutButton" class="secondary" style="display: <?= isset($_SESSION['admin_logged']) ? 'inline-block' : 'none' ?>">Déconnexion</button>
        </div>
        
        <div class="alert alert-warning">
            <strong>Accès restreint :</strong> Cette interface est réservée aux administrateurs du processus électoral.
        </div>
        
        <div id="loginSection" style="display: <?= isset($_SESSION['admin_logged']) ? 'none' : 'block' ?>">
            <div class="admin-section">
                <h2>Connexion Administrateur</h2>
                <div class="form-group">
                    <label for="adminPassword">Mot de passe administrateur</label>
                    <input type="password" id="adminPassword" placeholder="Entrez le mot de passe administrateur">
                </div>
                <button id="loginAdminButton" class="orange">Se connecter</button>
                <div id="adminLoginMessage" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <div id="adminPanel" style="display: <?= isset($_SESSION['admin_logged']) ? 'block' : 'none' ?>">
            <!-- Statistiques principales -->
            <div class="features-section">
                <div class="feature-box">
                    <h3 id="totalParticipants">0</h3>
                    <p>Inscrits total</p>
                </div>
                <div class="feature-box">
                    <h3 id="totalCandidates">0</h3>
                    <p>Candidats</p>
                </div>
                <div class="feature-box">
                    <h3 id="totalVotes">0</h3>
                    <p>Votes exprimés</p>
                </div>
                <div class="feature-box">
                    <h3 id="totalDelegates">0</h3>
                    <p>Délégués à élire</p>
                </div>
            </div>
            
            <!-- Section Gestion des Dates -->
            <div class="admin-section">
                <h2>Gestion des Dates et Configuration</h2>
                <div class="form-group">
                    <label for="dateOuvertureInscription">Date d'ouverture des inscriptions</label>
                    <input type="datetime-local" id="dateOuvertureInscription" 
                           value="<?= $config['date_ouverture_inscription'] ? date('Y-m-d\TH:i', strtotime($config['date_ouverture_inscription'])) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="dateFermetureInscription">Date de fermeture des inscriptions</label>
                    <input type="datetime-local" id="dateFermetureInscription" 
                           value="<?= $config['date_fermeture_inscription'] ? date('Y-m-d\TH:i', strtotime($config['date_fermeture_inscription'])) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="dateVote">Date de début du vote</label>
                    <input type="datetime-local" id="dateVote" 
                           value="<?= $config['date_vote'] ? date('Y-m-d\TH:i', strtotime($config['date_vote'])) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="dateFinVote">Date de fin du vote</label>
                    <input type="datetime-local" id="dateFinVote" 
                           value="<?= $config['date_fin_vote'] ? date('Y-m-d\TH:i', strtotime($config['date_fin_vote'])) : '' ?>">
                </div>
                <button id="saveDatesButton" class="success">Enregistrer les dates</button>
                <div id="datesMessage" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Section Configuration des Délégués -->
            <div class="admin-section">
                <h2>Configuration du Nombre de Délégués</h2>
                <div class="form-group">
                    <label for="inspecteurDelegates">Nombre de délégués Inspecteurs</label>
                    <input type="number" id="inspecteurDelegates" value="<?= $config['inspecteur_delegates'] ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="controleurDelegates">Nombre de délégués Contrôleurs/Sous-Officiers</label>
                    <input type="number" id="controleurDelegates" value="<?= $config['controleur_delegates'] ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="agentDelegates">Nombre de délégués Agents</label>
                    <input type="number" id="agentDelegates" value="<?= $config['agent_delegates'] ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="preposeDelegates">Nombre de délégués Préposés</label>
                    <input type="number" id="preposeDelegates" value="<?= $config['prepose_delegates'] ?>" min="1">
                </div>
                <div class="form-group">
                    <label for="retraiteDelegates">Nombre de délégués Retraités</label>
                    <input type="number" id="retraiteDelegates" value="<?= $config['retraite_delegates'] ?>" min="1">
                </div>
                <button id="saveDelegatesCountButton" class="success">Enregistrer la configuration</button>
                <div id="delegatesConfigMessage" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Section Liste des Inscrits -->
            <div class="admin-section">
                <h2>Liste des Inscrits</h2>
                <div class="alert alert-info">
                    <p>Total des inscrits : <strong id="totalParticipantsCount">0</strong></p>
                </div>
                
                <div class="action-buttons">
                    <button id="refreshParticipantsButton" class="info">Actualiser la liste</button>
                    <button id="exportParticipantsButton" class="info">Exporter en CSV</button>
                </div>
                
                <table id="participantsTable">
                    <thead>
                        <tr>
                            <th>Date Inscription</th>
                            <th>Grade</th>
                            <th>Prénom</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Matricule</th>
                            <th>Candidat</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="participantsList"></tbody>
                </table>
                
                <div id="paginationContainer" class="pagination"></div>
            </div>
            
            <!-- Section Résultats des Votes -->
            <div class="admin-section">
                <h2>Résultats des Votes</h2>
                <div class="action-buttons">
                    <button id="countVotesButton" class="success">Dépouiller les votes</button>
                    <button id="viewResultsButton" class="info">Voir les résultats</button>
                </div>
                
                <div id="resultsContainer" class="hidden"></div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        const GRADE_MAPPING = {
            'inspecteur': 'Inspecteur',
            'controleur_sous_officier': 'Contrôleur/Sous-Officier',
            'agent_constatation_brevete': 'Agent de Constatation/Agent Breveté',
            'prepose': 'Préposé',
            'retraite': 'Retraité'
        };
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            updateStats();
            if (document.getElementById('adminPanel').style.display !== 'none') {
                loadParticipants();
            }
            
            // Écouteurs d'événements
            document.getElementById('loginAdminButton').addEventListener('click', loginAdmin);
            document.getElementById('logoutButton').addEventListener('click', logoutAdmin);
            document.getElementById('saveDatesButton').addEventListener('click', saveDates);
            document.getElementById('saveDelegatesCountButton').addEventListener('click', saveDelegatesCount);
            document.getElementById('refreshParticipantsButton').addEventListener('click', () => {
                currentPage = 1;
                loadParticipants();
            });
            document.getElementById('exportParticipantsButton').addEventListener('click', exportParticipants);
            document.getElementById('countVotesButton').addEventListener('click', countVotes);
            document.getElementById('viewResultsButton').addEventListener('click', viewResults);
            
            // Écouteurs pour mettre à jour les stats quand les nombres de délégués changent
            document.getElementById('inspecteurDelegates').addEventListener('change', updateLocalStats);
            document.getElementById('controleurDelegates').addEventListener('change', updateLocalStats);
            document.getElementById('agentDelegates').addEventListener('change', updateLocalStats);
            document.getElementById('preposeDelegates').addEventListener('change', updateLocalStats);
            document.getElementById('retraiteDelegates').addEventListener('change', updateLocalStats);

            // Gestion de la touche Entrée pour la connexion
            document.getElementById('adminPassword').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loginAdmin();
                }
            });
        });

        function showMessage(elementId, message, type) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            element.textContent = message;
            element.className = 'alert';
            
            if (type === 'error') {
                element.classList.add('alert-error');
            } else if (type === 'success') {
                element.classList.add('alert-success');
            } else if (type === 'info') {
                element.classList.add('alert-info');
            } else if (type === 'warning') {
                element.classList.add('alert-warning');
            }
            
            element.style.display = 'block';
            
            // Masquer le message après 5 secondes
            setTimeout(() => {
                element.style.display = 'none';
            }, 5000);
        }

        function loginAdmin() {
            const password = document.getElementById('adminPassword').value.trim();
            
            if (!password) {
                showMessage('adminLoginMessage', 'Veuillez saisir le mot de passe', 'error');
                return;
            }
            
            const button = document.getElementById('loginAdminButton');
            button.disabled = true;
            button.textContent = 'Connexion...';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=login&password=${encodeURIComponent(password)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('loginSection').style.display = 'none';
                    document.getElementById('adminPanel').style.display = 'block';
                    document.getElementById('logoutButton').style.display = 'inline-block';
                    showMessage('adminLoginMessage', data.message, 'success');
                    updateStats();
                    loadParticipants();
                } else {
                    showMessage('adminLoginMessage', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('adminLoginMessage', 'Erreur lors de la connexion. Vérifiez votre connexion.', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = 'Se connecter';
            });
        }

        function logoutAdmin() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=logout'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('adminPanel').style.display = 'none';
                    document.getElementById('loginSection').style.display = 'block';
                    document.getElementById('logoutButton').style.display = 'none';
                    document.getElementById('adminPassword').value = '';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function updateStats() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalParticipants').textContent = data.stats.total_participants || 0;
                    document.getElementById('totalParticipantsCount').textContent = data.stats.total_participants || 0;
                    document.getElementById('totalCandidates').textContent = data.stats.total_candidates || 0;
                    document.getElementById('totalVotes').textContent = data.stats.total_votes || 0;
                    document.getElementById('totalDelegates').textContent = data.stats.total_delegates || 0;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function updateLocalStats() {
            const inspecteur = parseInt(document.getElementById('inspecteurDelegates').value) || 0;
            const controleur = parseInt(document.getElementById('controleurDelegates').value) || 0;
            const agent = parseInt(document.getElementById('agentDelegates').value) || 0;
            const prepose = parseInt(document.getElementById('preposeDelegates').value) || 0;
            const retraite = parseInt(document.getElementById('retraiteDelegates').value) || 0;
            
            const total = inspecteur + controleur + agent + prepose + retraite;
            document.getElementById('totalDelegates').textContent = total;
        }

        function saveDates() {
            const dateOuverture = document.getElementById('dateOuvertureInscription').value;
            const dateFermeture = document.getElementById('dateFermetureInscription').value;
            const dateVote = document.getElementById('dateVote').value;
            const dateFinVote = document.getElementById('dateFinVote').value;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_dates&date_ouverture=${encodeURIComponent(dateOuverture)}` +
                      `&date_fermeture=${encodeURIComponent(dateFermeture)}` +
                      `&date_vote=${encodeURIComponent(dateVote)}` +
                      `&date_fin_vote=${encodeURIComponent(dateFinVote)}`
            })
            .then(response => response.json())
            .then(data => {
                showMessage('datesMessage', data.message, data.success ? 'success' : 'error');
            })
            .catch(error => {
                showMessage('datesMessage', 'Erreur lors de l\'enregistrement', 'error');
                console.error('Error:', error);
            });
        }

        function saveDelegatesCount() {
            const inspecteur = document.getElementById('inspecteurDelegates').value;
            const controleur = document.getElementById('controleurDelegates').value;
            const agent = document.getElementById('agentDelegates').value;
            const prepose = document.getElementById('preposeDelegates').value;
            const retraite = document.getElementById('retraiteDelegates').value;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_delegates_count&inspecteur_delegates=${inspecteur}` +
                      `&controleur_delegates=${controleur}` +
                      `&agent_delegates=${agent}` +
                      `&prepose_delegates=${prepose}` +
                      `&retraite_delegates=${retraite}`
            })
            .then(response => response.json())
            .then(data => {
                showMessage('delegatesConfigMessage', data.message, data.success ? 'success' : 'error');
                updateStats();
            })
            .catch(error => {
                showMessage('delegatesConfigMessage', 'Erreur lors de l\'enregistrement', 'error');
                console.error('Error:', error);
            });
        }

        function loadParticipants(page = 1) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_participants&page=${page}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('participantsList');
                    tbody.innerHTML = '';

                    data.participants.forEach(participant => {
                        const tr = document.createElement('tr');
                        const dateInscription = new Date(participant.date_inscription);
                        tr.innerHTML = `
                            <td>${dateInscription.toLocaleString('fr-FR')}</td>
                            <td>${formatGrade(participant.grade)}</td>
                            <td>${escapeHtml(participant.prenom)}</td>
                            <td>${escapeHtml(participant.nom)}</td>
                            <td>${escapeHtml(participant.telephone)}</td>
                            <td>${escapeHtml(participant.matricule)}</td>
                            <td>${participant.is_candidat == 1 ? 'Oui' : 'Non'}</td>
                            <td>
                                <button onclick="deleteParticipant(${participant.id})" class="danger">Supprimer</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    if (data.pages) {
                        setupPagination(data.total, data.pages, page);
                        currentPage = page;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function setupPagination(total, pages, currentPage) {
            const paginationContainer = document.getElementById('paginationContainer');
            paginationContainer.innerHTML = '';

            if (pages <= 1) return;

            // Bouton Précédent
            const prevButton = document.createElement('button');
            prevButton.textContent = 'Précédent';
            prevButton.disabled = currentPage === 1;
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) loadParticipants(currentPage - 1);
            });
            paginationContainer.appendChild(prevButton);

            // Numéros de page
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(pages, currentPage + 2);

            if (startPage > 1) {
                const firstButton = document.createElement('button');
                firstButton.textContent = '1';
                firstButton.addEventListener('click', () => loadParticipants(1));
                paginationContainer.appendChild(firstButton);
                
                if (startPage > 2) {
                    const dots = document.createElement('span');
                    dots.textContent = '...';
                    dots.style.padding = '10px';
                    paginationContainer.appendChild(dots);
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                if (i === currentPage) {
                    pageButton.classList.add('active');
                }
                pageButton.addEventListener('click', () => loadParticipants(i));
                paginationContainer.appendChild(pageButton);
            }

            if (endPage < pages) {
                if (endPage < pages - 1) {
                    const dots = document.createElement('span');
                    dots.textContent = '...';
                    dots.style.padding = '10px';
                    paginationContainer.appendChild(dots);
                }
                
                const lastButton = document.createElement('button');
                lastButton.textContent = pages;
                lastButton.addEventListener('click', () => loadParticipants(pages));
                paginationContainer.appendChild(lastButton);
            }

            // Bouton Suivant
            const nextButton = document.createElement('button');
            nextButton.textContent = 'Suivant';
            nextButton.disabled = currentPage === pages;
            nextButton.addEventListener('click', () => {
                if (currentPage < pages) loadParticipants(currentPage + 1);
            });
            paginationContainer.appendChild(nextButton);
        }

        function formatGrade(grade) {
            return GRADE_MAPPING[grade] || grade;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function deleteParticipant(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce participant ?')) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_participant&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('datesMessage', data.message, 'success');
                        loadParticipants(currentPage);
                        updateStats();
                    } else {
                        showMessage('datesMessage', data.message, 'error');
                    }
                })
                .catch(error => {
                    showMessage('datesMessage', 'Erreur lors de la suppression', 'error');
                    console.error('Error:', error);
                });
            }
        }

        function exportParticipants() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_participants&page=all'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let csv = 'Date Inscription,Grade,Prénom,Nom,Téléphone,Matricule,Candidat\n';
                    
                    data.participants.forEach(p => {
                        const dateInscription = new Date(p.date_inscription);
                        csv += `"${dateInscription.toLocaleString('fr-FR')}","${formatGrade(p.grade)}",` +
                               `"${p.prenom}","${p.nom}","${p.telephone}","${p.matricule}",` +
                               `"${p.is_candidat == 1 ? 'Oui' : 'Non'}"\n`;
                    });
                    
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', `participants_${new Date().toISOString().split('T')[0]}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function countVotes() {
            if (confirm('Êtes-vous sûr de vouloir procéder au dépouillement des votes ?')) {
                const button = document.getElementById('countVotesButton');
                button.disabled = true;
                button.textContent = 'Dépouillement en cours...';
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=count_votes'
                })
                .then(response => response.json())
                .then(data => {
                    showMessage('datesMessage', data.message, data.success ? 'success' : 'error');
                    updateStats();
                })
                .catch(error => {
                    showMessage('datesMessage', 'Erreur lors du dépouillement', 'error');
                    console.error('Error:', error);
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = 'Dépouiller les votes';
                });
            }
        }

        function viewResults() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_results'
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('resultsContainer');
                container.innerHTML = '';
                
                if (data.success && data.results.length > 0) {
                    // Grouper les résultats par catégorie
                    const categories = {};
                    data.results.forEach(result => {
                        if (!categories[result.category]) {
                            categories[result.category] = [];
                        }
                        categories[result.category].push(result);
                    });
                    
                    // Afficher les résultats par catégorie
                    Object.entries(categories).forEach(([category, results]) => {
                        const categoryDiv = document.createElement('div');
                        categoryDiv.className = 'delegates-section';
                        
                        // Calculer le total des votes pour cette catégorie
                        const totalVotes = results.reduce((sum, result) => sum + parseInt(result.votes_count), 0);
                        
                        let html = `<h3>${formatGrade(category)} (${totalVotes} votes)</h3>
                                   <table>
                                       <thead>
                                           <tr>
                                               <th>Position</th>
                                               <th>Candidat</th>
                                               <th>Voix</th>
                                               <th>%</th>
                                           </tr>
                                       </thead>
                                       <tbody>`;
                        
                        results.forEach((result, index) => {
                            html += `<tr>
                                        <td>${index + 1}</td>
                                        <td>${escapeHtml(result.candidate_name)}</td>
                                        <td>${result.votes_count}</td>
                                        <td>${parseFloat(result.percentage).toFixed(2)}%</td>
                                    </tr>`;
                        });
                        
                        html += `</tbody></table>`;
                        categoryDiv.innerHTML = html;
                        container.appendChild(categoryDiv);
                    });
                } else {
                    container.innerHTML = '<div class="alert alert-info"><p>Aucun résultat disponible. Veuillez d\'abord effectuer le dépouillement.</p></div>';
                }
                
                container.classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                const container = document.getElementById('resultsContainer');
                container.innerHTML = '<div class="alert alert-error"><p>Erreur lors du chargement des résultats.</p></div>';
                container.classList.remove('hidden');
            });
        }
    </script>
</body>
</html>