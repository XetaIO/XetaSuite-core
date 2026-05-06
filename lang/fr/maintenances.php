<?php

return [
    // Attributes
    'material' => 'Matériel',
    'incidents' => 'Incidents',
    'description' => 'Description',
    'type' => 'Type',
    'realization' => 'Réalisation',
    'status' => 'Statut',
    'started_at' => 'Débuté le',
    'resolved_at' => 'Résolu le',
    'created_by' => 'Créé par',
    'operators' => 'Opérateurs',
    'companies' => 'Entreprises',
    'spare_parts' => 'Pièces détachées',

    // Type labels
    'type_corrective' => 'Corrective',
    'type_preventive' => 'Préventive',
    'type_inspection' => 'Inspection',
    'type_improvement' => 'Amélioration',

    // Status labels
    'status_planned' => 'Planifiée',
    'status_in_progress' => 'En cours',
    'status_completed' => 'Terminée',
    'status_canceled' => 'Annulée',

    // Realization labels
    'realization_internal' => 'Interne',
    'realization_external' => 'Externe',
    'realization_both' => 'Interne et externe',

    // Validation
    'validation' => [
        'material_not_on_site' => 'Le matériel sélectionné n\'appartient pas à votre site actuel.',
        'incident_not_on_site' => 'Un ou plusieurs incidents sélectionnés n\'appartiennent pas à votre site actuel.',
        'operator_not_on_site' => 'Un ou plusieurs opérateurs sélectionnés n\'ont pas accès à votre site actuel.',
        'company_not_found' => 'Une ou plusieurs entreprises sélectionnées n\'existent pas.',
        'item_not_on_site' => 'Un ou plusieurs articles sélectionnés n\'appartiennent pas à votre site actuel.',
        'item_insufficient_stock' => 'Stock insuffisant pour l\'article :name. Stock disponible: :stock, demandé: :requested.',
        'operators_required_for_internal' => 'Au moins un opérateur est requis pour une réalisation interne.',
        'companies_required_for_external' => 'Au moins une entreprise est requise pour une réalisation externe.',
        'quantity_min' => 'La quantité doit être d\'au moins 1.',
    ],

    // Messages
    'created' => 'Maintenance créée avec succès.',
    'updated' => 'Maintenance mise à jour avec succès.',
    'deleted' => 'Maintenance supprimée avec succès.',
];
