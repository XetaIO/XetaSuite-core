<?php

return [
    // Attributes
    'material' => 'Material',
    'incidents' => 'Incidents',
    'description' => 'Description',
    'type' => 'Type',
    'realization' => 'Realization',
    'status' => 'Status',
    'started_at' => 'Started at',
    'resolved_at' => 'Resolved at',
    'created_by' => 'Created by',
    'operators' => 'Operators',
    'companies' => 'Companies',
    'spare_parts' => 'Spare parts',

    // Type labels
    'type_corrective' => 'Corrective',
    'type_preventive' => 'Preventive',
    'type_inspection' => 'Inspection',
    'type_improvement' => 'Improvement',

    // Status labels
    'status_planned' => 'Planned',
    'status_in_progress' => 'In progress',
    'status_completed' => 'Completed',
    'status_canceled' => 'Canceled',

    // Realization labels
    'realization_internal' => 'Internal',
    'realization_external' => 'External',
    'realization_both' => 'Internal and external',

    // Validation
    'validation' => [
        'material_not_on_site' => 'The selected material does not belong to your current site.',
        'incident_not_on_site' => 'One or more selected incidents do not belong to your current site.',
        'operator_not_on_site' => 'One or more selected operators do not have access to your current site.',
        'company_not_found' => 'One or more selected companies do not exist.',
        'item_not_on_site' => 'One or more selected items do not belong to your current site.',
        'item_insufficient_stock' => 'Insufficient stock for item :name. Available stock: :stock, requested: :requested.',
        'operators_required_for_internal' => 'At least one operator is required for internal realization.',
        'companies_required_for_external' => 'At least one company is required for external realization.',
        'quantity_min' => 'Quantity must be at least 1.',
    ],

    // Messages
    'created' => 'Maintenance created successfully.',
    'updated' => 'Maintenance updated successfully.',
    'deleted' => 'Maintenance deleted successfully.',
];
