<?php
// Fichier: get_satellite_position.php
// Gère la récupération de la position d'un satellite via l'API N2YO
// Sécurisé en utilisant la clé API côté serveur.
// Utilise curl pour une meilleure gestion des requêtes/erreurs.

header('Content-Type: application/json'); // S'assurer que la réponse est en JSON

// --- Configuration ---
$apiKeyFilePath = '/var/www/api_keys/space.key'; // Chemin vers la clé API
$n2yoApiBaseUrl = 'https://api.n2yo.com/rest/v1/satellite/'; // Utilisation du domaine de l'API
// Note : La doc donne n2yo.com, mais l'exemple donne api.n2yo.com. L'exemple est plus fiable.

// Satellites autorisés à suivre (pour éviter les requêtes arbitraires) ID du NORAD
$allowedSatellites = [
    'ISS' => 25544, // International Space Station
    'CSS' => 54216, // Chinese Space Station (Tiangong)
    'Hubble' => 20580  // Hubble Space Telescope
];

// --- Récupérer la clé API ---
$apiKey = trim(@file_get_contents($apiKeyFilePath)); // Utilisation de @ pour éviter les warnings si le fichier n'existe pas
if (empty($apiKey)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'API Key not found or empty.', 'code' => 'API_KEY_ERROR']);
    error_log("Error: N2YO API Key file not found or empty at " . $apiKeyFilePath);
    exit();
}

// --- Récupérer et valider l'ID du satellite demandé ---
$requestedSatelliteId = isset($_GET['satellite_id']) ? filter_var($_GET['satellite_id'], FILTER_SANITIZE_NUMBER_INT) : null;

if (!$requestedSatelliteId) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Satellite ID is required.', 'code' => 'MISSING_ID']);
    exit();
}

// Vérifier si l'ID est dans la liste des ID autorisés
$isValidSatellite = false;
foreach ($allowedSatellites as $id) {
    if ((string)$id === (string)$requestedSatelliteId) { // Comparaison stricte mais tolérante sur le type
        $isValidSatellite = true;
        break;
    }
}

if (!$isValidSatellite) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid Satellite ID.', 'code' => 'INVALID_ID']);
    error_log("Invalid Satellite ID requested: " . $requestedSatelliteId);
    exit();
}


// --- Construire l'URL de l'API N2YO ---
// Endpoint: /positions/{id}/{observer_lat}/{observer_lng}/{observer_alt}/{seconds}/&apiKey={your_api_key} <-- Format selon l'exemple N2YO
$api_url = $n2yoApiBaseUrl . "positions/" . $requestedSatelliteId . "/0/0/0/1/&apiKey=" . urlencode($apiKey); // URL-encoder la clé par sécurité

// --- Récupérer les données depuis N2YO via cURL ---
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retourne le contenu au lieu de l'afficher
curl_setopt($ch, CURLOPT_HEADER, false); // N'inclut pas les headers dans la sortie
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suit les redirections
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 secondes
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout de connexion de 5 secondes
// Optionnel: Désactiver la vérification SSL si nécessaire (déconseillé en production)
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'cURL Error fetching data from N2YO API: ' . $curlError, 'code' => 'CURL_ERROR']);
    error_log("cURL Error fetching data from N2YO API for ID " . $requestedSatelliteId . ". URL: " . $api_url . " Error: " . $curlError);
    exit();
}

if ($httpCode !== 200) {
     // Tenter de décoder même une réponse non-200 pour voir si N2YO renvoie un JSON d'erreur
    $data = json_decode($response, true);
    $errorMessage = 'N2YO API returned HTTP status ' . $httpCode;
     if (isset($data['status'])) {
         $errorMessage .= ' Status: ' . $data['status'];
         if(isset($data['status']['message'])) {
             $errorMessage .= ' - ' . $data['status']['message'];
         }
    } else {
        $errorMessage .= '. Response: ' . substr($response, 0, 200) . '...'; // Limiter la taille du log
    }

    http_response_code($httpCode); // Renvoyer le statut HTTP reçu de N2YO
    echo json_encode(['error' => $errorMessage, 'code' => 'HTTP_ERROR', 'status' => $httpCode]);
    error_log("N2YO API HTTP Error for ID " . $requestedSatelliteId . ". URL: " . $api_url . " Response: " . $response);
    exit();
}


$data = json_decode($response, true);

// --- Analyser la réponse JSON ---
// Vérifier si le JSON est valide et contient les données attendues
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500); // Internal Server Error
     echo json_encode(['error' => 'Invalid JSON response from N2YO API: ' . json_last_error_msg(), 'code' => 'JSON_ERROR']);
     error_log("Invalid JSON response from N2YO API for ID " . $requestedSatelliteId . ". Response: " . $response);
    exit();
}

if (!isset($data) || !is_array($data) || !isset($data['positions']) || empty($data['positions'])) {
    $errorMessage = 'Invalid or empty data structure in N2YO API response.';
     if (isset($data['status'])) {
         $errorMessage .= ' Status: ' . $data['status'];
         if(isset($data['status']['message'])) {
             $errorMessage .= ' - ' . $data['status']['message'];
         }
    }

    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $errorMessage, 'code' => 'INVALID_DATA_STRUCTURE']);
    error_log("Invalid data structure in N2YO API response for ID " . $requestedSatelliteId . ": " . $response);
    exit();
}

// Extraire la première (et unique) position si seconds=1
$latestPosition = $data['positions'][0];

// --- Renvoyer la position au format JSON ---
echo json_encode([
    'id' => $requestedSatelliteId,
    'latitude' => $latestPosition['satlatitude'],
    'longitude' => $latestPosition['satlongitude'],
    'altitude' => $latestPosition['sataltitude'], // L'altitude est aussi disponible
    'timestamp' => $latestPosition['timestamp'],
     'name' => $data['info']['satname'] ?? 'Unknown Satellite' // Ajouter le nom du satellite
]);

?>
