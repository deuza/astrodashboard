![GitHub release](https://img.shields.io/github/v/release/DeuZa/astrodashboard?label=release&style=plastic)
![GitHub commit activity](https://img.shields.io/github/commit-activity/t/deuza/astrodashboard)
[![GitHub last commit](https://img.shields.io/github/last-commit/DeuZa/astrodashboard?style=plastic)](https://github.com/DeuZa/WMT/commits/main)
![Status](https://img.shields.io/badge/stability-solid-green?style=plastic)
[![License: CC0](https://img.shields.io/badge/license-CC0_1.0-lightgrey.svg?style=plastic)](https://creativecommons.org/publicdomain/zero/1.0/)
![Hack The Planet](https://img.shields.io/badge/hack-the--planet-black?style=flat-square\&logo=gnu\&logoColor=white)
![Built With Love](https://img.shields.io/badge/built%20with-%E2%9D%A4%20by%20DeuZa-red?style=plastic)  

# AstroDashboard 🚀

AstroDashboard est un tableau de bord web permettant de suivre les astronautes actuellement en orbite et la position de certains satellites en temps réel. Il utilise les API d'Open Notify pour les données des astronautes et de N2YO.com pour le suivi des satellites.

## Captures d'écran 📸

| Onglet Astronautes                                  | Onglet Vaisseaux                                   | Onglet Suivi Satellites (Vue Globale)               |
| :--------------------------------------------------: | :-------------------------------------------------: | :-------------------------------------------------: |
| ![Onglet Astronautes](01.png)                        | ![Onglet Vaisseaux](02.png)                         | ![Onglet Suivi Satellites Vue Globale](03.png)        |
| **Suivi Satellite Actif (ISS)** | **Clé API N2YO (Exemple)** | **Exemple d'erreur API (Timeout Open Notify)** |
| ![Suivi Satellite Actif ISS](04.jpg)                 | ![Clé API N2YO Exemple](API%20Key%20N2YO.png)        | ![Exemple Erreur API Timeout Open Notify](API%20timeout.png) |

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
* **Système d'exploitation** : Testé sous Debian (sur architectures arm64 et amd64).

## Configuration ⚙️

### 1. Clé API N2YO

Le script nécessite une clé API gratuite de [N2YO.com](https://www.n2yo.com/) pour récupérer les données de position des satellites.

1.  Créez un compte sur [N2YO.com](https://www.n2yo.com/).
2.  Récupérez votre clé API depuis la section "API ACCESS" de votre compte (voir capture `API Key N2YO.png`).
3.  Créez le fichier suivant sur votre serveur :
    * Chemin du fichier : `/var/www/api_keys/space.key`
    * et collez-y votre clé API
    * Assurez-vous que ce fichier est lisible par votre serveur web (Apache).

### 2. Fichiers du projet

Déployez les fichiers suivants dans le répertoire de votre serveur web (par exemple `/var/www/html/AstroDashboard/` ou un VirtualHost configuré) :

#### Le chemin du fichier contenant la clé API

`/var/www/api_keys/`    
└── `space.key`                    # Votre clé API N2YO    

#### Le chemin du répertoire contenant les fichiers du dashboard

`/var/www/html/AstroDashboard/`    
├── `index.php`                    # Page principale, affiche les données et la carte    
├── `get_satellite_position.php`   # Endpoint PHP pour récupérer les données N2YO    
├── `script.js`                    # Logique JavaScript pour les onglets et le tracking    
├── `style.css`                    # Styles CSS pour la page    
├── `nasa-logo.svg`                # Logo NASA    
└── `telescope.ico`                # Favicon         


## Utilisation 🌐

Une fois les dépendances installées, la clé API configurée et les fichiers en place, accédez à `index.php` via votre navigateur.

Exemple : `http://localhost/AstroDashboard/` ou `http://VOTRE_ADRESSE_IP/AstroDashboard/`

## Satellites suivis 🛰️

Par défaut, le tableau de bord est configuré pour suivre les satellites suivants via leur ID NORAD :

* **ISS (Station Spatiale Internationale)** : 25544
* **CSS (Station Spatiale Chinoise - Tiangong)** : 54216
* **Hubble (Télescope Spatial Hubble)** : 20580

Vous pouvez modifier cette liste dans les fichiers `get_satellite_position.php` (pour la validation côté serveur) et `index.php` (pour l'affichage initial et les requêtes).

## Erreurs 🥷

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
