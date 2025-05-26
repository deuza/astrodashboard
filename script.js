// Fichier: script.js
// Gère les onglets et le tracking satellite N2YO avec carte Leaflet.
// La carte suit le satellite sélectionné quand le tracking est actif.
// Suppression du message de chargement initial et affichage direct de l'onglet par défaut.


// Fonction pour ouvrir un onglet
// La logique reste la même
function openTab(evt, tabName) {
    console.log(`openTab called with tabName: ${tabName}`); // Log

    let i, tabcontent, tablinks;

    // Masquer tous les onglets
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    // Désactiver le bouton actif
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Afficher l'onglet courant
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.style.display = "block";
    }

    // Marquer le bouton comme actif
    if (evt) { // Si l'événement existe (clic manuel)
        evt.currentTarget.className += " active";
    } else { // Si appelé programmatiquement au chargement (on active le bouton correspondant)
        const defaultButton = document.querySelector(`.tablinks[onclick*="'${tabName}'"]`);
        if(defaultButton) {
            defaultButton.className += " active";
        }
    }

    // Si l'onglet de suivi satellite est ouvert, mettre à jour la taille de la carte Leaflet
    // et centrer/positionner le marqueur si la carte est déjà initialisée
    if (tabName === 'SatelliteTracking') {
        console.log("Satellite Tracking tab opened. Checking map."); // Log
        // Leaflet nécessite d'invalider sa taille si le conteneur était caché
        // Le conteneur n'est plus caché par le div #content qui est toujours visible,
        // mais les tabcontents eux sont cachés. Donc invalidateSize est toujours utile ici.
        if (mymap) {
            mymap.invalidateSize();
            console.log("Map invalidated size."); // Log
            // Mettre à jour la carte en fonction de la sélection actuelle
            updateMapBasedOnSelection(); // Centrer/positionner lors de l'ouverture de l'onglet (updateMapBasedOnSelection panifie par défaut)
        } else {
             console.warn("Satellite Tracking tab opened, but map not initialized."); // Log
        }
    }
}


// --- Variables et fonctions pour la carte Leaflet et le Tracking N2YO ---
let mymap = null; // Instance de la carte Leaflet
let satelliteMarker = null; // Instance du marqueur Leaflet

let issTracking = false; // État du tracking (false = paused, true = active)
let trackingInterval = null; // Pour stocker l'ID de l'intervalle

// satelliteIds, satellitesToTrack, initialSatelliteData, defaultMappedSatelliteId sont définis par PHP
let latestSatellitePositions = {}; // Stocke les dernières positions {id: {lat: ..., lon: ..., name: ..., error?: boolean}}
let currentMappedSatelliteId = null; // ID du satellite actuellement affiché sur la carte


