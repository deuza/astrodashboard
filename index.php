<?php
// Fichier: index.php
// Affiche les astronautes (Open Notify) et le suivi des satellites (N2YO) avec carte Leaflet.
// Utilise curl pour les appels API.
// Mise à jour du layout et suppression du message de chargement initial.


// --- Configuration N2YO ---
$n2yoApiKeyFilePath = '/var/www/api_keys/space.key'; // Chemin vers le fichier clé API N2YO
$n2yoApiBaseUrl = 'https://api.n2yo.com/rest/v1/satellite/'; // Utilisation du domaine de l'API
$satellitesToTrack = [ // Satellites à suivre {Nom Affiché => ID N2YO}
	'ISS' => 25544, // International Space Station
	'CSS' => 54216, // Chinese Space Station (Tiangong)
	'Hubble' => 20580 // Hubble Space Telescope
];

// --- Configuration source astronautes ---
// L'API open-notify.org est non maintenue (donnees figees a 2024).
// On utilise un fichier JSON local maintenu manuellement (cf. README_astros.md).
// Format compatible avec open-notify (drop-in replacement) + champs additionnels.
$astrosJsonPath = __DIR__ . '/astros.json';


// --- Fonction Helper pour appels cURL ---
function fetchDataWithCurl($url, $timeout = 10) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retourne le contenu au lieu de l'afficher
	curl_setopt($ch, CURLOPT_HEADER, false); // N'inclut pas les headers dans la sortie
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suit les redirections
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // Timeout de 10 secondes
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout de connexion de 5 secondes
	// Optionnel: Désactiver la vérification SSL si nécessaire (déconseillé en production)
	// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlError = curl_error($ch);
	curl_close($ch);

	if ($response === false) {
		error_log("cURL Error fetching " . $url . ": " . $curlError);
		return ['error' => 'cURL Error', 'details' => $curlError, 'http_code' => null];
	}

  // On vérifie si la réponse ressemble à du JSON valide
  $data = json_decode($response, true);

  if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    // Ce n'est pas du JSON valide, probablement une page d'erreur HTML
    error_log("Non-JSON or Invalid JSON response from " . $url . " (HTTP Code: " . $httpCode . "): " . substr($response, 0, 200) . "...");
    return ['error' => 'Invalid or non-JSON response', 'details' => 'HTTP Status: ' . $httpCode, 'http_code' => $httpCode, 'response' => $response];
  }


  if ($httpCode !== 200) {
    // C'est du JSON, mais le statut n'est pas 200. Examiner le JSON pour un message d'erreur N2YO
    $errorMessage = 'N2YO API returned HTTP status ' . $httpCode;
    if (isset($data['status'])) {
      $errorMessage .= ' Status: ' . $data['status'];
      if(isset($data['status']['message'])) {
        $errorMessage .= ' - ' . $data['status']['message'];
      }
    }

    error_log("N2YO API HTTP Error for URL " . $url . ": " . $errorMessage . " Full response: " . $response);
    return ['error' => $errorMessage, 'code' => 'HTTP_ERROR', 'status' => $httpCode, 'data' => $data]; // Inclure data pour voir le message N2YO
  }


  return ['data' => $data, 'http_code' => $httpCode];
}


// --- Récupérer la clé API N2YO ---
$n2yoApiKey = trim(@file_get_contents($n2yoApiKeyFilePath));
$n2yo_api_key_error = empty($n2yoApiKey);
if ($n2yo_api_key_error) {
  error_log("Error: N2YO API Key file not found or empty at " . $n2yoApiKeyFilePath);
}


// --- Récupérer les données initiales des satellites (N2YO) ---
$initial_satellite_data = [];
$n2yo_api_error = $n2yo_api_key_error; // Marquer l'erreur N2YO si la clé manque

