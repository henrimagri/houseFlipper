<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Projeto;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentoTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): User
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function project(User $user, string $name = 'Projeto QA'): Projeto
    {
        $property = Propriedade::create([
            'tenant_id' => $user->tenant_id,
            'apelido' => 'Imóvel QA',
        ]);

        return Projeto::create([
            'tenant_id' => $user->tenant_id,
            'propriedade_id' => $property->id,
            'nome' => $name,
        ]);
    }

    private function pdf(string $name = 'contrato.pdf', int $kilobytes = 4): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_owner_can_upload_list_download_update_and_delete_private_document(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-owner');
        $project = $this->project($owner);
        $this->actingAs($owner, 'sanctum');

        $response = $this->post('/api/documentos', [
            'arquivo' => $this->pdf('Contrato de compra.pdf'),
            'projeto_id' => $project->id,
            'categoria' => 'Contratos',
            'status' => 'em_revisao',
            'descricao' => 'Documento fictício para QA.',
            'data_vencimento' => now()->addMonth()->toDateString(),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.nome', 'Contrato de compra.pdf')
            ->assertJsonPath('data.projeto.id', $project->id)
            ->assertJsonMissingPath('data.caminho')
            ->assertJsonMissingPath('data.tenant_id');

        $id = $response->json('data.id');
        $document = Documento::withoutGlobalScopes()->findOrFail($id);
        $this->assertStringStartsWith('documents/'.$owner->tenant_id.'/', $document->caminho);
        $this->assertStringNotContainsString('Contrato', $document->caminho);
        Storage::disk('local')->assertExists($document->caminho);

        $this->getJson('/api/documentos?busca=Contrato&categoria=Contratos&status=em_revisao&projeto_id='.$project->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/documentos/'.$id)->assertOk()->assertJsonPath('data.descricao', 'Documento fictício para QA.');

        $download = $this->get('/api/documentos/'.$id.'/download', ['Accept' => 'application/json'])->assertOk();
        $download->assertHeader('cache-control', 'no-store, private');
        $download->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('content-disposition'));

        $this->putJson('/api/documentos/'.$id, [
            'projeto_id' => null,
            'categoria' => 'Jurídico',
            'status' => 'aprovado',
            'descricao' => null,
            'data_vencimento' => null,
        ])->assertOk()->assertJsonPath('data.categoria', 'Jurídico')->assertJsonPath('data.projeto_id', null);

        $this->deleteJson('/api/documentos/'.$id)->assertOk();
        $this->getJson('/api/documentos/'.$id)->assertNotFound();
        Storage::disk('local')->assertMissing($document->caminho);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('documentos', ['id' => $id, 'deletado_flg' => true]);
    }

    public function test_other_tenant_cannot_discover_download_mutate_or_delete_document(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-a');
        $this->actingAs($owner, 'sanctum');
        $id = $this->post('/api/documentos', [
            'arquivo' => $this->pdf(), 'categoria' => 'Contratos',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $path = Documento::withoutGlobalScopes()->findOrFail($id)->caminho;

        $this->actingAs($this->account('documents-b'), 'sanctum');
        $this->getJson('/api/documentos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/documentos/'.$id)->assertNotFound();
        $this->get('/api/documentos/'.$id.'/download', ['Accept' => 'application/json'])->assertNotFound();
        $this->putJson('/api/documentos/'.$id, ['categoria' => 'Ataque'])->assertNotFound();
        $this->deleteJson('/api/documentos/'.$id)->assertNotFound();
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('documentos', ['id' => $id, 'deletado_flg' => false]);
    }

    public function test_upload_rejects_foreign_project_unsafe_type_oversize_and_privileged_fields(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-validation');
        $foreign = $this->account('documents-foreign');
        $foreignProject = $this->project($foreign, 'Projeto externo');
        $this->actingAs($owner, 'sanctum');

        $this->post('/api/documentos', [
            'arquivo' => $this->pdf(), 'categoria' => 'Contratos', 'projeto_id' => $foreignProject->id,
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('projeto_id');
        $this->post('/api/documentos', [
            'arquivo' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php'), 'categoria' => 'Contratos',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('arquivo');
        $this->post('/api/documentos', [
            'arquivo' => UploadedFile::fake()->create('payload.php', 1, 'application/pdf'), 'categoria' => 'Contratos',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('arquivo');
        $this->post('/api/documentos', [
            'arquivo' => $this->pdf('grande.pdf', 10241), 'categoria' => 'Contratos',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

        $id = $this->post('/api/documentos', [
            'arquivo' => $this->pdf('../../nome-seguro.pdf'),
            'categoria' => 'Contratos',
            'tenant_id' => $foreign->tenant_id,
            'caminho' => 'public/ataque.php',
            'deletado_flg' => true,
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $document = Documento::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame($owner->tenant_id, $document->tenant_id);
        $this->assertFalse($document->deletado_flg);
        $this->assertStringStartsWith('documents/'.$owner->tenant_id.'/', $document->caminho);
        $this->assertStringNotContainsString('..', $document->nome_original);
    }

    public function test_storage_failure_returns_neutral_error_without_database_record(): void
    {
        $owner = $this->account('documents-failure');
        $this->actingAs($owner, 'sanctum');
        Storage::shouldReceive('disk')->once()->andThrow(new RuntimeException('sensitive storage detail'));
        $this->post('/api/documentos', [
            'arquivo' => $this->pdf(), 'categoria' => 'Contratos',
        ], ['Accept' => 'application/json'])->assertStatus(503)
            ->assertJsonMissing(['sensitive storage detail']);
        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_database_failure_removes_orphan_file(): void
    {
        $owner = $this->account('documents-database-failure');
        $this->actingAs($owner, 'sanctum');
        Storage::fake('local');
        Event::listen('eloquent.creating: '.Documento::class, fn () => throw new RuntimeException('database failure'));
        try {
            app(DocumentoService::class)->criar(
                ['categoria' => 'Contratos'],
                $this->pdf('sem-orfao.pdf'),
                $owner,
            );
            $this->fail('A criação deveria falhar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('database failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.Documento::class);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_rechecks_active_project_and_removes_file_if_it_was_deleted(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-project-recheck');
        $this->actingAs($owner, 'sanctum');
        $project = $this->project($owner);
        $project->update(['deletado_flg' => true]);

        try {
            app(DocumentoService::class)->criar(
                ['categoria' => 'Contratos', 'projeto_id' => $project->id],
                $this->pdf('sem-projeto-orfao.pdf'),
                $owner,
            );
            $this->fail('O upload deveria revalidar o projeto sob lock.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('documentos', 0);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_delete_storage_failure_preserves_document_and_returns_neutral_error(): void
    {
        $owner = $this->account('documents-delete-failure');
        $document = Documento::create([
            'tenant_id' => $owner->tenant_id,
            'nome_original' => 'contrato.pdf',
            'caminho' => 'documents/'.$owner->tenant_id.'/contrato.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 10,
            'categoria' => 'Contratos',
        ]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($document->caminho)->andReturnTrue();
        $disk->shouldReceive('readStream')->once()->with($document->caminho)->andThrow(new RuntimeException('sensitive delete failure'));
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->actingAs($owner, 'sanctum')->deleteJson('/api/documentos/'.$document->id)
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Não foi possível excluir o documento. Tente novamente.']);

        $this->assertDatabaseHas('documentos', ['id' => $document->id, 'deletado_flg' => false]);
    }

    public function test_delete_rejects_unmanaged_document_path_without_changing_record(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-unmanaged-path');
        $document = Documento::create([
            'tenant_id' => $owner->tenant_id,
            'nome_original' => 'segredo.pdf',
            'caminho' => 'documents/'.$owner->tenant_id.'/../segredo.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 10,
            'categoria' => 'Contratos',
        ]);

        $this->actingAs($owner, 'sanctum')->deleteJson('/api/documentos/'.$document->id)
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Não foi possível excluir o documento. Tente novamente.']);

        $this->assertDatabaseHas('documentos', ['id' => $document->id, 'deletado_flg' => false]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_document_delete_restores_private_file_and_database_record(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-delete-rollback');
        $document = Documento::create([
            'tenant_id' => $owner->tenant_id,
            'nome_original' => 'rollback.pdf',
            'caminho' => 'documents/'.$owner->tenant_id.'/rollback.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 10,
            'categoria' => 'Contratos',
        ]);
        Storage::disk('local')->put($document->caminho, 'document-bytes');
        $event = 'eloquent.updating: '.Documento::class;
        Event::listen($event, fn () => throw new RuntimeException('simulated database failure'));

        try {
            $this->actingAs($owner, 'sanctum')->deleteJson('/api/documentos/'.$document->id)->assertStatus(500);

            Storage::disk('local')->assertExists($document->caminho);
            $this->assertSame([$document->caminho], Storage::disk('local')->allFiles());
            $this->assertDatabaseHas('documentos', ['id' => $document->id, 'deletado_flg' => false]);
        } finally {
            Event::forget($event);
        }
    }

    public function test_document_pagination_and_filters_are_validated(): void
    {
        $owner = $this->account('documents-pagination');
        $this->actingAs($owner, 'sanctum');
        foreach (range(1, 26) as $index) {
            Documento::create([
                'nome_original' => sprintf('Documento %02d.pdf', $index),
                'caminho' => 'documents/'.$owner->tenant_id.'/'.$index.'.pdf',
                'mime_type' => 'application/pdf',
                'tamanho_bytes' => 10,
                'categoria' => 'QA',
            ]);
        }

        $this->getJson('/api/documentos?per_page=25&page=1')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/documentos?per_page=101')->assertUnprocessable();
        $this->getJson('/api/documentos?status=desconhecido')->assertUnprocessable();
        $this->getJson('/api/documentos?projeto_id=abc')->assertUnprocessable();
    }

    public function test_document_patch_is_partial_and_preserves_file_metadata_and_project(): void
    {
        Storage::fake('local');
        $owner = $this->account('documents-patch');
        $project = $this->project($owner);
        $this->actingAs($owner, 'sanctum');
        $id = $this->post('/api/documentos', [
            'arquivo' => $this->pdf('contrato-patch.pdf'),
            'projeto_id' => $project->id,
            'categoria' => 'Contratos',
            'status' => 'em_revisao',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $before = Documento::findOrFail($id);

        $this->patchJson('/api/documentos/'.$id, ['descricao' => 'Metadado alterado isoladamente.'])
            ->assertOk()
            ->assertJsonPath('data.descricao', 'Metadado alterado isoladamente.')
            ->assertJsonPath('data.categoria', 'Contratos')
            ->assertJsonPath('data.projeto_id', $project->id);

        $after = Documento::findOrFail($id);
        $this->assertSame($before->caminho, $after->caminho);
        $this->assertSame($before->nome_original, $after->nome_original);
        $this->assertSame($before->mime_type, $after->mime_type);
        $this->assertSame($before->tamanho_bytes, $after->tamanho_bytes);
        $this->assertSame('em_revisao', $after->status);
        Storage::disk('local')->assertExists($after->caminho);

        $this->putJson('/api/documentos/'.$id, ['descricao' => 'Incompleto'])
            ->assertUnprocessable()->assertJsonValidationErrors('categoria');
    }
}
