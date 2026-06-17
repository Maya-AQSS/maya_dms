<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\DocumentPolicy;
use App\Policies\TemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contrato del permiso de admin de SOLO LECTURA `dms.admin.read`:
 *
 *  - VE todo (detalle, listado e historial) sin ser creador/titular/revisor.
 *  - NUNCA autoriza una mutación, AUNQUE el usuario tenga además los slugs de escritura
 *    que el rol admin hereda por la cadena de roles (document.update/delete/version/...).
 *
 * Es la prueba de regresión del riesgo de escalada: el bypass de visibilidad de
 * admin-lectura no debe colarse en {@see DocumentPolicy::viewScoped()} ni equivalentes.
 */
class AdminReadAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ID = '99999999-9999-9999-9999-999999999999';
    private const CREATOR_ID = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    private const OWNER_ID = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

    /** Admin con TODOS los slugs de escritura + el de lectura total: el peor caso de escalada. */
    private const ADMIN_PERMISSIONS = [
        'dms.admin.read',
        'document.index', 'document.show', 'document.create', 'document.update',
        'document.delete', 'document.version', 'document.clone',
        'template.index', 'template.show', 'template.create', 'template.update',
        'template.delete', 'template.version', 'template.clone',
    ];

    private DocumentPolicy $documentPolicy;

    private TemplatePolicy $templatePolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentPolicy = app(DocumentPolicy::class);
        $this->templatePolicy = app(TemplatePolicy::class);
    }

    // ── Documentos ────────────────────────────────────────────────────────

    public function test_admin_read_can_view_foreign_personal_draft_document(): void
    {
        $admin = $this->admin();
        auth()->setUser($admin);
        $doc = $this->makeDocument(self::CREATOR_ID, self::OWNER_ID, 'draft');

        $this->assertTrue($this->documentPolicy->view($admin, $doc));
        $this->assertTrue($this->documentPolicy->viewAny($admin));
        $this->assertTrue($this->documentPolicy->viewHistory($admin, $doc));
    }

    public function test_admin_read_cannot_mutate_foreign_document_despite_write_slugs(): void
    {
        $admin = $this->admin();
        auth()->setUser($admin);
        $doc = $this->makeDocument(self::CREATOR_ID, self::OWNER_ID, 'draft');

        // Aunque tiene document.update/delete y ve el documento, no es titular ni está en
        // su ámbito real: viewScoped() lo excluye, por lo que toda escritura queda denegada.
        $this->assertFalse($this->documentPolicy->update($admin, $doc));
        $this->assertFalse($this->documentPolicy->delete($admin, $doc));
        $this->assertFalse($this->documentPolicy->clone($admin, $doc));
        $this->assertFalse($this->documentPolicy->attemptStartRevision($admin, $doc));
    }

    public function test_admin_read_cannot_break_segregation_of_duties_on_document(): void
    {
        $admin = $this->admin();
        auth()->setUser($admin);
        $doc = $this->makeDocument(self::CREATOR_ID, self::OWNER_ID, 'in_review');

        // SoD: revisar/enviar/publicar/delegar dependen de owner_id o de document_reviews,
        // nunca de la visibilidad. El admin de lectura no es ninguno de esos.
        $this->assertFalse($this->documentPolicy->review($admin, $doc));
        $this->assertFalse($this->documentPolicy->submit($admin, $doc));
        $this->assertFalse($this->documentPolicy->publish($admin, $doc));
        $this->assertFalse($this->documentPolicy->delegate($admin, $doc));
        $this->assertFalse($this->documentPolicy->share($admin, $doc));
    }

    // ── Plantillas ────────────────────────────────────────────────────────

    public function test_admin_read_can_view_foreign_personal_draft_template(): void
    {
        $admin = $this->admin();
        auth()->setUser($admin);
        $tpl = $this->makeTemplate(self::CREATOR_ID, 'draft');

        $this->assertTrue($this->templatePolicy->view($admin, $tpl));
        $this->assertTrue($this->templatePolicy->viewAny($admin));
        $this->assertTrue($this->templatePolicy->viewHistory($admin, $tpl));
    }

    public function test_admin_read_cannot_mutate_foreign_template_despite_write_slugs(): void
    {
        $admin = $this->admin();
        auth()->setUser($admin);
        $draft = $this->makeTemplate(self::CREATOR_ID, 'draft');
        $published = $this->makeTemplate(self::CREATOR_ID, 'published');

        // Borrador ajeno: solo el creador edita/descarta.
        $this->assertFalse($this->templatePolicy->update($admin, $draft));
        $this->assertFalse($this->templatePolicy->discard($admin, $draft));
        // Publicada personal ajena: fuera de ámbito → viewScoped() falsa → sin escritura.
        $this->assertFalse($this->templatePolicy->update($admin, $published));
        $this->assertFalse($this->templatePolicy->delete($admin, $published));
        $this->assertFalse($this->templatePolicy->clone($admin, $published));
        $this->assertFalse($this->templatePolicy->attemptStartRevision($admin, $published));
    }

    public function test_admin_read_cannot_break_segregation_of_duties_on_template(): void
    {
        $admin = $this->admin();
        auth()->setUser($admin);
        $tpl = $this->makeTemplate(self::CREATOR_ID, 'in_review');

        $this->assertFalse($this->templatePolicy->review($admin, $tpl));
        $this->assertFalse($this->templatePolicy->submitForReview($admin, $tpl));
        $this->assertFalse($this->templatePolicy->publish($admin, $tpl));
        $this->assertFalse($this->templatePolicy->assignReview($admin, $tpl));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function admin(): JwtUser
    {
        return new JwtUser([
            'id' => self::ADMIN_ID,
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => self::ADMIN_PERMISSIONS,
            'scope' => '',
        ]);
    }

    private function makeDocument(string $createdBy, string $ownerId, string $status): Document
    {
        $templateId = $this->makeTemplate($createdBy, 'draft')->id;

        $document = Document::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'title' => 'Documento admin-read test',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $createdBy,
            'owner_id' => $ownerId,
            'status' => $status,
        ]);
        $document->refresh();

        return $document;
    }

    private function makeTemplate(string $createdBy, string $status): Template
    {
        $template = Template::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla admin-read test',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $createdBy,
            'status' => $status,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        $template->refresh();

        return $template;
    }
}