// Fonction pour mettre à jour la position des satellites en appelant l'endpoint PHP
async function updateSatelliteLocations() {
    console.log("Fetching satellite locations..."); // >>> Log crucial <<<

    const phpEndpoint = 'get_satellite_position.php'; // Ton endpoint PHP

    const fetchPromises = satelliteIds.map(id => {
        const url = `${phpEndpoint}?satellite_id=${id}`;
        console.log(`Workspaceing URL: ${url}`); // Log fetch URL
        return fetch(url)
            .then(response => {
                console.log(`Received response for ID ${id}. Status: ${response.status}`); // Log response status
                if (!response.ok) {
                    // Log the full response text for non-200 status to help debug API issues
                    return response.text().then(text => {
                        console.error(`Workspace failed for ID ${id}. Status: ${response.status}. Response text (preview): ${text.substring(0, 500)}...`);
                         let errorJson;
                         try { errorJson = JSON.parse(text); } catch (e) { /* not JSON */ }
                        const errorMsg = errorJson && errorJson.error ? errorJson.error : response.statusText;
                         throw new Error(`HTTP error! Status: ${response.status}, Message: ${errorMsg}`);
                    });
                }
                return response.json();
            })
            .catch(error => {
                console.error(`Error fetching position for satellite ID ${id}:`, error);
                return { id: id, error: true, message: error.message || 'Fetch failed' };
            });
    });

    try {
        const results = await Promise.all(fetchPromises);
        console.log("All fetch promises settled."); // Log after all fetches

        results.forEach(data => {
            const id = parseInt(data.id, 10); // Ensure ID is a number for strict comparison later
            console.log(`Processing data for satellite ID ${id}.`); // Log processing start

            const latElement = document.getElementById(`lat-${id}`);
            const lonElement = document.getElementById(`lon-${id}`);
            const altElement = document.getElementById(`alt-${id}`);

            if (data.error) {
                console.error(`Processing error for satellite ID ${id}: ${data.message}`); // Log processing error
                if(latElement) latElement.innerText = "Erreur";
                if(lonElement) lonElement.innerText = "Erreur";
                if(altElement) altElement.innerText = "Erreur";

                 latestSatellitePositions[id] = { error: true, name: initialSatelliteData[id]?.name || 'Unknown' }; // Mark as error

            } else {
                const lat = parseFloat(data.latitude); // Ensure lat/lon are numbers
                const lon = parseFloat(data.longitude); // Ensure lat/lon are numbers
                const alt = data.altitude; // Alt can remain number or string
                const name = data.name || initialSatelliteData[id]?.name || 'Satellite'; // Use API name or initial PHP name

                if (latElement) latElement.innerText = lat;
                if (lonElement) lonElement.innerText = lon;
                if (altElement) altElement.innerText = `${alt} km`;


                 // Validate coordinates before storing/using
                if (isFinite(lat) && isFinite(lon)) {
                     // Store the latest valid position
                     latestSatellitePositions[id] = {
                         lat: lat,
                         lon: lon,
                         alt: alt,
                         name: name,
                         error: false // Explicitly mark as no error
                     };
                     console.log(`Stored valid position for ID ${id}: Lat ${lat}, Lon ${lon}`); // Log stored data

                    // Mettre à jour la carte si ce satellite est celui actuellement sélectionné POUR LA CARTE
                    console.log(`Checking map update for ID ${id}. Current mapped ID: ${currentMappedSatelliteId}. Tracking active: ${issTracking}`); // >>> Log crucial <<<
                    if (id === currentMappedSatelliteId) {
                        console.log(`Match found for ID ${id}. Updating map.`); // Log match

                        if (issTracking) {
                            console.log("Tracking is active, calling updateMapMarkerAndPan"); // Log call type
                            updateMapMarkerAndPan(lat, lon, name); // <-- Move marker AND pan
                        } else {
                            console.log("Tracking is paused, calling updateMapMarker"); // Log call type
                            updateMapMarker(lat, lon, name); // <-- Just move marker
                        }
                         console.log("Map update function call finished."); // Log after call

                    } else {
                        // console.log(`No map update needed for ID ${id}.`); // Log no match (can be noisy)
                    }
                } else {
                     console.error(`Received invalid (non-finite) coordinates for ID ${id}: Lat ${lat}, Lon ${lon}. Data:`, data); // Log invalid coords
                     if(latElement) latElement.innerText = "Coordonnées invalides";
                     if(lonElement) lonElement.innerText = "Coordonnées invalides";
                     // Do NOT update latestSatellitePositions with invalid data - keep the last good one or mark error
                      latestSatellitePositions[id] = { error: true, name: name }; // Mark as error if data is bad
                }
            }
            console.log(`Finished processing data for satellite ID ${id}.`); // Log processing end
        });

        // After all updates, if tracking is paused, update the map based on the *last* positions received
        // This ensures the marker jumps to the latest position on the static map when tracking is paused
        // This will be triggered by the interval when issTracking is false
         if (!issTracking && currentMappedSatelliteId !== null && latestSatellitePositions[currentMappedSatelliteId] && !latestSatellitePositions[currentMappedSatelliteId].error) {
              const data = latestSatellitePositions[currentMappedSatelliteId];
              console.log(`Tracking is paused. Updating marker only for selected ID ${currentMappedSatelliteId} at Lat: ${data.lat}, Lon: ${data.lon}`); // Log paused update
              updateMapMarker(data.lat, data.lon, data.name);
         }


    } catch (error) {
        console.error("Error during satellite updates batch:", error);
    }
}


