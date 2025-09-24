=== Calendar Pet Sitting ===
Contributors: petsitting
Tags: calendar, booking, pet-sitting, appointments, reservations
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Un plugin WordPress complet pour la gestion de réservations de services de pet-sitting avec calendrier interactif intégrable dans Divi.

== Description ==

Calendar Pet Sitting est un plugin WordPress complet qui fournit :

* Un calendrier interactif intégrable dans Divi
* Un formulaire de réservation en popup après clic sur un créneau
* La gestion de prestations (prix, durée minimale, type: journalière / horaire / minutes)
* La gestion d'indisponibilités (ponctuelles et récurrentes)
* Le blocage automatique des créneaux réservés pour éviter tout chevauchement
* La possibilité pour le client de réserver plusieurs occurrences en une seule soumission
* Un nettoyage automatique des réservations (conservation 2 ans puis suppression)

= Fonctionnalités Front-office =

* Affichage calendrier type Google Agenda avec vues mois, semaine, jour
* Navigation intuitive (précédent/suivant/aujourd'hui)
* Indication claire des créneaux disponibles et indisponibles
* Formulaire de réservation avec validation
* Multi-réservations dans le même formulaire
* Confirmation à l'écran après succès

= Fonctionnalités Back-office =

* CRUD de prestations
* Gestion des indisponibilités ponctuelles et récurrentes
* Liste des réservations avec filtres
* Paramètres configurables (fuseau horaire, conservation, etc.)

== Installation ==

1. Téléchargez le plugin et décompressez-le dans le dossier `/wp-content/plugins/`
2. Activez le plugin via le menu 'Extensions' dans WordPress
3. Configurez le plugin via le menu 'Pet Sitting' dans l'administration
4. Utilisez le shortcode `[petsitting_calendar]` pour afficher le calendrier

== Frequently Asked Questions ==

= Comment intégrer le calendrier dans une page Divi ? =

Utilisez le shortcode `[petsitting_calendar]` dans un module Code ou Texte de Divi.

= Quels sont les types de services supportés ? =

Le plugin supporte trois types de services :
* Journalier : sélection de jours entiers
* Horaire : sélection de plages d'heures
* Minutes : sélection de plages au pas fin

= Comment gérer les indisponibilités ? =

Vous pouvez définir des indisponibilités ponctuelles (dates spécifiques) ou récurrentes (par exemple, tous les lundis de 14h à 18h).

== Screenshots ==

1. Calendrier interactif front-end
2. Formulaire de réservation
3. Interface d'administration
4. Gestion des services

== Changelog ==

= 1.0.0 =
* Version initiale du plugin
* Calendrier interactif avec FullCalendar
* Système de réservation complet
* Interface d'administration
* API REST pour les interactions AJAX

== Upgrade Notice ==

= 1.0.0 =
Version initiale du plugin Calendar Pet Sitting.