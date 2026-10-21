<?php

namespace App\Services\Visualization;

use App\Models\Opportunity;
use App\Models\OpportunityPhoto;
use App\Models\PropertyPhotoAnalysis;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Support\OpportunityPhotoPathPolicy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoSceneAnalyzer
{
    private const ROOMS = [
        'sala' => 'living',
        'estar' => 'living',
        'quarto' => 'bedroom',
        'suite' => 'bedroom',
        'suíte' => 'bedroom',
        'banheiro' => 'bathroom',
        'lavabo' => 'bathroom',
        'cozinha' => 'kitchen',
        'varanda' => 'balcony',
        'sacada' => 'balcony',
        'garagem' => 'garage',
        'vaga' => 'garage',
        'fachada' => 'facade',
        'area externa' => 'outdoor',
        'área externa' => 'outdoor',
    ];

    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly PhotoPixelSampler $pixels,
    ) {}

    public function analyzeOpportunity(User $user, int $opportunityId): array
    {
        $this->entitlement->assert($user, 'photo_analysis');
        $opportunity = Opportunity::query()->where('user_id', $user->id)->with('photos')->whereKey($opportunityId)->firstOrFail();
        PropertyPhotoAnalysis::query()
            ->where('subject_type', 'opportunity')
            ->where('subject_id', $opportunityId)
            ->whereNotIn('origin', ['neural', 'neural_object_detection'])
            ->delete();

        foreach ($opportunity->photos as $photo) {
            if ($this->hasNeuralEvidence('opportunity', $opportunityId, (string) $photo->original_name)) {
                continue;
            }
            $this->store($user, 'opportunity', $opportunityId, $photo);
        }

        return $this->existing('opportunity', $opportunityId);
    }

    public function forget(string $subjectType, int $subjectId, bool $preserveNeural = false): void
    {
        PropertyPhotoAnalysis::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->when($preserveNeural, fn ($query) => $query->whereNotIn('origin', ['neural', 'neural_object_detection']))
            ->delete();
    }

    public function hasNeuralEvidence(string $subjectType, int $subjectId, string $sourceName): bool
    {
        return PropertyPhotoAnalysis::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('source_name', $sourceName)
            ->whereIn('origin', ['neural', 'neural_object_detection'])
            ->exists();
    }

    public function existing(string $subjectType, int $subjectId): array
    {
        return PropertyPhotoAnalysis::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderBy('id')
            ->get()
            ->map(fn (PropertyPhotoAnalysis $row) => [
                'id' => $row->id,
                'source_photo_id' => $row->source_photo_id,
                'source_name' => $row->source_name,
                'room_guess' => $row->room_guess,
                'elements' => $row->elements,
                'confidence' => $row->confidence,
                'detection_confidence' => $row->detection_confidence,
                'room_confidence' => $row->room_confidence ?? ($row->room_guess ? $row->confidence : null),
                'room_provenance' => $row->room_provenance,
                'origin' => $row->origin,
                'quality' => $row->quality,
                'limitations' => $row->limitations,
                'palette' => $row->palette,
                'pixel_stats' => $row->pixel_stats,
            ])
            ->all();
    }

    public function analyzeNamedFile(
        User $user,
        string $subjectType,
        int $subjectId,
        string $originalName,
        ?string $absolutePath,
        ?int $sourcePhotoId = null,
    ): void {
        $name = $this->normalize($originalName);
        $room = $this->guessRoom($name);
        $elements = $this->guessElements($name);
        $sample = $this->pixels->sample($absolutePath);
        $confidence = $room ? 45 : 15;
        if ($sample['ok'] ?? false) {
            $confidence = min(72, $confidence + 12);
        }
        $origin = ($sample['ok'] ?? false)
            ? ($room ? 'filename_and_pixels' : 'pixel_palette')
            : ($room ? 'filename_heuristic' : 'inference');

        PropertyPhotoAnalysis::query()->create([
            'tenant_id' => (int) $user->tenant_id,
            'user_id' => $user->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'source_photo_id' => $sourcePhotoId,
            'source_name' => $originalName,
            'room_guess' => $room,
            'elements' => $elements,
            'confidence' => $confidence,
            'detection_confidence' => null,
            'room_confidence' => $room ? $confidence : null,
            'room_provenance' => [
                'method' => $room ? 'filename_heuristic' : 'unclassified',
                'source' => 'filename',
                'is_neural_room_classifier' => false,
                'classification_status' => $room ? 'classified' : 'unclassified',
            ],
            'origin' => $origin,
            'quality' => ($sample['ok'] ?? false) ? 'sampled' : (strlen($name) < 4 ? 'unreadable' : 'low'),
            'palette' => $sample['palette'] ?? null,
            'pixel_stats' => $sample['ok'] ?? false ? [
                'width' => $sample['width'] ?? null,
                'height' => $sample['height'] ?? null,
                'brightness' => $sample['brightness'] ?? null,
                'aspect' => $sample['aspect'] ?? null,
            ] : ['reason' => $sample['reason'] ?? 'unavailable'],
            'limitations' => [
                $sample['ok'] ?? false
                    ? 'A paleta veio do histograma da imagem; não identifica objeto nem medida.'
                    : 'A classificação usa o nome do arquivo, não um modelo de visão treinado.',
                'Nenhuma medida foi extraída da fotografia.',
                'Observação visual não confirma dados do anunciante.',
            ],
        ]);
    }

    private function store(User $user, string $subjectType, int $subjectId, OpportunityPhoto $photo): void
    {
        $path = (string) $photo->path;
        $absolute = null;
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://') && OpportunityPhotoPathPolicy::allows($path)) {
            $absolute = Storage::disk('public')->path($path);
        }
        $this->analyzeNamedFile($user, $subjectType, $subjectId, (string) $photo->original_name, $absolute, $photo->id);
    }

    private function guessRoom(string $name): ?string
    {
        foreach (self::ROOMS as $needle => $room) {
            if (Str::contains($name, $this->normalize($needle))) {
                return $room;
            }
        }

        return null;
    }

    private function guessElements(string $name): array
    {
        $elements = [];
        foreach (['piso' => 'piso', 'parede' => 'parede', 'janela' => 'janela', 'porta' => 'porta', 'sofa' => 'sofa', 'cama' => 'cama', 'pia' => 'pia'] as $needle => $label) {
            if (Str::contains($name, $needle)) {
                $elements[] = ['label' => $label, 'origin' => 'filename_heuristic'];
            }
        }

        return $elements;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