// Fonction pour mettre à jour la position et le popup du marqueur Leaflet (sans centrer)
function updateMapMarker(latitude, longitude, satelliteName) {
    console.log(`updateMapMarker called with lat: ${latitude}, lon: ${longitude}, name: ${satelliteName}`); // Log call
    if (mymap && satelliteMarker) {
        const latLng = [latitude, longitude];
        satelliteMarker.setLatLng(latLng); // Déplacer le marqueur
        // .update() n'est généralement pas nécessaire après setLatLng dans Leaflet 1.x

        if (satelliteName) {
            satelliteMarker.setPopupContent(`${satelliteName}<br>Lat: ${latitude}<br>Lon: ${longitude}`); // Mettre à jour le contenu du popup
        } else {
             satelliteMarker.setPopupContent(`Satellite Position<br>Lat: ${latitude}<br>Lon: ${longitude}`);
        }
         // Keep popup open if it was open for this marker
         // if (satelliteMarker.getPopup().isOpen()) { // Re-opening automatically can be annoying
         //     satelliteMarker.openPopup();
         // }
    } else {
         console.warn("updateMapMarker: mymap or satelliteMarker not initialized."); // Log if map/marker missing
    }
}

// Fonction pour mettre à jour le marqueur ET centrer la carte (pour suivi auto ou changement de sélection)
function updateMapMarkerAndPan(latitude, longitude, satelliteName) {
     console.log(`updateMapMarkerAndPan called with lat: ${latitude}, lon: ${longitude}, name: ${satelliteName}`); // Log call
     updateMapMarker(latitude, longitude, satelliteName); // Déplace le marqueur et met à jour popup
     if (mymap) {
          mymap.panTo([latitude, longitude]); // Centre la carte sur la nouvelle position
          console.log("Map panTo called."); // Log after panTo
     } else {
         console.warn("updateMapMarkerAndPan: mymap not initialized."); // Log if map missing
     }
}


// Fonction pour mettre à jour la carte (marqueur + vue) en fonction de la sélection radio actuelle
// Toujours panifie quand appelée
function updateMapBasedOnSelection() {
     console.log(`updateMapBasedOnSelection called. Selected ID: ${currentMappedSatelliteId}`); // Log call

     if (!mymap || !satelliteMarker) {
         console.warn("updateMapBasedOnSelection: Map or marker not initialized yet.");
         return;
     }

     const selectedId = currentMappedSatelliteId;
     const satelliteData = latestSatellitePositions[selectedId];

     // Vérifier si les données existent ET ne sont pas marquées comme erreur ET sont finies
     if (satelliteData && !satelliteData.error && isFinite(satelliteData.lat) && isFinite(satelliteData.lon)) {
         const lat = satelliteData.lat;
         const lon = satelliteData.lon;
         const name = satelliteData.name || 'Selected Satellite';

         console.log(`updateMapBasedOnSelection: Found valid data for ID ${selectedId}. Lat: ${lat}, Lon: ${lon}.`); // Log data

         // Mettre à jour le marqueur ET centrer la carte
         updateMapMarkerAndPan(lat, lon, name); // <-- Use the pan function here

     } else {
         console.warn(`updateMapBasedOnSelection: No valid latest position data available for selected satellite ID ${selectedId} (or marked as error). Setting map to 0,0.`); // Log reason
         // Si pas de données valides (ou marquées erreur), placer le marqueur à 0,0 et centrer la carte
         updateMapMarkerAndPan(0, 0, 'Position inconnue'); // Use the pan function here too
         if (mymap) {
             mymap.setView([0, 0], 2); // Reset zoom for world view
         }
     }
}