if (!$n2yo_api_key_error) {
  foreach ($satellitesToTrack as $name => $id) {
    // Endpoint: /positions/{id}/{observer_lat}/{observer_lng}/{observer_alt}/{seconds}/&apiKey={your_api_key}
    $api_url = $n2yoApiBaseUrl . "positions/" . $id . "/0/0/0/1/&apiKey=" . urlencode($n2yoApiKey); // <-- Format selon l'exemple N2YO + encodage clé

    $result = fetchDataWithCurl($api_url);

    // Vérifier si la requête a réussi et si les données attendues sont présentes
    if (isset($result['data']) && isset($result['data']['positions']) && !empty($result['data']['positions'])) {
      $initial_satellite_data[$id] = [
        'name' => $result['data']['info']['satname'] ?? $name, // Utiliser le nom de l'API si dispo
        'latitude' => $result['data']['positions'][0]['satlatitude'],
        'longitude' => $result['data']['positions'][0]['satlongitude'],
        'altitude' => $result['data']['positions'][0]['sataltitude'],
        'timestamp' => $result['data']['positions'][0]['timestamp'],
      ];
    } else {
      $n2yo_api_error = true; // Marquer une erreur si la récupération d'un satellite échoue
      error_log("Failed initial N2YO fetch for ID " . $id . " (Name: " . $name . "). Result: " . print_r($result, true));
      // Initialiser avec des valeurs par défaut en cas d'échec
      $initial_satellite_data[$id] = [
        'name' => $name,
        'latitude' => 'N/A',
        'longitude' => 'N/A',
        'altitude' => 'N/A',
        'timestamp' => time(), // Timestamp approximatif du moment de l'échec
      ];
    }
  }
}

// --- Recuperer les donnees astronautes (fichier JSON local) ---
$astronautes = [];
$nombre_astronautes = 0;
$astros_updated = null;
$open_notify_error = false; // nom de variable conserve pour compatibilite avec le reste du code

if (!is_readable($astrosJsonPath)) {
  $open_notify_error = true;
  error_log("Fichier astros.json introuvable ou illisible: " . $astrosJsonPath);
} else {
  $astros_raw = @file_get_contents($astrosJsonPath);
  $astros_data = json_decode($astros_raw, true);

  if (json_last_error() !== JSON_ERROR_NONE || !is_array($astros_data)) {
    $open_notify_error = true;
    error_log("astros.json: JSON invalide (" . json_last_error_msg() . ")");
  } elseif (!isset($astros_data['message']) || $astros_data['message'] !== 'success') {
    $open_notify_error = true;
    error_log("astros.json: champ message absent ou != 'success'");
  } else {
    $astronautes = $astros_data['people'] ?? [];
    $nombre_astronautes = $astros_data['number'] ?? count($astronautes);
    $astros_updated = $astros_data['updated'] ?? null;
  }
}

// Récupérer la liste unique des vaisseaux de Open Notify
$vaisseaux = [];
if (!empty($astronautes)) {
  $vaisseaux = array_unique(array_column($astronautes, 'craft'));
  sort($vaisseaux); // Trier par ordre alphabétique
}


// --- Fonction Wikipédia (version simplifiée via recherche) ---
// On pointe vers la recherche Wikipédia
function getWikipediaSearchLink($name) {
  // Renvoyer directement l'URL de recherche
  return "https://en.wikipedia.org/w/index.php?search=" . urlencode($name);
}

$date_fetched = date("d/m/Y H:i:s");

// Déterminer si une erreur globale doit être affichée en haut
$global_api_error = $n2yo_api_error || $open_notify_error || $n2yo_api_key_error;


?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AstroDashboard</title>   <link rel="icon" href="telescope.ico">
  <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
   integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
   crossorigin=""/>

  <style>
    /* Style simple pour les boutons radio */
    .satellite-selector {
      margin: 20px 0;
      text-align: center; /* Centrer le contenu du sélecteur */
    }
    .satellite-selector p {
      margin-bottom: 10px;
    }
    .satellite-selector label {
      margin-right: 15px;
      cursor: pointer;
      color: #cccccc; /* Couleur par défaut */
    }
    .satellite-selector label input[type="radio"] {
      margin-right: 5px;
    }
    .satellite-selector label:hover {
      color: #ffcc00; /* Couleur au survol */
    }
    .satellite-selector label input[type="radio"]:checked + span {
      color: #ffcc00; /* Couleur pour l'option sélectionnée */
      font-weight: bold;
    }

    /* Style pour le conteneur de la carte Leaflet */
    #mapid {
      height: 400px;
      width: 100%;
      margin: 20px auto;
      border-radius: 5px;
      /* background-color: #222; /* Optionnel: fond sombre avant chargement */
    }

  </style>
