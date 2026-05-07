![GitHub release](https://img.shields.io/github/v/release/DeuZa/astrodashboard?label=release&style=plastic)
![GitHub Release Date](https://img.shields.io/github/release-date/deuza/astrodashboard)
![GitHub commit activity](https://img.shields.io/github/commit-activity/t/deuza/astrodashboard)
[![GitHub last commit](https://img.shields.io/github/last-commit/DeuZa/astrodashboard?style=plastic)](https://github.com/DeuZa/astrodashboard/commits/main)
![Status](https://img.shields.io/badge/stability-solid-green?style=plastic)
[![License: CC0](https://img.shields.io/badge/license-CC0_1.0-lightgrey.svg?style=plastic)](https://creativecommons.org/publicdomain/zero/1.0/)
![Built With Love](https://img.shields.io/badge/built%20with-%E2%9D%A4%20by%20DeuZa-red?style=plastic)  

# AstroDashboard 🚀

AstroDashboard est un tableau de bord web permettant de suivre les astronautes actuellement en orbite et la position de certains satellites en temps réel.   
Il utilise un fichier JSON local pour la liste des astronautes et l'API de N2YO.com pour le suivi des satellites.

## Captures d'écran 📸

| Onglet Astronautes                                  | Onglet Vaisseaux                                   | Onglet Suivi Satellites (Vue Globale)               |
| :-------------------------------------------------: | :-------------------------------------------------: | :-------------------------------------------------: |
| ![Onglet Astronautes](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/01.png)                        | ![Onglet Vaisseaux](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/02.png)                         | ![Onglet Suivi Satellites Vue Globale](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/03.png)        |
| Suivi Satellite Actif (ISS) | More stuff puis API ACCESS | Erreur timeout d'une API faites un refresh |
| ![Suivi Satellite Actif ISS](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/04.png)  |  ![More stuff puis API ACCESS](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/API.Key.N2YO.png) | ![Erreur timeout d'une API, refresh](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/05.png) |

## Fonctionnalités ✨

* **Liste des astronautes en orbite** : Affiche le nom des astronautes et le vaisseau spatial auquel ils sont assignés.
* **Liste des vaisseaux habités** : Montre les vaisseaux spatiaux actuellement habités.
* **Suivi de satellites** : Affiche la position (latitude, longitude, altitude) de satellites sélectionnés (ISS, CSS, Hubble) et permet de les visualiser sur une carte Leaflet.
* **Mise à jour en temps réel** : Les positions des satellites peuvent être mises à jour automatiquement.
* **Liens externes** : Fournit des liens de recherche rapides vers Wikipédia et Google pour chaque astronaute et vaisseau.

## Dépendances et Prérequis 🛠️

Le script a été testé avec les configurations suivantes :

* **Serveur Web** : Apache2
* **PHP** : Version 8.2.28 (ou supérieure recommandée)
* **Module PHP cURL** : `php-curl` et `php8.2-curl` (ou la version correspondante à votre PHP) sont nécessaires.    
* **Système d'exploitation** : Testé sous Debian (sur architectures `arm64` et `amd64`).

## Configuration ⚙️

### 1. Clé API N2YO

Le script nécessite une clé API gratuite de [N2YO.com](https://www.n2yo.com/) pour récupérer les données de position des satellites.

1.  Créez un compte sur [N2YO.com](https://www.n2yo.com/).
2.  Récupérez votre clé API depuis la section "API ACCESS" de votre compte (voir capture `API Key N2YO.png`).
3.  Créez le fichier suivant sur votre serveur :
    * Chemin du fichier : `/var/www/api_keys/space.key` en dehors de votre DocumentRoot
    * et collez-y votre clé API
    * Assurez-vous que ce fichier est lisible par votre serveur web (Apache).

### 2. Fichiers du projet

Déployez les fichiers suivants dans le répertoire de votre serveur web (par exemple `/var/www/html/AstroDashboard/` ou un VirtualHost configuré) :

#### Le chemin du fichier contenant la clé API

```
/var/www/api_keys/
└── space.key                    # Votre clé API N2YO
```

```
/var/www/html/AstroDashboard/
├── index.php                    # Page principale, affiche les données et la carte
├── get_satellite_position.php   # Endpoint PHP pour récupérer les données N2YO
├── astros.json                  # Liste des astronautes en orbite (maintenue manuellement)
├── script.js                    # Logique JavaScript pour les onglets et le tracking
├── style.css                    # Styles CSS pour la page
├── nasa-logo.svg                # Logo NASA
└── telescope.ico                # Favicon
```

#### Le chemin du répertoire contenant les fichiers du dashboard

```
/var/www/html/AstroDashboard/`    
├── `index.php`                    # Page principale, affiche les données et la carte    
├── `get_satellite_position.php`   # Endpoint PHP pour récupérer les données N2YO    
├── `astros.json`                  # Liste des astronautes en orbite (maintenue manuellement)    
├── `script.js`                    # Logique JavaScript pour les onglets et le tracking    
├── `style.css`                    # Styles CSS pour la page    
├── `nasa-logo.svg`                # Logo NASA    
└── `telescope.ico`                # Favicon         
```


## Utilisation 🌐

Une fois les dépendances installées, la clé API configurée et les fichiers en place, accédez à `index.php` via votre navigateur.

Exemple : `http://localhost/astrodashboard/` ou `http://VOTRE_ADRESSE_IP/astrodashboard/`

## Satellites suivis 🛰️

Par défaut, le tableau de bord est configuré pour suivre les satellites suivants via leur ID NORAD :

* **ISS (Station Spatiale Internationale)** : 25544
* **CSS (Station Spatiale Chinoise - Tiangong)** : 54216
* **Hubble (Télescope Spatial Hubble)** : 20580

Vous pouvez modifier cette liste dans les fichiers `get_satellite_position.php` (pour la validation côté serveur) et `index.php` (pour l'affichage initial et les requêtes).

## Maintenance de la liste des astronautes 🧑‍🚀

Le fichier `astros.json` contient la liste des humains actuellement en orbite. Il est maintenu manuellement à chaque rotation d'équipage, soit environ 8 à 12 mises à jour par an (rotations Crew Dragon SpaceX, Soyouz et Shenzhou).

### Format du fichier

```json
{
  "message": "success",
  "number": 10,
  "people": [
    {"craft": "ISS",      "name": "Nom Prenom"},
    {"craft": "Tiangong", "name": "Nom Prenom"}
  ],
  "updated": "2026-05-07",
  "expeditions": {
    "ISS": "Note libre sur l'expedition courante",
    "Tiangong": "Note libre sur la mission Shenzhou courante"
  }
}
```

### Procédure de mise à jour

1.  Vérifier la situation sur les sources de référence ci-dessous (croisement recommandé).
2.  Éditer `astros.json` :
    * Mettre à jour le tableau `people`
    * Mettre à jour le champ `number` (compteur total)
    * Mettre à jour le champ `updated` au format ISO `YYYY-MM-DD`
    * Mettre à jour les notes `expeditions` si changement d'expédition
3.  Valider le JSON :
    ```sh
    jq . astros.json > /dev/null && echo OK
    ```
4.  Recharger la page du dashboard, c'est tout.

### Sources de référence

#### Officielles, primaires

* **NASA Expeditions** : [https://www.nasa.gov/international-space-station/expedition-missions/](https://www.nasa.gov/international-space-station/expedition-missions/) - source officielle, page dédiée à chaque expédition en cours avec équipage nominatif et journal des dock/undock.
* **CMSA (China Manned Space Agency)** : [http://en.cmse.gov.cn/](http://en.cmse.gov.cn/) - source officielle pour Tiangong, version anglaise disponible.

#### Secondaires, très réactives

* **ARISS (Amateur Radio on the ISS)** : [https://www.ariss.org/current-iss-crew.html](https://www.ariss.org/current-iss-crew.html) - liste directe et synthétique de l'équipage ISS courant.
* **Wikipedia** : [List of current spaceflight crew members](https://en.wikipedia.org/wiki/List_of_current_spaceflight_crew_members) - vue consolidée ISS + Tiangong, mise à jour dans les heures qui suivent un dock/undock.

#### Spécialisées

* **Spacefacts.de** : [http://www.spacefacts.de/iss/english/iss-now.htm](http://www.spacefacts.de/iss/english/iss-now.htm) - très complet, maintenu par Joachim Becker, fiable de longue date.
* **china-in-space.com** : [https://www.china-in-space.com/](https://www.china-in-space.com/) - excellente source pour Tiangong, plus réactive et détaillée que CMSA.

## Erreurs 🥷

![Erreur timeout d'une API, refresh](https://github.com/deuza/astrodashboard/releases/download/v0.0.1/05.png)

Parfois vous pouvez avoir une erreur liée à une réponse trop lente des sites distribuant les données.   
Il vous suffit de raffraichir la page pour régler le problème, si ce comportement est systèmatique pas la peine de tabasser le bouton refresh :)

## Auteur 🧑‍💻

* **DeuZa** aka **0x2A**

## Licence 📜

Ce projet est sous licence `CC0 1.0 Universal`.
[![CC0](https://mirrors.creativecommons.org/presskit/buttons/88x31/svg/cc-zero.svg)](https://creativecommons.org/publicdomain/zero/1.0/)

<p align="center">
  <sub><sup>Maintenu avec ❤️  par <a href="https://github.com/deuza">DeuZa</a></sup></sub>
</p>
