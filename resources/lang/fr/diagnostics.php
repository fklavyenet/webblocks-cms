<?php

return [
  'title' => 'Rapport de diagnostic',
  'help' => 'Préparez un rapport limité sur l’installation pour le consulter ou le télécharger. Aucune donnée n’est envoyée à un prestataire d’assistance. Les rapports expirent après 30 minutes.',
  'categories' => 'Inclure dans le rapport',
  'category' => [
    'system_summary' => 'Versions du système et de l’environnement d’exécution',
    'update_run' => 'Exécution de mise à jour sélectionnée ou la plus récente',
    'plugin_health' => 'Versions et état d’activation des extensions installées',
    'saved_health' => 'Décomptes enregistrés des contrôles d’état',
    'recent_application_errors' => 'Horodatages et empreintes des erreurs récentes',
  ],
  'errors_help' => 'Le diagnostic des erreurs contient uniquement les horodatages, les niveaux de gravité et les empreintes. Les messages, les données des requêtes, les identifiants d’accès, les chemins, le contenu et les journaux complets sont exclus. Les autres canaux de journalisation ne sont pas collectés.',
  'consent' => 'Préparer les informations de diagnostic sélectionnées pour que je puisse les examiner.',
  'prepare' => 'Préparer le rapport de diagnostic',
  'review' => 'Examinez ci-dessous le rapport tel qu’il sera téléchargé. Pour retirer des catégories, modifiez la sélection et préparez un nouveau rapport. Le téléchargement ne collecte aucune nouvelle donnée.',
  'download' => 'Télécharger le rapport examiné',
  'expired' => 'Ce rapport a expiré ou appartient à un autre utilisateur. Préparez un nouveau rapport.',
];
