<?php
// votes.php
require_once 'config.php';

header('Content-Type: application/json');

// Autoriser les requêtes CORS si nécessaire
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$db = getDBConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_user':
            $matricule = $_GET['matricule'] ?? '';
            if (empty($matricule)) {
                echo json_encode(['error' => 'Matricule requis']);
                exit;
            }
            
            $stmt = $db->prepare("SELECT * FROM participants WHERE matricule = ?");
            $stmt->execute([$matricule]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['error' => 'Utilisateur non trouvé']);
                exit;
            }
            
            echo json_encode($user);
            break;
            
        case 'get_candidates':
            $grade = $_GET['grade'] ?? '';
            if (empty($grade)) {
                echo json_encode(['error' => 'Grade requis']);
                exit;
            }
            
            $stmt = $db->prepare("
                SELECT matricule, prenom, nom, grade, motivation 
                FROM participants 
                WHERE grade = ? AND is_candidat = TRUE
                ORDER BY nom, prenom
            ");
            $stmt->execute([$grade]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($candidates);
            break;
            
        case 'submit_vote':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation des données
            if (empty($data['matriculeElecteur']) || empty($data['matriculeCandidat'])) {
                echo json_encode(['error' => 'Données de vote incomplètes']);
                exit;
            }
            
            // Vérifier si l'électeur a déjà voté
            $stmt = $db->prepare("SELECT a_vote FROM participants WHERE matricule = ?");
            $stmt->execute([$data['matriculeElecteur']]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($participant && $participant['a_vote']) {
                echo json_encode(['error' => 'Cet électeur a déjà voté']);
                exit;
            }
            
            // Enregistrer le vote
            $db->beginTransaction();
            
            try {
                // Ajouter le vote
                $stmt = $db->prepare("INSERT INTO votes (matricule_electeur, matricule_candidat) VALUES (?, ?)");
                $stmt->execute([$data['matriculeElecteur'], $data['matriculeCandidat']]);
                
                // Mettre à jour le statut de l'électeur
                $stmt = $db->prepare("UPDATE participants SET a_vote = TRUE, date_vote = NOW() WHERE matricule = ?");
                $stmt->execute([$data['matriculeElecteur']]);
                
                $db->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['error' => 'Erreur lors de l\'enregistrement du vote: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_election_dates':
            $stmt = $db->query("SELECT voting_start_date, voting_end_date FROM election_settings LIMIT 1");
            $dates = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dates) {
                // Dates par défaut si non définies
                $defaultStart = date('Y-m-d', strtotime('+1 week'));
                $defaultEnd = date('Y-m-d', strtotime('+2 weeks'));
                
                $stmt = $db->prepare("
                    INSERT INTO election_settings (voting_start_date, voting_end_date) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$defaultStart, $defaultEnd]);
                
                $dates = ['voting_start_date' => $defaultStart, 'voting_end_date' => $defaultEnd];
            }
            
            echo json_encode($dates);
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
// Remplacer la fonction principale par ceci
document.addEventListener("DOMContentLoaded", function () {
    // Vérifier si l'utilisateur est connecté
    const matricule = sessionStorage.getItem("currentMatricule");
    if (!matricule) {
        window.location.href = "connexion.html";
        return;
    }

    // Charger les informations de l'utilisateur depuis la base de données
    fetch(`votes.php?action=get_user&matricule=${matricule}`)
        .then(response => response.json())
        .then(user => {
            if (user.error) {
                alert(user.error);
                window.location.href = "connexion.html";
                return;
            }

            // Afficher les informations de l'utilisateur
            const fullName = ((user.prenom || "") + " " + (user.nom || "")).trim();
            const userGrade = (user.grade || "").trim();

            document.getElementById("userName").textContent = fullName.toUpperCase();
            document.getElementById("userGrade").textContent = userGrade;

            // Vérifier si l'utilisateur a déjà voté
            if (user.a_vote) {
                alert("Vous avez déjà voté. Un seul vote par personne est autorisé.");
                window.location.href = "connexion.html";
                return;
            }

            // Charger les dates d'élection
            return fetch('votes.php?action=get_election_dates')
                .then(response => response.json())
                .then(dates => {
                    // Afficher les dates de vote dans le message
                    document.getElementById('startDate').textContent = formatDate(dates.voting_start_date);
                    document.getElementById('endDate').textContent = formatDate(dates.voting_end_date);

                    // Vérifier si la période de vote est active
                    if (!isVotingPeriodActive(dates.voting_start_date, dates.voting_end_date)) {
                        // Masquer la section de vote
                        document.getElementById('voteSection').style.display = 'none';
                        // Afficher le message de période hors vote
                        document.getElementById('votingPeriodMessage').style.display = 'block';
                    } else {
                        // Charger les candidats
                        loadCandidates(userGrade);
                    }
                });
        })
        .catch(error => {
            console.error("Erreur:", error);
            alert("Une erreur est survenue lors du chargement des données.");
        });
});

// Modifier la fonction loadCandidates pour utiliser la base de données
function loadCandidates(userGrade) {
    const loadingIndicator = document.getElementById("loadingIndicator");
    const container = document.getElementById("candidatesList");
    
    fetch(`votes.php?action=get_candidates&grade=${encodeURIComponent(userGrade)}`)
        .then(response => response.json())
        .then(candidates => {
            // Supprimer l'indicateur de chargement
            loadingIndicator.remove();

            // Vérifier s'il y a des candidats
            if (candidates.error) {
                document.getElementById("noCandidate").style.display = "block";
                document.getElementById("submitVote").disabled = true;
                return;
            }

            if (candidates.length === 0) {
                document.getElementById("noCandidate").style.display = "block";
                document.getElementById("submitVote").disabled = true;
                return;
            }

            // Afficher les candidats avec une animation progressive
            candidates.forEach((candidat, index) => {
                const fullName = ((candidat.prenom || "") + " " + (candidat.nom || "")).trim();
                const motivation = candidat.motivation || "Candidat à l'élection des délégués.";
                
                // Créer la carte du candidat
                const div = document.createElement("div");
                div.className = "candidate-card";
                div.style.opacity = "0";
                div.style.transform = "translateY(20px)";
                div.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                
                div.innerHTML = `
                    <div class="candidate-header">
                        <h4 class="candidate-name">${fullName}</h4>
                    </div>
                    <div class="candidate-body">
                        <div class="candidate-motivation">${motivation}</div>
                        <label class="vote-option">
                            <input type="radio" name="candidate" value="${candidat.matricule}">
                            <span>Voter pour ce candidat</span>
                        </label>
                    </div>
                `;
                
                container.appendChild(div);
                
                // Animer l'apparition avec un délai progressif
                setTimeout(() => {
                    div.style.opacity = "1";
                    div.style.transform = "translateY(0)";
                }, 100 * index);
                
                // Ajouter l'événement de sélection
                const radioInput = div.querySelector('input[type="radio"]');
                radioInput.addEventListener('change', function() {
                    document.querySelectorAll('.candidate-card').forEach(card => {
                        card.classList.remove('selected-candidate');
                    });
                    
                    if (this.checked) {
                        div.classList.add('selected-candidate');
                        document.getElementById('submitVote').disabled = false;
                    }
                });
            });
        })
        .catch(error => {
            console.error("Erreur lors du chargement des candidats:", error);
            loadingIndicator.textContent = "Erreur lors du chargement des candidats.";
            loadingIndicator.style.color = "#ef4444";
        });
    
    // Modifier l'événement de soumission du vote
    document.getElementById("submitVote").addEventListener("click", function() {
        const selectedCandidate = document.querySelector('input[name="candidate"]:checked');
        
        if (!selectedCandidate) {
            alert("Veuillez sélectionner un candidat pour voter.");
            return;
        }
        
        showLoader();
        
        const matricule = sessionStorage.getItem("currentMatricule");
        const voteData = {
            matriculeElecteur: matricule,
            matriculeCandidat: selectedCandidate.value
        };
        
        fetch('votes.php?action=submit_vote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(voteData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Marquer la session comme terminée
            sessionStorage.setItem("voteComplete", "true");
            
            // Animation de confirmation
            hideLoader();
            animateVoteSuccess();
        })
        .catch(error => {
            console.error("Erreur lors de l'enregistrement du vote:", error);
            hideLoader();
            alert("Une erreur est survenue lors de l'enregistrement de votre vote: " + error.message);
        });
    });
}
?>
