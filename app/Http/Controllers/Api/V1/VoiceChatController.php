<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Exceptions\Ai\LlmRateLimitException;
use XetaSuite\Http\Requests\V1\Voice\ChatRequest;
use XetaSuite\Services\VoiceToolsService;

class VoiceChatController extends Controller
{
    public function __construct(
        private readonly LlmProvider $llm,
        private readonly VoiceToolsService $toolsService,
    ) {
    }

    /**
     * Process a voice assistant chat message with agentic tool-calling loop.
     * Returns a natural-language reply in French.
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $user = $request->user();

        $today = Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY');
        $siteName = $user->currentSite?->name ?? 'votre site';

        $systemPrompt = "Tu es l'assistant vocal de XetaSuite, un ERP multi-sites.
Tu aides les utilisateurs à interagir avec le système : consulter les données, créer des maintenances, signaler des incidents, gérer les articles, etc.
Tu parles à {$user->full_name} sur le site « {$siteName} ».
Réponds toujours en français, de manière concise et naturelle (tu seras lu à voix haute).
Pour les dates, utilise le format ISO 8601 (ex: 2026-04-27T08:00:00).
La date d'aujourd'hui est {$today}.";

        $history = $request->input('history', []);
        $userMessage = $request->input('message');

        // Build initial message list
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history),
            ['role' => 'user', 'content' => $userMessage],
        ];

        // Agentic loop: max 5 iterations to handle sequential tool calls
        try {
            for ($i = 0; $i < 5; $i++) {
                $data = $this->llm->chat($messages, $this->getTools());
                $choice = $data['choices'][0] ?? null;

                if (! $choice) {
                    break;
                }

                $finishReason = $choice['finish_reason'] ?? 'stop';
                $message = $choice['message'] ?? [];

                // Final text response
                if ($finishReason === 'stop' || (isset($message['content']) && empty($message['tool_calls']))) {
                    return response()->json(['reply' => $message['content'] ?? "Je n'ai pas de réponse."]);
                }

                // Tool calls
                if ($finishReason === 'tool_calls' && ! empty($message['tool_calls'])) {
                    $messages[] = $message;

                    foreach ($message['tool_calls'] as $toolCall) {
                        $name = $toolCall['function']['name'];
                        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                        $args = $this->coerceBooleans($args ?? []);

                        $result = $this->toolsService->execute($user, $name, $args);

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => $name,
                            'content' => $result,
                        ];
                    }

                    continue;
                }

                return response()->json(['reply' => $message['content'] ?? "Je n'ai pas pu traiter votre demande."]);
            }

            return response()->json(['reply' => "Désolé, je n'ai pas pu terminer cette action."]);
        } catch (LlmRateLimitException) {
            return response()->json(
                ['message' => 'Trop de demandes. Veuillez patienter quelques instants avant de réessayer.'],
                429
            );
        }
    }

    /**
     * Coerce boolean strings and numeric strings to their native types
     * to handle LLM-generated JSON arguments.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function coerceBooleans(array $args): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value === 'true') {
                return true;
            }
            if ($value === 'false') {
                return false;
            }
            return $value;
        }, $args);
    }

    /**
     * Tool definitions for Groq function calling.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTools(): array
    {
        return [
            // ----------------------------------------------------------------
            // Calendar Events
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_calendar_events',
                    'description' => 'Liste les événements du calendrier du site avec filtres optionnels',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => ['type' => 'string', 'description' => 'Recherche textuelle sur le titre'],
                            'start' => ['type' => 'string', 'description' => 'Filtrer les événements à partir de cette date ISO 8601'],
                            'end' => ['type' => 'string', 'description' => 'Filtrer les événements jusqu\'à cette date ISO 8601'],
                            'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_calendar_event',
                    'description' => 'Récupère le détail d\'un événement du calendrier',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['event_id'],
                        'properties' => [
                            'event_id' => ['type' => 'integer', 'description' => 'ID de l\'événement'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_calendar_event',
                    'description' => 'Crée un événement dans le calendrier',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['title', 'start_at'],
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Titre de l\'événement'],
                            'description' => ['type' => 'string', 'description' => 'Description'],
                            'start_at' => ['type' => 'string', 'description' => 'Date de début ISO 8601'],
                            'end_at' => ['type' => 'string', 'description' => 'Date de fin ISO 8601'],
                            'all_day' => ['type' => 'boolean', 'description' => 'Événement toute la journée'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_calendar_event',
                    'description' => 'Met à jour un événement du calendrier existant',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['event_id'],
                        'properties' => [
                            'event_id' => ['type' => 'integer', 'description' => 'ID de l\'événement à modifier'],
                            'title' => ['type' => 'string', 'description' => 'Nouveau titre'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                            'start_at' => ['type' => 'string', 'description' => 'Nouvelle date de début ISO 8601'],
                            'end_at' => ['type' => 'string', 'description' => 'Nouvelle date de fin ISO 8601'],
                            'all_day' => ['type' => 'boolean', 'description' => 'Événement toute la journée'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_calendar_event',
                    'description' => 'Supprime un événement du calendrier',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['event_id'],
                        'properties' => [
                            'event_id' => ['type' => 'integer', 'description' => 'ID de l\'événement à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Cleanings
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_cleanings',
                    'description' => 'Liste les nettoyages du site avec filtres optionnels',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['casual', 'daily', 'weekly', 'monthly'], 'description' => 'Filtrer par type de nettoyage'],
                            'search' => ['type' => 'string', 'description' => 'Recherche textuelle sur la description'],
                            'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cleaning',
                    'description' => 'Récupère le détail d\'un nettoyage',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['cleaning_id'],
                        'properties' => [
                            'cleaning_id' => ['type' => 'integer', 'description' => 'ID du nettoyage'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_cleaning',
                    'description' => 'Enregistre un nouveau nettoyage',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['material_id', 'description'],
                        'properties' => [
                            'material_id' => ['type' => 'integer', 'description' => 'ID du matériel nettoyé'],
                            'description' => ['type' => 'string', 'description' => 'Description du nettoyage effectué'],
                            'type' => ['type' => 'string', 'enum' => ['casual', 'daily', 'weekly', 'monthly'], 'description' => 'Type de nettoyage'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_cleaning',
                    'description' => 'Met à jour un nettoyage existant',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['cleaning_id'],
                        'properties' => [
                            'cleaning_id' => ['type' => 'integer', 'description' => 'ID du nettoyage à modifier'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                            'type' => ['type' => 'string', 'enum' => ['casual', 'daily', 'weekly', 'monthly'], 'description' => 'Nouveau type'],
                            'material_id' => ['type' => 'integer', 'description' => 'Nouveau matériel concerné'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_cleaning',
                    'description' => 'Supprime un nettoyage',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['cleaning_id'],
                        'properties' => [
                            'cleaning_id' => ['type' => 'integer', 'description' => 'ID du nettoyage à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Incidents
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_incidents',
                    'description' => 'Liste les incidents du site',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                            'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_incident',
                    'description' => 'Récupère le détail d\'un incident',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['incident_id'],
                        'properties' => [
                            'incident_id' => ['type' => 'integer', 'description' => 'ID de l\'incident'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_incident',
                    'description' => 'Signale un nouvel incident',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['description'],
                        'properties' => [
                            'description' => ['type' => 'string', 'description' => 'Description de l\'incident'],
                            'material_id' => ['type' => 'integer', 'description' => 'ID du matériel concerné'],
                            'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical'], 'description' => 'Sévérité'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_incident',
                    'description' => 'Met à jour un incident existant',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['incident_id'],
                        'properties' => [
                            'incident_id' => ['type' => 'integer', 'description' => 'ID de l\'incident à modifier'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                            'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical'], 'description' => 'Nouvelle sévérité'],
                            'status' => ['type' => 'string', 'enum' => ['open', 'in_progress', 'resolved', 'closed'], 'description' => 'Nouveau statut'],
                            'resolved_at' => ['type' => 'string', 'description' => 'Date de résolution ISO 8601'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_incident',
                    'description' => 'Supprime un incident',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['incident_id'],
                        'properties' => [
                            'incident_id' => ['type' => 'integer', 'description' => 'ID de l\'incident à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Item Movements
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_item_movements',
                    'description' => 'Liste les mouvements de stock du site',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['entry', 'exit'], 'description' => 'Filtrer par type de mouvement'],
                            'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_item_movement',
                    'description' => 'Récupère le détail d\'un mouvement de stock',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['movement_id'],
                        'properties' => [
                            'movement_id' => ['type' => 'integer', 'description' => 'ID du mouvement'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_item_movement',
                    'description' => 'Enregistre un mouvement de stock (entrée ou sortie)',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['item_id', 'type', 'quantity'],
                        'properties' => [
                            'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article'],
                            'type' => ['type' => 'string', 'enum' => ['entry', 'exit'], 'description' => 'Type de mouvement'],
                            'quantity' => ['type' => 'integer', 'description' => 'Quantité'],
                            'unit_price' => ['type' => 'number', 'description' => 'Prix unitaire (pour une entrée)'],
                            'notes' => ['type' => 'string', 'description' => 'Notes optionnelles'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_item_movement',
                    'description' => 'Met à jour un mouvement de stock existant',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['movement_id'],
                        'properties' => [
                            'movement_id' => ['type' => 'integer', 'description' => 'ID du mouvement à modifier'],
                            'quantity' => ['type' => 'integer', 'description' => 'Nouvelle quantité'],
                            'unit_price' => ['type' => 'number', 'description' => 'Nouveau prix unitaire'],
                            'notes' => ['type' => 'string', 'description' => 'Nouvelles notes'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_item_movement',
                    'description' => 'Supprime un mouvement de stock',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['movement_id'],
                        'properties' => [
                            'movement_id' => ['type' => 'integer', 'description' => 'ID du mouvement à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Items
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_items',
                    'description' => 'Liste les articles du site avec filtres optionnels',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                            'stock_status' => ['type' => 'string', 'enum' => ['ok', 'warning', 'critical'], 'description' => 'Filtrer par statut de stock'],
                            'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 20)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_item',
                    'description' => 'Récupère le détail d\'un article',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['item_id'],
                        'properties' => [
                            'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_item',
                    'description' => 'Crée un nouvel article dans le stock',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Nom de l\'article'],
                            'reference' => ['type' => 'string', 'description' => 'Référence interne'],
                            'description' => ['type' => 'string', 'description' => 'Description'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_item',
                    'description' => 'Met à jour un article existant',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['item_id'],
                        'properties' => [
                            'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article à modifier'],
                            'name' => ['type' => 'string', 'description' => 'Nouveau nom'],
                            'reference' => ['type' => 'string', 'description' => 'Nouvelle référence'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_item',
                    'description' => 'Supprime un article (uniquement si aucun mouvement de stock)',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['item_id'],
                        'properties' => [
                            'item_id' => ['type' => 'integer', 'description' => 'ID de l\'article à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Maintenances
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_maintenances',
                    'description' => 'Liste les maintenances du site avec filtres optionnels',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['planned', 'in_progress', 'completed', 'canceled'], 'description' => 'Filtrer par statut'],
                            'type' => ['type' => 'string', 'enum' => ['preventive', 'corrective', 'inspection', 'improvement'], 'description' => 'Filtrer par type'],
                            'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_maintenance',
                    'description' => 'Récupère le détail d\'une maintenance',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['maintenance_id'],
                        'properties' => [
                            'maintenance_id' => ['type' => 'integer', 'description' => 'ID de la maintenance'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_maintenance',
                    'description' => 'Crée une nouvelle maintenance',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['description'],
                        'properties' => [
                            'description' => ['type' => 'string', 'description' => 'Description de la maintenance'],
                            'reason' => ['type' => 'string', 'description' => 'Raison de la maintenance'],
                            'type' => ['type' => 'string', 'enum' => ['preventive', 'corrective', 'inspection', 'improvement'], 'description' => 'Type de maintenance'],
                            'realization' => ['type' => 'string', 'enum' => ['internal', 'external', 'both'], 'description' => 'Réalisation'],
                            'status' => ['type' => 'string', 'enum' => ['planned', 'in_progress', 'completed'], 'description' => 'Statut initial'],
                            'material_id' => ['type' => 'integer', 'description' => 'ID du matériel concerné'],
                            'started_at' => ['type' => 'string', 'description' => 'Date de début ISO 8601'],
                            'resolved_at' => ['type' => 'string', 'description' => 'Date de fin ISO 8601'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_maintenance',
                    'description' => 'Met à jour une maintenance existante',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['maintenance_id'],
                        'properties' => [
                            'maintenance_id' => ['type' => 'integer', 'description' => 'ID de la maintenance à modifier'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                            'reason' => ['type' => 'string', 'description' => 'Nouvelle raison'],
                            'type' => ['type' => 'string', 'enum' => ['preventive', 'corrective', 'inspection', 'improvement'], 'description' => 'Nouveau type'],
                            'realization' => ['type' => 'string', 'enum' => ['internal', 'external', 'both'], 'description' => 'Nouvelle réalisation'],
                            'status' => ['type' => 'string', 'enum' => ['planned', 'in_progress', 'completed', 'canceled'], 'description' => 'Nouveau statut'],
                            'started_at' => ['type' => 'string', 'description' => 'Nouvelle date de début ISO 8601'],
                            'resolved_at' => ['type' => 'string', 'description' => 'Nouvelle date de fin ISO 8601'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_maintenance',
                    'description' => 'Supprime une maintenance',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['maintenance_id'],
                        'properties' => [
                            'maintenance_id' => ['type' => 'integer', 'description' => 'ID de la maintenance à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Materials
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_materials',
                    'description' => 'Liste les matériels du site',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => ['type' => 'string', 'description' => 'Recherche textuelle'],
                            'per_page' => ['type' => 'integer', 'description' => 'Nombre de résultats'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_material',
                    'description' => 'Récupère le détail d\'un matériel',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['material_id'],
                        'properties' => [
                            'material_id' => ['type' => 'integer', 'description' => 'ID du matériel'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_material',
                    'description' => 'Crée un nouveau matériel dans une zone',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['name', 'zone_id'],
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Nom du matériel'],
                            'zone_id' => ['type' => 'integer', 'description' => 'ID de la zone où se trouve le matériel'],
                            'description' => ['type' => 'string', 'description' => 'Description du matériel'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_material',
                    'description' => 'Met à jour un matériel existant',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['material_id'],
                        'properties' => [
                            'material_id' => ['type' => 'integer', 'description' => 'ID du matériel à modifier'],
                            'name' => ['type' => 'string', 'description' => 'Nouveau nom'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description'],
                            'zone_id' => ['type' => 'integer', 'description' => 'Nouvelle zone'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_material',
                    'description' => 'Supprime un matériel',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['material_id'],
                        'properties' => [
                            'material_id' => ['type' => 'integer', 'description' => 'ID du matériel à supprimer'],
                        ],
                    ],
                ],
            ],
            // ----------------------------------------------------------------
            // Dashboard
            // ----------------------------------------------------------------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_dashboard_stats',
                    'description' => 'Récupère les statistiques du tableau de bord',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
        ];
    }
}
