<?php

namespace App\Livewire;

use App\Models\StudyType;
use App\Models\Document;
use Livewire\Attributes\Layout;
use Livewire\Component;

class DidacticProgrammingList extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        // Hidratamos la jerarquía completa
        $hierarchy = StudyType::with(['studies.courseModules'])
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'studies' => $type->studies->map(fn ($study) => [
                    'id' => $study->id,
                    'name' => $study->name,
                    'course_modules' => $study->courseModules->map(fn ($module) => [
                        'id' => $module->id,
                        'name' => $module->name,
                    ]),
                ]),
            ]);

        // Hidratamos todas las programaciones accesibles para el usuario
        // El Global Scope 'user_access' ya filtra por el usuario autenticado
        $documents = Document::with(['study', 'courseModule', 'template'])
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'status' => $doc->status,
                'study_id' => $doc->study_id,
                'course_module_id' => $doc->course_module_id,
                'study_name' => $doc->study?->name ?? 'N/A',
                'module_name' => $doc->courseModule?->name ?? 'N/A',
                'updated_at' => $doc->updated_at->diffForHumans(),
            ]);

        return view('livewire.didactic-programming-list', [
            'hierarchyJson' => $hierarchy->toJson(),
            'documentsJson' => $documents->toJson(),
        ]);
    }
}