// Fonction pour activer/désactiver le tracking N2YO
function toggleTracking() {
    issTracking = !issTracking; // Inverse l'état
    const toggleButton = document.getElementById("toggleTracking"); // ID du bouton de tracking
    if (toggleButton) {
        toggleButton.innerText = issTracking ? "⏸️ Pause Tracking" : "▶️Lancer Tracking";
         if (issTracking) {
             toggleButton.classList.add('active-tracking');
         } else {
             toggleButton.classList.remove('active-tracking');
         }
    }

    if (issTracking) {
        console.log("Satellite Tracking Started"); // >>> Log crucial <<<
        // Déclenche une mise à jour immédiate quand on démarre le tracking
        // updateSatelliteLocations va maintenant appeler updateMapMarkerAndPan si le satellite est le bon
        updateSatelliteLocations();
        // DémarrER l'intervalle UNIQUEMENT quand on démarre le tracking
        if (!trackingInterval) { // S'assurer qu'on ne démarre pas plusieurs intervalles
             trackingInterval = setInterval(updateSatelliteLocations, 10000); // Mettre à jour toutes les 10 secondes
             console.log("Interval started."); // Log interval start
        } else {
             console.warn("Interval was already running when Start was clicked?"); // Log warning
        }
    } else {
        console.log("Satellite Tracking Paused"); // >>> Log crucial <<<
        // Arrête l'intervalle si le tracking est mis en pause
        if (trackingInterval) {
            clearInterval(trackingInterval);
            trackingInterval = null;
            console.log("Interval cleared."); // Log interval clear
        } else {
             console.warn("Interval was already cleared when Pause was clicked?"); // Log warning
        }
        // Optionnel: Mettre à jour la carte une dernière fois avec la position au moment de la pause
        // Cela sera géré par la fin de la dernière exécution de updateSatelliteLocations (si elle était en cours)
        // ou la prochaine si elle a été appelée juste avant le clear.
    }
}


