<?php
require_once 'config.php';

// Initialiser la base de données
initializeDatabase();

// Traitement AJAX pour l'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    header('Content-Type: application/json');
    
    try {
        $grade = trim($_POST['grade']);
        $prenom = trim($_POST['prenom']);
        $nom = trim($_POST['nom']);
        $telephone = preg_replace('/\D/', '', trim($_POST['telephone'])); // Nettoyer le numéro
        $matricule = strtoupper(trim($_POST['matricule']));
        $isCandidat = isset($_POST['isCandidat']) && $_POST['isCandidat'] === 'true';
        $motivation = $isCandidat ? trim($_POST['motivation']) : '';
        
        // Validation
        if (empty($grade) || empty($prenom) || empty($nom) || empty($matricule)) {
            echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
            exit;
        }
        
        if (strlen($telephone) !== 9) {
            echo json_encode(['success' => false, 'message' => 'Numéro de téléphone invalide (9 chiffres requis)']);
            exit;
        }
        
        if (!preg_match('/^[0-9]{6}[A-Za-z]$/', $matricule)) {
            echo json_encode(['success' => false, 'message' => 'Matricule invalide (6 chiffres + 1 lettre)']);
            exit;
        }
        
        if ($isCandidat && empty($motivation)) {
            echo json_encode(['success' => false, 'message' => 'La motivation est requise pour les candidats']);
            exit;
        }
        
        $db = getDBConnection();
        
        // Vérifier si le matricule existe déjà
        $stmt = $db->prepare("SELECT id FROM participants WHERE matricule = ?");
        $stmt->execute([$matricule]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ce matricule est déjà enregistré']);
            exit;
        }
        
        // Insérer le participant
        $stmt = $db->prepare("INSERT INTO participants (grade, prenom, nom, telephone, matricule, is_candidat, motivation) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$grade, $prenom, $nom, $telephone, $matricule, $isCandidat, $motivation]);
        
        echo json_encode(['success' => true, 'message' => 'Inscription réussie !']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage()]);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Élections Mutuelle des Douanes</title>
    <style>
        :root {
            --primary-color: #1a5a96;
            --secondary-color: #144272;
            --error-color: #d9534f;
            --success-color: #5cb85c;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        header img {
            max-height: 100px;
            margin-bottom: 15px;
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .form-container {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background: white;
        }
        
        .required:after {
            content: " *";
            color: var(--error-color);
        }
        
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(26, 90, 150, 0.2);
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            width: 100%;
        }
        
        button:hover {
            background-color: var(--secondary-color);
        }
        
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .error {
            color: var(--error-color);
            font-size: 0.8em;
            margin-top: 5px;
            display: none;
        }
        
        .success {
            color: var(--success-color);
            font-size: 0.9em;
            margin-top: 10px;
            display: none;
        }
        
        .info-box {
            background-color: #e7f4ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
        }
        
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            .form-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="logo_douanes.png" alt="Logo Mutuelle des Douanes">
        <h1>Inscription aux Élections 2025</h1>
        <p>Mutuelle des Douanes Sénégalaises - Élection triennale des délégués</p>
    </header>
    
    <div class="form-container">
        <div class="info-box">
            <p><strong>Informations importantes :</strong></p>
            <ul>
                <li>L'inscription est obligatoire pour participer au vote</li>
                <li>Les élections ont lieu tous les 3 ans</li>
                <li>Après inscription, vous pourrez voter à la date prévue</li>
            </ul>
        </div>
        
        <form id="inscriptionForm">
            <div class="form-section">
                <h2>Informations personnelles</h2>
                
                <label for="grade" class="required">Grade</label>
                <select id="grade" name="grade" required>
                    <option value="">Sélectionnez votre grade</option>
                    <option value="inspecteur">Inspecteur</option>
                    <option value="controleur_sous_officier">Contrôleur/Sous-Officier</option>
                    <option value="agent_constatation_brevete">Agent de Constatation/Agent Breveté</option>
                    <option value="prepose">Préposé</option>
                    <option value="retraite">Retraité</option>
                </select>
                <div id="gradeError" class="error"></div>
                
                <label for="prenom" class="required">Prénom(s)</label>
                <input type="text" id="prenom" name="prenom" required>
                <div id="prenomError" class="error"></div>
                
                <label for="nom" class="required">Nom</label>
                <input type="text" id="nom" name="nom" required>
                <div id="nomError" class="error"></div>
                
                <label for="telephone" class="required">Numéro de Téléphone</label>
                <input type="tel" id="telephone" name="telephone" required placeholder="Ex: 771234567">
                <div id="telephoneError" class="error"></div>
                
                <label for="matricule" class="required">Matricule de Solde</label>
                <input type="text" id="matricule" name="matricule" required placeholder="Ex: 123456A">
                <div id="matriculeError" class="error"></div>
            </div>
            
            <div class="form-section">
                <label>
                    <input type="checkbox" id="candidatCheckbox">
                    <strong>Je souhaite me porter candidat</strong>
                </label>
                
                <div id="candidatSection" style="display: none; margin-top: 15px;">
                    <label for="motivation">Motivation (pourquoi vous candidatez?)</label>
                    <textarea id="motivation" name="motivation" rows="4"></textarea>
                </div>
            </div>
            
            <div class="info-box">
                <p><strong>Identifiants de connexion :</strong></p>
                <ul>
                    <li><strong>Identifiant :</strong> Votre matricule de solde</li>
                    <li><strong>Mot de passe :</strong> douanes2025</li>
                </ul>
                <p>Vous recevrez un SMS de confirmation une fois votre inscription validée.</p>
            </div>
            
            <button type="submit" id="registerButton">S'inscrire</button>
            
            <div class="loading" id="loading">
                <p>Inscription en cours...</p>
            </div>
            
            <div id="registerMessage" class="success"></div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de la candidature
            document.getElementById('candidatCheckbox').addEventListener('change', function() {
                document.getElementById('candidatSection').style.display = this.checked ? 'block' : 'none';
            });
            
            // Gestion du formulaire
            document.getElementById('inscriptionForm').addEventListener('submit', function(e) {
                e.preventDefault();
                registerParticipant();
            });
        });
        
        function registerParticipant() {
            // Nettoyer les messages d'erreur précédents
            clearErrors();
            
            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('grade', document.getElementById('grade').value);
            formData.append('prenom', document.getElementById('prenom').value.trim());
            formData.append('nom', document.getElementById('nom').value.trim());
            formData.append('telephone', document.getElementById('telephone').value.trim());
            formData.append('matricule', document.getElementById('matricule').value.trim());
            formData.append('isCandidat', document.getElementById('candidatCheckbox').checked);
            formData.append('motivation', document.getElementById('motivation').value.trim());
            
            // Afficher le loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('registerButton').disabled = true;
            
            fetch('inscription.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('registerButton').disabled = false;
                
                if (data.success) {
                    // Affichage du message de succès
                    const messageEl = document.getElementById('registerMessage');
                    messageEl.textContent = data.message + ' Redirection vers l\'espace de vote...';
                    messageEl.style.display = 'block';
                    
                    // Redirection après 2 secondes
                    setTimeout(() => {
                        window.location.href = 'connexion.html';
                    }, 2000);
                } else {
                    // Affichage de l'erreur
                    alert(data.message);
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('registerButton').disabled = false;
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de l\'inscription. Veuillez réessayer.');
            });
        }
        
        function clearErrors() {
            const errorElements = document.querySelectorAll('.error');
            errorElements.forEach(el => {
                el.style.display = 'none';
                el.textContent = '';
            });
        }
    </script>
</body>
</html>