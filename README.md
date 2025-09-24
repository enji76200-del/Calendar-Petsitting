# Calendar Pet Sitting - Plugin WordPress

Un plugin WordPress complet pour la gestion de réservations de services de pet-sitting avec calendrier interactif intégrable dans Divi.

## 🚀 Fonctionnalités

### Front-office (Client)
- **Calendrier interactif** type Google Agenda avec vues mois, semaine, jour
- **Navigation intuitive** (précédent/suivant/aujourd'hui)
- **Indication claire** des créneaux disponibles et indisponibles
- **Formulaire de réservation** en popup après clic sur un créneau
- **Multi-réservations** dans le même formulaire
- **Validation complète** des formulaires (champs requis, email/téléphone valides)
- **Confirmation** à l'écran après succès

### Back-office (Admin)
- **CRUD complet** pour les prestations (prix, durée minimale, type)
- **Gestion des indisponibilités** ponctuelles et récurrentes
- **Liste des réservations** avec filtres (date, prestation, client)
- **Paramètres configurables** (fuseau horaire, conservation, etc.)
- **Statistiques rapides** sur le tableau de bord
- **Interface d'administration** intuitive

### Types de services supportés
- **Journalier** : sélection de jours entiers (avec durée minimale en jours)
- **Horaire** : sélection de plages d'heures (pas configurable : 30, 60 minutes, etc.)
- **Minutes** : sélection de plages au pas fin (5, 10, 15 minutes, configurable)

### Sécurité et performance
- **Blocage automatique** des créneaux réservés pour éviter tout chevauchement
- **Transactions BDD** pour éviter les doubles réservations
- **Validation côté serveur** stricte
- **Nettoyage automatique** des réservations (conservation 2 ans par défaut)
- **Nonces WordPress** pour la sécurité
- **API REST** sécurisée

## 📦 Installation

1. Téléchargez le plugin et décompressez-le dans `/wp-content/plugins/calendar-petsitting/`
2. Activez le plugin via le menu 'Extensions' dans WordPress
3. Le plugin créera automatiquement les tables de base de données nécessaires
4. Configurez le plugin via le menu 'Pet Sitting' dans l'administration
5. Utilisez le shortcode `[petsitting_calendar]` pour afficher le calendrier

## 🔧 Utilisation

### Shortcode de base
```
[petsitting_calendar]
```

### Shortcode avec options
```
[petsitting_calendar view="week" height="700" service_id="1"]
```

#### Options disponibles :
- `view` : Vue par défaut (`month`, `week`, `day`)
- `height` : Hauteur du calendrier en pixels
- `service_id` : Filtrer par service spécifique
- `theme` : Thème d'affichage (`default`)

### Intégration Divi
Le shortcode peut être utilisé dans :
- Module **Code** de Divi
- Module **Texte** de Divi
- **Code Shortcode** de Divi

## 🗄️ Structure de la base de données

Le plugin crée 6 tables :

- `wp_ps_services` : Types de services
- `wp_ps_customers` : Informations clients
- `wp_ps_bookings` : Réservations principales
- `wp_ps_booking_items` : Éléments de réservation (créneaux)
- `wp_ps_unavailabilities` : Indisponibilités ponctuelles
- `wp_ps_recurring_unavailabilities` : Indisponibilités récurrentes

## 🔌 API REST

### Endpoints publics (pas d'authentification)
- `GET /wp-json/petsitting/v1/availability` : Récupérer les disponibilités
- `GET /wp-json/petsitting/v1/services` : Lister les services actifs
- `POST /wp-json/petsitting/v1/book` : Créer une réservation

### Endpoints admin (authentification requise)
- `GET/POST/PUT/DELETE /wp-json/petsitting/v1/admin/services` : Gestion des services
- `GET /wp-json/petsitting/v1/admin/bookings` : Liste des réservations
- `GET/POST /wp-json/petsitting/v1/admin/unavailabilities` : Gestion des indisponibilités

## 🎨 Personnalisation

### CSS
Le plugin charge automatiquement :
- `assets/css/public.css` : Styles front-end
- `assets/css/admin.css` : Styles back-end

Vous pouvez surcharger ces styles dans votre thème.

### JavaScript
- `assets/js/public.js` : Logique front-end (FullCalendar, formulaires)
- `assets/js/admin.js` : Logique back-end

## 🌍 Internationalisation

Le plugin est prêt pour la traduction avec :
- Domaine de traduction : `calendar-petsitting`
- Fichiers de langue dans `/languages/`
- Textes français par défaut

## ⚙️ Configuration

### Paramètres principaux
- **Fuseau horaire** : Europe/Paris par défaut
- **Durée de conservation** : 2 ans par défaut
- **Pas par défaut** : 30 minutes pour les services horaires

### Nettoyage automatique
- **WP-Cron quotidien** pour supprimer les anciennes réservations
- **Suppression des clients** orphelins
- **Logs d'activité** dans les logs d'erreur PHP

## 🔧 Développement

### Structure des fichiers
```
calendar-petsitting/
├── calendar-petsitting.php     # Fichier principal
├── includes/                   # Classes core
│   ├── class-database.php
│   ├── class-rest-api.php
│   ├── class-booking-manager.php
│   ├── class-availability-calculator.php
│   └── class-cron-cleanup.php
├── admin/                      # Interface admin
│   ├── class-admin.php
│   ├── class-services-admin.php
│   ├── class-bookings-admin.php
│   ├── class-unavailabilities-admin.php
│   └── class-settings-admin.php
├── public/                     # Interface publique
│   ├── class-public.php
│   └── class-shortcode.php
├── assets/                     # Ressources
│   ├── css/
│   └── js/
├── languages/                  # Traductions
└── uninstall.php              # Désinstallation
```

### Hooks disponibles
- `calendar_petsitting_cleanup` : Cron de nettoyage
- Actions WordPress standard pour admin et front-end

## 📋 Prérequis

- **WordPress** : 5.0 minimum
- **PHP** : 7.4 minimum
- **MySQL** : 5.6 minimum
- **Extensions PHP** : PDO, JSON

## 🐛 Dépannage

### Problèmes courants
1. **Calendrier ne s'affiche pas** : Vérifier que jQuery et FullCalendar sont chargés
2. **Erreur 404 sur l'API** : Vider le cache des permaliens
3. **Pas de données** : Vérifier que les tables sont créées et que des services existent

### Debug
Activer `WP_DEBUG` pour voir les erreurs détaillées.

## 📄 Licence

GPL v2 ou ultérieure - https://www.gnu.org/licenses/gpl-2.0.html

## 🤝 Contribution

Les contributions sont les bienvenues ! Merci de :
1. Fork le projet
2. Créer une branche pour votre fonctionnalité
3. Commiter vos changements
4. Pousser vers la branche
5. Ouvrir une Pull Request

## 📞 Support

Pour toute question ou problème, ouvrez une issue sur le repository GitHub.

---

**Développé avec ❤️ pour la communauté WordPress**