// Exécuté lorsque le DOM est complètement chargé
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOMContentLoaded fired."); // Log
    // Masquer le message de chargement et afficher le contenu après un délai
    // SUPPRIMER LE SETTIMEOUT POUR AFFICHAGE IMMEDIAT
    // setTimeout(function() {
        console.log("Initial setup starting (no timeout delay)."); // Log
        const loadingElement = document.getElementById("loading"); // Will be null/undefined if removed from HTML
        const contentElement = document.getElementById("content");
        // #content is visible by default now via CSS
        if (loadingElement) loadingElement.style.display = "none"; // Ensure it's hidden if still in HTML


        // --- Initialiser Leaflet Map ---
        const mapContainer = document.getElementById('mapid');
        if (mapContainer) {
             console.log("Map container found. Initializing Leaflet map."); // Log
             mymap = L.map('mapid', {
                center: [0, 0], // Centre initial temporaire
                zoom: 2, // Zoom initial
                zoomControl: true
            });

             L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(mymap);

            satelliteMarker = L.marker([0, 0]).addTo(mymap);
            satelliteMarker.bindPopup('Position du satellite');
            console.log("Leaflet map and marker initialized."); // Log

        } else {
            console.error("Map container #mapid not found!"); // Log error
            // Cannot proceed with map-related logic if map container is missing
            // return; // Do NOT exit the function, other parts might still work
        }


        // --- Initialiser l'état du tracking N2YO et le bouton ---
        issTracking = false; // Tracking starts paused
        const toggleButton = document.getElementById("toggleTracking");

        if (toggleButton) {
            console.log("Tracking toggle button found. Adding event listener."); // Log
            toggleButton.innerText = "▶️ Lancer Tracking";
            toggleButton.addEventListener("click", toggleTracking);
            toggleButton.classList.remove('active-tracking');
        } else {
             console.warn("Tracking toggle button not found. N2YO initial API data might have failed to load?"); // Log warning
        }

         // --- Initialiser la sélection de satellite pour la carte ---
         const satelliteSelectors = document.querySelectorAll('input[name="selected_satellite"]');
         // We can proceed with selectors even if map is missing, but map updates won't work
         if (satelliteSelectors.length > 0) {

             // currentMappedSatelliteId is already set by PHP's defaultMappedSatelliteId variable
             currentMappedSatelliteId = defaultMappedSatelliteId; // Ensure it's correctly set in JS scope
             console.log("Selected satellite selectors found. currentMappedSatelliteId set to:", currentMappedSatelliteId); // Log

             // Populate latestSatellitePositions with initial data from PHP
             console.log("Populating latestSatellitePositions with initial data:", initialSatelliteData); // Log initial data
             Object.keys(initialSatelliteData).forEach(idStr => {
                  const id = parseInt(idStr, 10); // Ensure ID key is number
                  const data = initialSatelliteData[idStr]; // Access data using string key from PHP array keys
                   if (data && isFinite(data.latitude) && isFinite(data.longitude)) {
                       latestSatellitePositions[id] = { // Store with number key
                            lat: parseFloat(data.latitude), // Ensure number
                            lon: parseFloat(data.longitude), // Ensure number
                            alt: data.altitude,
                            name: data.name
                        };
                        console.log(`Stored initial valid data for ID ${id}:`, latestSatellitePositions[id]); // Log
                   } else {
                       latestSatellitePositions[id] = { error: true, name: data ? data.name : 'Unknown' }; // Store with number key
                       console.warn(`Initial data for ID ${id} is invalid:`, data); // Log warning
                   }
             });

            // Position the map and marker initially based on the default selection, IF MAP EXISTS
            if(mymap) {
                 console.log("Calling updateMapBasedOnSelection for initial map position."); // Log
                 updateMapBasedOnSelection(); // Update and pan to default satellite initial position
            } else {
                 console.warn("Map not initialized, skipping initial map positioning."); // Log
            }


             // Add event listeners to radio buttons
             console.log("Adding change listeners to satellite selectors."); // Log
             satelliteSelectors.forEach(radio => {
                 radio.addEventListener('change', function() {
                     const selectedId = parseInt(this.value, 10); // Ensure ID is number
                     currentMappedSatelliteId = selectedId;
                     console.log("Mapped satellite changed to ID:", currentMappedSatelliteId);
                      // Update and pan to the new selected satellite's latest known position, IF MAP EXISTS
                     if(mymap) {
                          updateMapBasedOnSelection();
                     } else {
                          console.warn("Map not initialized, cannot update map on selection change."); // Log
                     }
                 });
             });

         } else {
              console.warn("Satellite map selectors not found. Map selection disabled."); // Log warning
              currentMappedSatelliteId = 25544; // Default selection even if no radios
              // Try to display default ISS if initial data is available, IF MAP EXISTS
              if (mymap) {
                   if (initialSatelliteData && initialSatelliteData[currentMappedSatelliteId] && isFinite(initialSatelliteData[currentMappedSatelliteId].latitude) && isFinite(initialSatelliteData[currentMappedSatelliteId].longitude)) {
                        const initialData = initialSatelliteData[currentMappedSatelliteId];
                         console.log(`Attempting to display default ISS (ID ${currentMappedSatelliteId}) initial data on map:`, initialData); // Log
                        updateMapMarkerAndPan(parseFloat(initialData.latitude), parseFloat(initialData.longitude), initialData.name); // Ensure numbers
                   } else {
                         console.warn(`Initial data for default ISS (ID ${currentMappedSatelliteId}) is invalid. Displaying 0,0 map.`); // Log warning
                         updateMapMarkerAndPan(0, 0, 'ISS Position');
                         if (mymap) mymap.setView([0, 0], 2);
                   }
              } else {
                   console.warn("Map not initialized, cannot display default ISS position."); // Log
              }
         }


        // --- The interval is now only started by the toggleTracking button ---
        // Remove the setInterval call from here. (Make sure it's not here!)
        // The logic for starting the interval is solely within toggleTracking().


    // }, 1000); // >>> REMOVE THE CLOSING PARENTHESIS AND CURLY BRACE OF THE SETTIMEOUT <<<
    // The code below should be outside the setTimeout
    // The code above should be outside the setTimeout

    // --- Afficher l'onglet par défaut (Astronautes) ---
    // Cet appel doit se faire APRÈS que le DOM est prêt et les initialisations JS faites
     console.log("Opening default tab: Astronautes"); // Log
     openTab(null, 'Astronautes');


}); // End DOMContentLoaded


// Optionnel: Nettoyer l'intervalle si l'utilisateur quitte la page
window.addEventListener('beforeunload', function() {
    console.log("Page is unloading. Clearing interval if exists."); // Log
    if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
        console.log("Interval cleared during unload."); // Log
    }
});