</head>
<body>
          <div id="content">

    <header>
      <br>
      <img src="nasa-logo.svg" alt="NASA Logo" class="nasa-logo">
      <h1>🪐 AstroDashboard ⭐</h1>
      <?php if ($global_api_error): ?>
        <p style="color: red;">Des erreurs sont survenues lors de la récupération de certaines données API.</p>
        <?php if ($n2yo_api_key_error): ?>
          <p style="color: red; font-size: smaller;">Erreur clé API N2YO : Vérifier le fichier <?php echo htmlspecialchars($n2yoApiKeyFilePath); ?></p>
        <?php endif; ?>
        <?php if ($n2yo_api_error && !$n2yo_api_key_error): ?>
          <p style="color: orange; font-size: smaller;">Erreur lors de la récupération des données satellites via l'API N2YO.</p>
        <?php endif; ?>
        <?php if ($open_notify_error): ?>
          <p style="color: orange; font-size: smaller;">Erreur lors de la lecture du fichier local des astronautes (astros.json).</p>
        <?php endif; ?>

      <?php else: ?>
        <p><strong><?php echo htmlspecialchars($nombre_astronautes); ?></strong> astronautes en orbite</p>
        <p>Données mises à jour le : <strong><?php echo htmlspecialchars($date_fetched); ?></strong></p>
        <?php if ($astros_updated): ?>
          <p style="font-size: smaller; color: #888;">Liste des astronautes : maj manuelle au <?php echo htmlspecialchars($astros_updated); ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </header>

        <div class="container">
            <div class="tab">
        <button class="tablinks" onclick="openTab(event, 'Astronautes')">🧑‍🚀 Astronautes</button>
        <button class="tablinks" onclick="openTab(event, 'Vaisseaux')">🚀 Vaisseaux</button>
        <button class="tablinks" onclick="openTab(event, 'SatelliteTracking')">🛰️ Suivi Satellites</button>
      </div>

            <div id="Astronautes" class="tabcontent">
        <h2>🧑‍🚀 Liste des astronautes 🧑‍🚀</h2>
        <?php if ($open_notify_error): ?>
          <p style="color: red;">Impossible d'afficher la liste des astronautes en raison d'une erreur API.</p>
        <?php elseif (empty($astronautes)): ?>
          <p>Aucun astronaute actuellement en orbite selon les données disponibles.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Vaisseau</th>
                <th>Liens</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($astronautes as $astronaute): ?>
                <tr>
                  <td><?php echo htmlspecialchars($astronaute['name']); ?></td>
                  <td>
                    <a href="#" onclick="event.preventDefault(); openTab(event, 'Vaisseaux');"><?php echo htmlspecialchars($astronaute['craft']); ?></a>
                  </td>
                  <td>
                    <a href="<?php echo htmlspecialchars(getWikipediaSearchLink($astronaute['name'])); ?>" target="_blank">📖 Rechercher sur Wikipédia</a>
                    | <a href="https://www.google.com/search?q=<?php echo urlencode($astronaute['name'] . ' astronaut'); ?>" target="_blank">🔍 Google</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

            <div id="Vaisseaux" class="tabcontent">
        <h2>🚀 Vaisseaux en orbite 🚀</h2>
        <?php if ($open_notify_error): ?>
          <p style="color: red;">Impossible d'afficher la liste des vaisseaux en raison d'une erreur API.</p>
        <?php elseif (empty($vaisseaux)): ?>
          <p>Aucun vaisseau habité actuellement en orbite selon les données disponibles.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Nom du Vaisseau</th>
                <th>Liens</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vaisseaux as $craft): ?>
                <tr>
                  <td><?php echo htmlspecialchars($craft); ?></td>
                  <td>
                    <a href="<?php echo htmlspecialchars(getWikipediaSearchLink($craft)); ?>" target="_blank">📖 Rechercher sur Wikipédia</a>
                    | <a href="https://www.google.com/search?q=<?php echo urlencode($craft . ' spacecraft'); ?>" target="_blank">🔍 Google</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

            <div id="SatelliteTracking" class="tabcontent">
        <h2>🛰️ Position actuelle des satellites 🛰️</h2>

        <br>
        <?php if ($n2yo_api_error): ?>
          <p style="color: red;">Impossible d'afficher les positions des satellites en raison d'une erreur API N2YO.</p>
          <?php if ($n2yo_api_key_error): ?>
            <p style="color: red; font-size: smaller;">Erreur clé API N2YO : Vérifiez le fichier <?php echo htmlspecialchars($n2yoApiKeyFilePath); ?></p>
          <?php endif; ?>
        <?php else: ?>
         <hr>
                    <div id="satellite-coordinates">
            <?php foreach ($satellitesToTrack as $name => $id):
              // Utiliser les données initiales si disponibles, sinon N/A
              $lat = $initial_satellite_data[$id]['latitude'] ?? 'N/A';
              $lon = $initial_satellite_data[$id]['longitude'] ?? 'N/A';
              $alt = $initial_satellite_data[$id]['altitude'] ?? 'N/A';
              ?>
              <p>
                <strong><?php echo htmlspecialchars($name); ?> (ID: <?php echo htmlspecialchars($id); ?>):</strong>
                Latitude: <span id="lat-<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($lat); ?></span>,
                Longitude: <span id="lon-<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($lon); ?></span>
                , Altitude: <span id="alt-<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($alt); ?> km</span>
              </p>
            <?php endforeach; ?>
            <p style="font-size: small; color: #aaa;"><i><b>(Positions mises à jour toutes les 10 secondes si le tracking est actif)</b></i></p>
            <hr>
          </div>
          <br>
          <div class="satellite-selector">
            <p>Afficher sur la carte :</p>
            <?php
            $is_first = true;
            $default_mapped_satellite_id = null; // Pour mémoriser l'ID du premier satellite
            foreach ($satellitesToTrack as $name => $id):
              if ($is_first) {
                $default_mapped_satellite_id = $id;
              }
              ?>
              <label>
                <input type="radio" name="selected_satellite" value="<?php echo htmlspecialchars($id); ?>" <?php if ($is_first) echo 'checked'; ?>>
                <span><?php echo htmlspecialchars($name); ?></span>
              </label>
            <?php $is_first = false; endforeach; ?>
          </div>


          <button id="toggleTracking" class="tablinks"> ▶️ Lancer Tracking</button>

          <div id="mapid"></div>
          <p style="font-size: small; color: #aaa;">(La carte suit le satellite sélectionné ci-dessus)</p>

          <p><a href="https://heavens-above.com/" target="_blank">📡 Voir plus d'infos sur Heavens Above 📡</a></p>

        <?php endif; ?>

      </div>
    </div>     <br><hr>

    <footer>
      <i>Liste des astronautes : maintenue manuellement (cf. README_astros.md). Sources de reference : <a href="https://www.nasa.gov/international-space-station/expedition-missions/" target="_blank">NASA Expeditions</a>, <a href="https://www.ariss.org/current-iss-crew.html" target="_blank">ARISS</a>, <a href="https://en.wikipedia.org/wiki/List_of_current_spaceflight_crew_members" target="_blank">Wikipedia</a>.</i><br>
      <i>Les données de suivi satellite sont issues du site : <a href="https://www.n2yo.com/" target="_blank">https://www.n2yo.com/</a></i><br>
      <i>With ❤️ par 👾 DeuZa 👾</i><br>
      <br>

<a href="https://github.com/deuza/astrodashboard/">Astrodashboard</a> by <a href="https://github.com/deuza/">DeuZa</a> is marked <a href="https://creativecommons.org/publicdomain/zero/1.0/">CC0 1.0 Universal</a><img src="https://mirrors.creativecommons.org/presskit/icons/cc.svg" style="max-width: 1em;max-height:1em;margin-left: .2em;"><img src="https://mirrors.creativecommons.org/presskit/icons/zero.svg" style="max-width: 1em;max-height:1em;margin-left: .2em;">

    </footer>

  </div>   <script>
    // Passer les données satellites initiales et les IDs au JavaScript
    const satellitesToTrack = <?php echo json_encode($satellitesToTrack); ?>; // {Nom => ID}
    const satelliteIds = Object.values(satellitesToTrack); // Tableau des IDs [25544, 54216, 20580]
    const initialSatelliteData = <?php echo json_encode($initial_satellite_data); ?>; // {ID => {lat, lon, ...}}
    const defaultMappedSatelliteId = <?php echo json_encode($default_mapped_satellite_id); ?>; // ID du satellite sélectionné par défaut
  </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
   integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
   crossorigin=""></script>
    <script src="script.js"></script>

</body>
</html>
