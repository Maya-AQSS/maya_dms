<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AcademicHierarchyRepository implements AcademicHierarchyRepositoryInterface
{
    /**
     * Árbol de jerarquía académica.
     */
    public function getTree(): Collection
    {
        $locale = app()->getLocale();

        $studyTypes = DB::table('res_company')
            ->select(['id', 'name'])
            ->whereNotNull('parent_id')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $studies = DB::table('maya_core_study')
            ->select(['id', 'company_id', 'abbr', 'name'])
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $subjectsByStudy = DB::table('maya_core_study_maya_core_subject_rel as rel')
            ->join('maya_core_subject as subject', 'subject.id', '=', 'rel.maya_core_subject_id')
            ->select([
                'rel.maya_core_study_id as study_id',
                'subject.id',
                'subject.abbr',
                'subject.name',
            ])
            ->orderBy('subject.id')
            ->get()
            ->groupBy('study_id');

        $studiesByType = $studies->groupBy('company_id');

        $tree = $studyTypes->map(function ($type) use ($studiesByType, $subjectsByStudy, $locale): array {
            $typeStudies = $studiesByType->get($type->id, collect())->map(function ($study) use ($subjectsByStudy, $locale): array {
                $modules = $subjectsByStudy->get($study->id, collect())->map(function ($subject) use ($study, $locale): array {
                    return [
                        'id' => (string) $subject->id,
                        'name' => $this->localizedJsonText($subject->abbr, $subject->name, $locale),
                        'study_id' => (string) $study->id,
                    ];
                })->values()->all();

                return [
                    'id' => (string) $study->id,
                    'name' => $this->localizedJsonText($study->abbr, $study->name, $locale),
                    'study_type_id' => (string) $study->company_id,
                    'course_modules' => $modules,
                ];
            })->values()->all();

            return [
                'id' => (string) $type->id,
                'name' => (string) $type->name,
                'studies' => $typeStudies,
            ];
        })->values()->all();

        return new Collection($tree);
    }

    /**
     * Resuelve texto localizado desde JSONB (abbr/name) con fallback.
     */
    private function localizedJsonText(mixed $abbr, mixed $name, string $locale): string
    {
        $abbrText = $this->resolveLocaleValue($abbr, $locale);
        if ($abbrText !== null && $abbrText !== '') {
            return $abbrText;
        }

        $nameText = $this->resolveLocaleValue($name, $locale);
        if ($nameText !== null && $nameText !== '') {
            return $nameText;
        }

        return '';
    }

    /**
     * Extrae valor por locale con fallback a es/en/primer valor.
     */
    private function resolveLocaleValue(mixed $value, string $locale): ?string
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->pickLocaleValue($decoded, $locale);
            }

            return $value;
        }

        if (is_array($value)) {
            return $this->pickLocaleValue($value, $locale);
        }

        return null;
    }

    /**
     * Elige el valor localizado de un array de valores localizados.
     * 
     * @param array<string, mixed> $localized
     */
    private function pickLocaleValue(array $localized, string $locale): ?string
    {
        foreach ([$locale, 'es', 'en'] as $candidate) {
            $text = $localized[$candidate] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        foreach ($localized as $text) {
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }
}
