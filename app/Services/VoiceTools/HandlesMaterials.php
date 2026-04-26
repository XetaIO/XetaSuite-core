<?php

declare(strict_types=1);

namespace XetaSuite\Services\VoiceTools;

use XetaSuite\Models\Material;
use XetaSuite\Models\User;

trait HandlesMaterials
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function listMaterials(User $user, array $args): string
    {
        $query = Material::query()
            ->forCurrentSite()
            ->select(['id', 'name', 'description'])
            ->orderBy('name');

        if (! empty($args['search'])) {
            $query->where('name', 'ilike', "%{$args['search']}%");
        }

        $perPage = min((int) ($args['per_page'] ?? 20), 50);
        $materials = $query->limit($perPage)->get(['id', 'name', 'description']);

        return json_encode(['data' => $materials, 'total' => $materials->count()]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function getMaterial(User $user, array $args): string
    {
        $material = Material::with(['zone', 'site', 'creator'])
            ->findOrFail($args['material_id']);

        return json_encode($material->toArray());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createMaterialAction(User $user, array $args): string
    {
        $material = $this->createMaterial->handle($user, $args);

        return json_encode([
            'success' => true,
            'id' => $material->id,
            'name' => $material->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function updateMaterialAction(User $user, array $args): string
    {
        $material = Material::findOrFail($args['material_id']);
        $material = $this->updateMaterial->handle($material, $args);

        return json_encode([
            'success' => true,
            'id' => $material->id,
            'name' => $material->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deleteMaterialAction(User $user, array $args): string
    {
        $material = Material::findOrFail($args['material_id']);
        $this->deleteMaterial->handle($material);

        return json_encode(['success' => true]);
    }
}
