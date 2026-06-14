# Errores de arquitectura — maya_dms/backend

> Auditoría automática contra el flujo Controller -> Service (DTOs) -> Repository -> Model + Policies.

## Resumen

- Archivos revisados: **415**
- Violaciones: **40** — CRITICAL: 0 · HIGH: 2 · MEDIUM: 16 · LOW: 22

## Violaciones por severidad

| Severidad | Regla | Archivo | Línea | Problema | Corrección sugerida |
|---|---|---|---|---|---|
| HIGH | R7 | `app/Http/Controllers/Api/UserController.php` | 31-49,65-85,101-121,130-148 | Ninguno de los 4 endpoints (index, reviewerCandidates, documentReviewerCandidates, ownerCandidates) usa un FormRequest. Reciben Illuminate\Http\Request y leen input crudo sin validar mediante $request->get('search'), $request->get('per_page'), $request->query('exclude_user_id') y ReviewerCandidateFilterDto::fromRequest($request) (que a su vez lee $request->query() crudo en app/DTOs/Users/ReviewerCandidateFilterDto.php). No hay reglas de validacion (longitudes, formato uuid de exclude_user_id, etc.). | Crear FormRequests (p.ej. SearchUsersRequest, ReviewerCandidatesRequest) con rules() para search/per_page/exclude_user_id/contexto academico y consumir $request->validated(); construir ReviewerCandidateFilterDto desde datos validados. |
| HIGH | R6 | `app/Http/Controllers/Api/UserController.php` | 34-36,67-69,103-105,132-135 | Autorizacion inline dispersa en cada metodo: 'if (! $user instanceof JwtUser \|\| ! $user->hasPermission('template.show')) abort(403)'. Cuatro checks ad-hoc de permisos en el controlador en lugar de delegarse a Policy/Gate o al authorize() de un FormRequest. Viola R1 (sin checks de permiso inline en endpoint) y R6 (control de acceso debe vivir en Policies). | Mover el control de acceso al authorize() de un FormRequest o a un Gate/Policy (p.ej. Gate::authorize('searchUsers')), eliminando los abort(403) manuales del controlador. |
| MEDIUM | R4 | `app/DTOs/Documents/DocumentDto.php` | 156-161 | resolveTemplateName() ejecuta una query Eloquent directa dentro del DTO (EntityVersion::query()->whereKey(...)->where('versionable_type', Template::class)->first()) como fallback cuando la relacion no esta cargada. Las queries a BD deben vivir en la capa Repository (R4), no en el mapper Model->DTO. El docblock reconoce que es 'ultimo recurso', pero sigue siendo acceso a Eloquent fuera de su capa. | Mover el fallback a un Resolver/Repository (p.ej. extender DocumentTemplateVersionNumberResolver con resolveName) e inyectar el valor ya resuelto antes de construir el DTO, dejando fromModel sin acceso a BD. |
| MEDIUM | R1 | `app/Http/Controllers/Api/MediaController.php` | 60-63 | show() captura \Exception generica del Service y deriva el codigo HTTP (404 vs 403) con str_contains($e->getMessage(), 'no encontrada'). Acoplamiento fragil del controlador al texto de los mensajes del Service; un cambio de copy rompe el mapeo de status. | Que el Service lance excepciones tipadas (NotFoundHttpException / AccessDeniedHttpException o excepciones de dominio) y dejar que el handler las traduzca, en vez de inspeccionar el string del mensaje. |
| MEDIUM | R7 | `app/Http/Requests/TemplateBlocks/BulkUpdateTemplateBlockRequest.php` | 12-15 | authorize() devuelve `true` incondicionalmente en un endpoint mutador (PUT /blocks/bulk que cambia block_state). El control de acceso real existe pero se delega al controlador (TemplateBlockBulkController::bulkUpdate, lineas 69-78, hace $this->authorize('updateTemplateBlock', $templateModel) por cada plantilla). Verificado: la autorizacion SI se aplica, por lo que no hay agujero de seguridad; pero la regla R7 pide que authorize() delegue en Policy/Gate cuando hay reglas de acceso, en lugar de `return true`. A diferencia de los demas FormRequests del lote (Reorder/Store/Update) que si llaman $this->user()->can('...TemplateBlock', resolveTemplate()), este queda inconsistente. | Reutilizar el trait ResolvesTemplateForBlockAuthorization y autorizar en el FormRequest. Como el bulk afecta a varias plantillas via `ids.*`, resolver las plantillas de los bloques y comprobar can('updateTemplateBlock') sobre todas (o mantener la comprobacion en el controller pero documentar que authorize() es intencionalmente permisivo). |
| MEDIUM | R6 | `app/Http/Requests/Templates/UpdateTemplateRequest.php` | 25-37 | authorize() contiene logica de autorizacion inline para la transferencia de propiedad: compara $this->user()->getAuthIdentifier() contra $template->created_by y consulta hasPermission('template.transfer-ownership') directamente en el FormRequest, en lugar de delegar todo en TemplatePolicy. La regla R6 pide que el control de acceso (ownership + permiso) viva en la Policy. El resto de ramas si delegan via can('update', ...). | Mover la regla de cambio de owner (creador actual o admin con template.transfer-ownership, y la restriccion de mixed update) a un metodo de TemplatePolicy (p.ej. transferOwnership / updateWithOwnerChange) y que authorize() solo invoque $this->user()->can(...). |
| MEDIUM | R8 | `app/Http/Resources/TemplateVersionResource.php` | 31 | El Resource resuelve datos por su cuenta: invoca app(TemplateVersionBlockLayerResolver::class)->resolveBlocksSnapshot($dto->id) y resuelve nombres de usuario (ResolvesUserNames) dentro de toArray(). Un Resource solo debe dar forma a la respuesta; reconstruir el snapshot de bloques y resolver autores/revisores es trabajo de Service que debería venir ya en el DTO. Esto rompe la separación de capas y dificulta el testing del formateo. | Mover la resolución del snapshot de bloques y de nombres al Service que produce EntityVersionDto, de modo que el Resource reciba blocks_snapshot, author_name y reviewer_names ya resueltos en el DTO y solo los mapee. |
| MEDIUM | R4 | `app/Notifications/Rules/PendingValidationsThresholdRule.php` | 23-36 | Regla de notificacion programada que consulta `document_reviews` directamente con `DB::table()` (query builder crudo, incl. `havingRaw`, `count()`). Aunque no esta en `app/Services`, contiene logica de acceso a datos que la arquitectura del repo encapsula en Repositories (existe `DocumentRepositoryInterface::countPendingReviewsForDocument` y similares). Acoplamiento directo al esquema (nombres de tabla/columna) fuera de la capa Repository. | Extraer las consultas a un metodo de repositorio (p. ej. un `ReviewInboxRepository` o ampliar `DocumentRepositoryInterface`) que devuelva los revisores con conteo > umbral; la regla solo orquesta y publica. |
| MEDIUM | R4 | `app/Notifications/Rules/ValidationDeadlineApproachingRule.php` | 32-44 | Regla de notificacion programada que consulta `documents` join `entity_versions` con `DB::table()` y `whereRaw` (expresiones JSON especificas de Postgres) directamente. Acceso a datos crudo fuera de la capa Repository, acoplado al esquema y al dialecto SQL; la app encapsula este tipo de consultas en Repositories. | Mover la consulta de documentos con deadline proximo a un metodo de repositorio (p. ej. `DocumentRepositoryInterface::findApproachingDeadline(int $days)`) que devuelva DTOs/filas tipadas; la regla itera y publica. |
| MEDIUM | R2 | `app/Services/ApiTeamEmbedService.php` | 55-102 | La implementación de los métodos embedOnTemplate/embedOnTemplates/embedOnDocument/embedOnDocuments recibe modelos Eloquent y los muta con setAttribute() dentro del Service, lo que rompe la separación de capas (R2). El núcleo correcto (resolveTemplateTeam/resolveDocumentTeam → array) ya existe; estos son adaptadores deprecated de compatibilidad. Sin impacto de seguridad. | Eliminar los métodos embedOn* una vez todos los controllers usen resolveTemplateTeam()/resolveDocumentTeam() y apliquen el array resultante al Resource sin mutar el modelo en el Service. |
| MEDIUM | R2 | `app/Services/Contracts/ApiTeamEmbedServiceInterface.php` | 33-57 | Métodos embedOnTemplate($template, ...), embedOnTemplates(iterable), embedOnDocument($document, ...), embedOnDocuments(iterable) aceptan y mutan modelos Eloquent (setAttribute) en la capa Service. Aunque los parámetros son sin tipar para 'no aceptar el modelo como tipo', semánticamente operan sobre modelos Eloquent, contrario a R2. Están marcados @deprecated en favor de resolveTemplateTeam()/resolveDocumentTeam() (que devuelven array). Aún en uso por varios controllers (TemplateController, DocumentOptionsController, TemplateStateController). | Completar la migración a resolveTemplateTeam()/resolveDocumentTeam() en todos los controllers y eliminar los métodos embedOn* deprecated del contrato y la implementación. |
| MEDIUM | R2 | `app/Services/Contracts/CommentServiceInterface.php` | 23 | findModelOrFail(string $id, ?string $readerUserId = null): Comment devuelve un modelo Eloquent desde el Service. Viola R2 (debe devolver DTO). La implementación CommentService::findModelOrFail está anotada @internal para policy gates; el resto del Service sí devuelve CommentDto correctamente. Sin impacto de seguridad → MEDIUM. | Si el gate de policy puede operar sobre CommentDto o sobre el id, eliminar la variante que devuelve el modelo. En caso contrario, mantenerla estrictamente acotada a autorización y documentarlo en el contrato. |
| MEDIUM | R2 | `app/Services/Contracts/DocumentServiceInterface.php` | 56-72, 134, 177-298 | El contrato del Service declara métodos que devuelven modelos Eloquent en lugar de DTOs: findModelOrFail(): Document, findModelOrFailWithoutUserAccess(): Document, resolveDocumentWithPublishedFallback(): Document, findLatestPublishedVersion(): ?EntityVersion, submitToReview(): Document, destroyVersion(): Document, listReviews(): Collection (de modelos), listOrderedByCreatedAtDesc(): Collection, resolveWorkingRevisionConflict(Document), attachShareMetadataForViewer(Collection), etc. R2 exige que los Services devuelvan DTOs/colecciones de DTOs, nunca modelos Eloquent. Es un escape hatch documentado ("variante de uso interno" para policy gates) usado de forma consistente por los controllers, sin impacto de seguridad, por eso MEDIUM y no HIGH. | A medio plazo, migrar a DTOs: los gates de policy pueden basarse en un DTO o en el id; donde el modelo es imprescindible para el Resource, considerar un DocumentDto enriquecido. Como mínimo, documentar y acotar estos métodos a un único uso (policy) para evitar que se conviertan en el camino por defecto. |
| MEDIUM | R2 | `app/Services/Contracts/TemplateBlockServiceInterface.php` | 32 | findModelOrFail(string $id): TemplateBlock devuelve un modelo Eloquent desde el Service (R2). Comentado como 'Devuelve el modelo Eloquent de un bloque. Variante de uso interno'. El resto del contrato devuelve TemplateBlockDto. Sin impacto de seguridad → MEDIUM. | Acotar a uso de policy/autorización o sustituir por una variante que devuelva DTO si el caso de uso lo permite. |
| MEDIUM | R2 | `app/Services/Contracts/TemplateServiceInterface.php` | 47-67, 105-228 | El contrato declara métodos que devuelven modelos Eloquent en vez de DTOs: findModelOrFail(): Template, findModelOrFailWithoutUserAccess(): Template, findOrFailWithoutCatalogScope(): Template, findManyByIds(): Eloquent\Collection<Template>, findLatestPublishedVersion(): ?EntityVersion, listPublishedVersions(): Collection (de modelos), resolveWorkingRevisionConflict(Template), update(Template, ...), overlayPublishedSnapshotForNonOwners(Collection), resolveTemplateViewerContext(Template, ...). Viola R2 (Service no debe exponer Eloquent). Escape hatch documentado y de uso consistente en controllers; sin impacto de seguridad. | Igual que DocumentService: tender hacia DTOs. Donde se reciba un Template como parámetro (update, resolveTemplateViewerContext), preferir el id + carga interna en el Service para no obligar al controller a manejar el modelo. |
| MEDIUM | R6 | `app/Services/DocumentShareService.php` | 29-30, 63-64 | Comprobacion de propiedad (ownership) inline dentro del Service: `if ($document->owner_id !== $actorId) abort(403, 'Solo el titular puede gestionar colaboradores.')` en upsertDocumentShare y removeDocumentShare. Per R6 el control de acceso de tipo ownership deberia vivir en una Policy (p.ej. DocumentPolicy::manageShares) invocada con authorize() desde el controlador, no dispersa en el Service. | Extraer la regla a DocumentPolicy y autorizar en el controlador (`$this->authorize('manageShares', $document)`), o inyectar Gate y delegar; el Service deberia recibir el documento ya autorizado. |
| MEDIUM | R2 | `app/Services/UserFavoriteService.php` | 22-30 | findTemplateModelOrFail() y findDocumentModelOrFail() devuelven modelos Eloquent (Template / Document) desde el Service, violando R2 (un Service debe devolver DTOs o colecciones de DTOs, nunca modelos Eloquent). Atenuante: delegan a repositorios (no usan Eloquent directo, R2 datos OK) y el modelo NO se serializa en la respuesta — FavoriteController solo lo usa para Gate::authorize('view', $model) (verificado en app/Http/Controllers/Api/FavoriteController.php:38,69). No hay fuga de datos al cliente, por eso MEDIUM y no CRITICAL. | Si se desea cumplir R2 estricto: mover la resolucion de autorizacion (Gate::authorize por id) a una Policy con metodo que reciba el id, o exponer un metodo del Service que encapsule la comprobacion de acceso sin devolver el modelo al controller. Alternativamente documentar la excepcion como patron aceptado (modelo solo-para-policy). |
| MEDIUM | R2 | `app/Services/UserProfileService.php` | 40-119 | getProfile() y buildFallbackProfile() devuelven un array asociativo crudo en lugar de un DTO. R2 exige que los Services devuelvan DTOs. No hay acceso a Eloquent directo (usa repositorios + Cache), por lo que no es CRITICAL; es una desviacion de tipado/contrato. | Introducir un UserProfileDto (readonly) con fromArray()/fromFdwRow() y devolverlo desde getProfile(); el caché puede seguir guardando el array serializado o el DTO. El Resource del endpoint /me consumiria el DTO en lugar del array. |
| LOW | R4 | `app/DTOs/Documents/DocumentDto.php` | 188-191 | resolveTemplateVersionNumber() invoca app(DocumentTemplateVersionNumberResolver::class)->resolve(...) dentro del DTO, que internamente consulta la BD. El mapper Model->DTO dispara resolucion contra BD en lugar de recibir el dato precalculado por el Service. | Precalcular template_version_number en el Service/Repository y pasarlo como atributo (ya existe el preloaded 'template_version_number'); evitar la rama que llama al resolver desde el DTO. |
| LOW | R3 | `app/DTOs/Themes/ThemeDto.php` | 24-48 | ThemeDto es un DTO de salida (devuelto por el Service al Controller) pero NO expone metodo de conversion Model->DTO (fromModel/fromEloquent). La logica de mapeo Model->DTO vive dispersa en ThemeRepository.php:188 (new ThemeDto(...)), inconsistente con el resto de DTOs de salida del repo (ProcessDto, DocumentDto, TemplateDto, CommentDto, DocumentReviewDto) que sí exponen fromModel(). R3 pide que el DTO exponga el conversor. | Anadir ThemeDto::fromModel(Theme): self centralizando el mapeo (actualmente en ThemeRepository), o documentar explicitamente que el mapeo de Theme vive en el repositorio por construirse desde columnas FDW; uniformar con la convencion fromModel del resto. |
| LOW | R8 | `app/Http/Controllers/Api/CoverImageController.php` | 27-33 | El controller no usa un API Resource: construye el JSON de respuesta inline ('data' => ['src' => ..., 'url' => ...]) a partir del array crudo que devuelve CoverImageService::upload(). El resto de controllers del lote forman la respuesta con Resources. Es una respuesta pequeña de subida de media, pero rompe la uniformidad de R8. | Crear un CoverImageResource (o reutilizar uno de media) que reciba el resultado de upload() y devolverlo desde store(), en lugar de ensamblar el array a mano en el controller. Idealmente el service debería devolver un DTO en vez de array<string,mixed> (R2), lo que facilitaría el Resource. |
| LOW | R8 | `app/Http/Controllers/Api/DocumentShareController.php` | 47 | store() devuelve response()->json(['data' => (new DocumentShareResource($data))->toArray($request)], 201) construyendo manualmente el array del Resource en vez de retornar el Resource ((new DocumentShareResource($data))->response()->setStatusCode(201)). Sigue usando el Resource, pero pierde la envoltura/serializacion estandar. | Retornar (new DocumentShareResource($data))->response()->setStatusCode(201) en lugar de armar el array a mano. |
| LOW | R8 | `app/Http/Controllers/Api/ReviewController.php` | 57,77 | approve()/reject() envuelven el Resource con response()->json(['data' => new DocumentResource($updated)]). El doble envoltorio ['data' => ...] mas el wrapper por defecto del Resource puede duplicar la clave data; ademas no usa el patron canonico ->response(). | Retornar new DocumentResource($updated) directamente (o ->response()) y dejar que el Resource gestione la envoltura. |
| LOW | R1 | `app/Http/Controllers/Api/ThemeImageController.php` | 23 | store() lee $request->input('url') en vez de $request->validated('url'). El campo 'url' SI esta validado en StoreThemeImageRequest (regla 'url'), por lo que no hay riesgo de inyeccion, pero rompe la convencion del proyecto de usar siempre validated(). | Usar $request->validated('url') para mantener la convencion (NUNCA $request->input/all). |
| LOW | R1 | `app/Http/Controllers/Api/ThemeImageController.php` | 12 | ThemeImageController no extiende App\Http\Controllers\Controller (a diferencia del resto de controladores del lote). Funciona porque no usa $this->authorize() (la autorizacion ocurre en otra capa), pero es inconsistente con la base comun. | Extender Controller para uniformidad, o documentar explicitamente la excepcion. |
| LOW | R7 | `app/Http/Requests/Documents/ListDocumentsRequest.php` | 55-74 | toFilterDto() construye el DTO leyendo $this->input('process_id'), $this->input('status'), etc. en vez de $this->validated(). Los campos estan declarados en filterRules() y se validan, pero leer via input() omite el conjunto validado (convencion del proyecto: usar validated()). | Construir el DTO desde $this->validated() (o this->safe()) en lugar de $this->input(). |
| LOW | R7 | `app/Http/Requests/Themes/StoreThemeImageRequest.php` | 24-27 | La regla `url` acepta cualquier URL remota (`['required_without:file','string','url','max:2048']`) sin restriccion de esquema/host. Si el Service descarga esa URL puede haber riesgo SSRF; no se puede confirmar el consumo desde este archivo. StoreCoverImageRequest (mismo dominio) explicitamente solo permite archivo por reduccion de superficie. Se reporta como nota: validar el consumo aguas abajo. | Restringir el esquema a https y/o validar host permitido, o eliminar la opcion de URL remota como hace StoreCoverImageRequest si no se descarga de forma segura en el Service. |
| LOW | R8 | `app/Http/Resources/TemplateReviewersSyncMessageResource.php` | 18 | El Resource envuelve un array crudo ($this->resource['message']) en lugar de un DTO. Es un mensaje trivial, pero el resto de Resources del proyecto reciben DTOs tipados; este recibe array asociativo sin tipo. | Opcional: usar un DTO/Value object simple para el mensaje, o mantenerlo dado que es solo un wrapper de mensaje sin datos de dominio. |
| LOW | R8 | `app/Http/Resources/TemplateVersionSummaryResource.php` | 39 | El Resource resuelve nombres de usuario (ResolvesUserNames -> resolveUserNameById) y parsea el snapshot (TemplateVersionSnapshotParser) dentro de toArray(). Lógica de resolución que idealmente vive en el Service/DTO, no en el formateo de respuesta. | Exponer author_name, published_by_name y reviewer_names ya resueltos en el DTO desde el Service; el Resource solo mapea. |
| LOW | R8 | `app/Http/Resources/UserDirectoryResource.php` | 29 | El Resource recibe un array asociativo crudo emitido por el repositorio en lugar de un DTO (a diferencia del resto de Resources del lote que reciben DTOs). Funcionalmente correcto pero rompe la consistencia con R3/R8 del proyecto. | Considerar un UserDirectoryDto con fromArray/fromModel para uniformar el contrato y el tipado. |
| LOW | R5 | `app/Models/TeamMember.php` | 17 | Modelo sin $fillable ni $guarded declarado. Es una vista FDW de solo lectura (documentado), por lo que no hay escritura, pero la ausencia explícita de $guarded deja el modelo sin protección de mass-assignment si en algún futuro se usa para escritura. | Declarar protected $guarded = ['*']; o protected $fillable = []; explícito para dejar constancia de que es read-only y evitar mass-assignment accidental. |
| LOW | R5 | `app/Models/UserCourseModule.php` | 17 | Modelo de vista FDW read-only sin $fillable/$guarded explícito. Coherente con UserStudy/UserStudyType/TeamMember; misma observación de protección de mass-assignment. | Declarar $guarded = ['*'] explícito para uniformar la marca de read-only en los modelos FDW. |
| LOW | R5 | `app/Models/UserFdw.php` | 15 | Modelo de vista FDW de solo lectura sin relaciones declaradas y con $fillable vacío. Anémico por diseño (read-only), pero carece de scopes/helpers que faciliten el filtrado obligatorio por usuario que menciona su propio docblock. | Opcionalmente añadir un scope (p.ej. scopeForUser) para encapsular el filtrado por usuario que el docblock exige, en vez de depender de que cada repo lo aplique. |
| LOW | R8 | `app/Notifications/Rules/PendingValidationsThresholdRule.php` | 42-54 | Construye el payload de notificacion como array asociativo crudo (`params`) en lugar de un DTO. Aceptable para la firma del publisher shared, pero es estructura sin tipar. No bloqueante. | Opcional: tipar el payload con un value object si el publisher lo soportara; de lo contrario dejar como esta. |
| LOW | R2 | `app/Services/DashboardService.php` | 21-37 | buildForUser() devuelve un array anidado crudo (stats + inboxes) en vez de un DTO. Es un agregado de dashboard (sin entidad unica), consistente con la excepcion de dashboard ya documentada en el proyecto, pero estrictamente R2 pide DTO/coleccion de DTOs. | Envolver en un DashboardDto (con sub-DTOs para stats e inbox items) para tipar la salida; baja prioridad por ser read-model agregado. |
| LOW | R2 | `app/Services/DocumentShareService.php` | 19-57 | upsertDocumentShare devuelve un array asociativo crudo `array{user_id, permission, granted_by}` en lugar de un DTO. Las shares no tienen entidad/DTO propio, pero R2 prefiere DTO sobre array asociativo para la salida de un Service. | Crear un DocumentShareDto readonly y devolverlo, o documentar la excepcion explicitamente como en UserFavoriteServiceInterface (Excepcion Bx). |
| LOW | R2 | `app/Services/TeamAuthorizationService.php` | 46-59 | getVisibleTeams()/getVisibleTeam() devuelven arrays crudos `array{id,name,is_department}` en lugar de DTOs. Team es un read-model FDW cross-app sin entidad/DTO local; deviacion consistente y de bajo impacto. | Si se quiere homogeneidad, introducir un TeamReadDto compartido; opcional dado que es directorio read-only FDW. |
| LOW | R2 | `app/Services/TeamReadService.php` | 19-44 | listVisibleTeamsForUser()/findVisibleTeamByIdForUser()/embeddableTeam() devuelven arrays crudos en lugar de DTOs (mismo read-model FDW de Teams que TeamAuthorizationService). | Mismo TeamReadDto compartido si se decide tipar; opcional. |
| LOW | R2 | `app/Services/ThemeImageService.php` | 30-91 | ingestFromUrl() (y upload() heredado de MediaUploadService) devuelve `array{src,uuid}` crudo. Es payload de almacenamiento de media, no una entidad de dominio; deviacion menor de R2. | Opcional: introducir un MediaUploadResultDto readonly para tipar src/uuid en CoverImageService/ThemeImageService/MediaUploadService. |
| LOW | R2 | `app/Services/UserDirectoryService.php` | 24-53 | searchUsers(), searchTemplateReviewerCandidates() y searchDocumentReviewerCandidates() devuelven array sin tipar (passthrough directo de filas del repositorio) en vez de una coleccion de DTOs. R2 prefiere colecciones de DTOs. Bajo impacto: es un passthrough fino sin logica de negocio que transforme la forma; el repositorio ya devuelve filas estructuradas. | Definir un ReviewerCandidateDto / DirectoryUserDto y mapear las filas del repositorio a una list<Dto> en el Service para fijar el contrato de salida. Opcional dado el caracter de passthrough. |

## Archivos revisados (415)

| Archivo | Capa | Cumple |
|---|---|---|
| `app/Console/Commands/EvaluateNotificationRulesCommand.php` | Other | ✅ |
| `app/Console/Commands/PdfPocCommand.php` | Other | ✅ |
| `app/Console/Commands/RepairMarkdownBlocks.php` | Other | ✅ |
| `app/Console/Commands/SeedRuleData.php` | Other | ✅ |
| `app/Constants/DocumentConstants.php` | Other | ✅ |
| `app/DTOs/AnchoredComment/AnchoredCommentDto.php` | DTO | ✅ |
| `app/DTOs/Comments/CommentableResource.php` | DTO | ✅ |
| `app/DTOs/Comments/CommentDto.php` | DTO | ✅ |
| `app/DTOs/Documents/ApplyTemplateMigrationDto.php` | DTO | ✅ |
| `app/DTOs/Documents/BlockDisplayDto.php` | DTO | ✅ |
| `app/DTOs/Documents/BlockUpdateDto.php` | DTO | ✅ |
| `app/DTOs/Documents/CreateDocumentDto.php` | DTO | ✅ |
| `app/DTOs/Documents/CreateDocumentSnapshotDto.php` | DTO | ✅ |
| `app/DTOs/Documents/CreationOptionDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DeleteDocumentBlockDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentBlockPayloadDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentFilterDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentMigrationPayloadDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentRenderDataDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentReviewDto.php` | DTO | ✅ |
| `app/DTOs/Documents/DocumentVersionSnapshotDto.php` | DTO | ✅ |
| `app/DTOs/Documents/ReviewerCandidateDto.php` | DTO | ✅ |
| `app/DTOs/Documents/ReviewerPoolDto.php` | DTO | ✅ |
| `app/DTOs/Documents/TemplateContextDto.php` | DTO | ✅ |
| `app/DTOs/Documents/TemplateVersionStatusDto.php` | DTO | ✅ |
| `app/DTOs/Documents/UpdateDocumentBlockDto.php` | DTO | ✅ |
| `app/DTOs/Documents/UpdateDocumentDto.php` | DTO | ✅ |
| `app/DTOs/Media/MediaDto.php` | DTO | ✅ |
| `app/DTOs/Processes/CreateProcessDto.php` | DTO | ✅ |
| `app/DTOs/Processes/ProcessDeletionPreviewDto.php` | DTO | ✅ |
| `app/DTOs/Processes/ProcessDto.php` | DTO | ✅ |
| `app/DTOs/Processes/UpdateProcessDto.php` | DTO | ✅ |
| `app/DTOs/Refs/UserRefDto.php` | DTO | ✅ |
| `app/DTOs/TemplateBlocks/BulkUpdateTemplateBlocksDto.php` | DTO | ✅ |
| `app/DTOs/TemplateBlocks/TemplateBlockDto.php` | DTO | ✅ |
| `app/DTOs/TemplateBlocks/TemplateBlockPayloadDto.php` | DTO | ✅ |
| `app/DTOs/TemplateBlocks/UpdateTemplateBlockDto.php` | DTO | ✅ |
| `app/DTOs/Templates/CreateTemplateDto.php` | DTO | ✅ |
| `app/DTOs/Templates/FilterTemplatesDto.php` | DTO | ✅ |
| `app/DTOs/Templates/SyncUsersDto.php` | DTO | ✅ |
| `app/DTOs/Templates/TemplateDto.php` | DTO | ✅ |
| `app/DTOs/Templates/TemplateFilterDto.php` | DTO | ✅ |
| `app/DTOs/Templates/TemplateRenderDto.php` | DTO | ✅ |
| `app/DTOs/Templates/UpdateTemplateDto.php` | DTO | ✅ |
| `app/DTOs/Themes/CloneThemeDto.php` | DTO | ✅ |
| `app/DTOs/Themes/CreateThemeDto.php` | DTO | ✅ |
| `app/DTOs/Themes/ThemeDto.php` | DTO | ✅ |
| `app/DTOs/Themes/ThemeResolvedDto.php` | DTO | ✅ |
| `app/DTOs/Themes/UpdateThemeDto.php` | DTO | ✅ |
| `app/DTOs/Users/JwtProfileDto.php` | DTO | ✅ |
| `app/DTOs/Users/ReviewerAcademicAssignmentScope.php` | DTO | ✅ |
| `app/DTOs/Users/ReviewerCandidateFilterDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/DocumentVersionDetailDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/DocumentVersionDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/DocumentVersionSummaryDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/EntityVersionDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/EntityVersionSnapshotDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/VersionBlockLayerDto.php` | DTO | ✅ |
| `app/DTOs/Versioning/WorkingRevisionConflictDto.php` | DTO | ✅ |
| `app/Enums/BlockState.php` | Other | ✅ |
| `app/Enums/BlockType.php` | Other | ✅ |
| `app/Enums/TemplateVisibilityLevel.php` | Other | ✅ |
| `app/Enums/ThemeStatus.php` | Other | ✅ |
| `app/Events/BlockCommentCreated.php` | Other | ✅ |
| `app/Events/BlockCommentDeleted.php` | Other | ✅ |
| `app/Events/BlockCommentMarkedRead.php` | Other | ✅ |
| `app/Events/BlockCommentsMarkedRead.php` | Other | ✅ |
| `app/Events/BlockCommentUpdated.php` | Other | ✅ |
| `app/Events/DocumentDownloaded.php` | Other | ✅ |
| `app/Events/DocumentReviewApproved.php` | Other | ✅ |
| `app/Events/DocumentStateChanged.php` | Other | ✅ |
| `app/Events/DocumentSubmittedForReview.php` | Other | ✅ |
| `app/Events/OwnershipTransferred.php` | Other | ✅ |
| `app/Events/SodViolationDetected.php` | Other | ✅ |
| `app/Events/TemplateBlockCreated.php` | Other | ✅ |
| `app/Events/TemplateBlockDeleted.php` | Other | ✅ |
| `app/Events/TemplateBlocksReordered.php` | Other | ✅ |
| `app/Events/TemplateBlockStateChanged.php` | Other | ✅ |
| `app/Events/TemplateDownloaded.php` | Other | ✅ |
| `app/Events/TemplateReviewApproved.php` | Other | ✅ |
| `app/Events/TemplateStateChanged.php` | Other | ✅ |
| `app/Events/TemplateSubmittedForReview.php` | Other | ✅ |
| `app/Exceptions/ResourceNotFoundException.php` | Other | ✅ |
| `app/Http/Concerns/AttachesCanCloneMeta.php` | Other | ✅ |
| `app/Http/Concerns/AttachesDocumentCanCloneMeta.php` | Other | ✅ |
| `app/Http/Concerns/AuthorizesTemplateForBlocks.php` | Other | ✅ |
| `app/Http/Concerns/SanitizesBlockContent.php` | Other | ✅ |
| `app/Http/Concerns/ValidatesOptionalProcessContext.php` | Other | ✅ |
| `app/Http/Controllers/Api/AnchoredCommentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/CommentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/CoverImageController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DashboardController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentBlockController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentDocxController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentExportController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentOptionsController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentPreviewController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentShareController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentStateController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/DocumentVersionController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/FavoriteController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/HealthCheckController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/MediaController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ProcessController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ReviewController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplateBlockBulkController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplateBlockController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplateController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplatePreviewController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplateReviewersController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplateStateController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/TemplateVersionController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ThemeController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ThemeFontController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ThemeImageController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ThemePreviewController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/UserController.php` | Controller | ❌ |
| `app/Http/Controllers/Controller.php` | Controller | ✅ |
| `app/Http/Requests/AnchoredCommentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Comments/MarkBlockCommentsReadRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Comments/StoreCommentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Comments/UpdateCommentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Concerns/ParsesFavoriteIds.php` | Other | ✅ |
| `app/Http/Requests/Concerns/ValidatesSubmissionChangelog.php` | Other | ✅ |
| `app/Http/Requests/Documents/ApplyTemplateMigrationRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/ApproveDocumentReviewRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/CloneDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/Concerns/ResolvesDocumentForAuthorization.php` | Other | ✅ |
| `app/Http/Requests/Documents/DelegateDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/DestroyDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/DocumentCreateFromModuleRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/DocumentCreationOptionsRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/IndexDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/ListDocumentsRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/PublishDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/RejectDocumentReviewRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/ShowDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/StartNewDocumentRevisionRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/StoreDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/StoreDocumentShareRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/SubmitDocumentForReviewRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/UpdateDocumentBlockRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Documents/UpdateDocumentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Processes/DestroyProcessRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Processes/IndexProcessRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Processes/ShowProcessRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Processes/StoreProcessRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Processes/UpdateProcessRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/StoreMediaRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/TemplateBlocks/BulkUpdateTemplateBlockRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/TemplateBlocks/Concerns/ResolvesTemplateForBlockAuthorization.php` | Other | ✅ |
| `app/Http/Requests/TemplateBlocks/ReorderTemplateBlocksRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/TemplateBlocks/StoreTemplateBlockRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/TemplateBlocks/UpdateTemplateBlockRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/CloneTemplateRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/Concerns/ResolvesTemplateForAuthorization.php` | Other | ✅ |
| `app/Http/Requests/Templates/IndexTemplateRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/ListTemplatesRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/PublishTemplateRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/StartNewTemplateRevisionRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/StoreCoverImageRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/StoreTemplateRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/SubmitTemplateForReviewRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/SyncTemplateDocumentReviewersRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/SyncTemplateUsersRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Templates/UpdateTemplateRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Themes/CloneThemeRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Themes/Concerns/SanitizesThemeLayout.php` | Other | ✅ |
| `app/Http/Requests/Themes/IndexThemeRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Themes/StoreThemeImageRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Themes/StoreThemeRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Themes/UpdateThemeRequest.php` | FormRequest | ✅ |
| `app/Http/Resources/AnchoredCommentResource.php` | Resource | ✅ |
| `app/Http/Resources/CommentResource.php` | Resource | ✅ |
| `app/Http/Resources/Concerns/ResolvesUserNames.php` | Other | ✅ |
| `app/Http/Resources/DashboardResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentBlockResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentCreateFromModuleResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentCreationOptionsResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentMigrationPayloadResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentReviewResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentShareResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentTemplateVersionStatusResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentVersionResource.php` | Resource | ✅ |
| `app/Http/Resources/DocumentWithBlocksResource.php` | Resource | ✅ |
| `app/Http/Resources/MediaResource.php` | Resource | ✅ |
| `app/Http/Resources/ProcessDeletionPreviewResource.php` | Resource | ✅ |
| `app/Http/Resources/ProcessResource.php` | Resource | ✅ |
| `app/Http/Resources/ReviewerCandidateResource.php` | Resource | ✅ |
| `app/Http/Resources/ReviewerPoolResource.php` | Resource | ✅ |
| `app/Http/Resources/TemplateBlockResource.php` | Resource | ✅ |
| `app/Http/Resources/TemplateResource.php` | Resource | ✅ |
| `app/Http/Resources/TemplateReviewersSyncMessageResource.php` | Resource | ✅ |
| `app/Http/Resources/TemplateVersionResource.php` | Resource | ✅ |
| `app/Http/Resources/TemplateVersionSummaryResource.php` | Resource | ✅ |
| `app/Http/Resources/ThemeResource.php` | Resource | ✅ |
| `app/Http/Resources/UserDirectoryResource.php` | Resource | ✅ |
| `app/Listeners/RecordSegregationOfDutiesDenial.php` | Other | ✅ |
| `app/Models/AnchoredComment.php` | Model | ✅ |
| `app/Models/BlockVersion.php` | Model | ✅ |
| `app/Models/Comment.php` | Model | ✅ |
| `app/Models/CommentEdit.php` | Model | ✅ |
| `app/Models/CommentRead.php` | Model | ✅ |
| `app/Models/Concerns/HasAcademicOverlapScope.php` | Other | ✅ |
| `app/Models/Concerns/HasCommentingStatus.php` | Other | ✅ |
| `app/Models/Concerns/HasEntityVersionHead.php` | Other | ✅ |
| `app/Models/Concerns/PurgesBlockComments.php` | Other | ✅ |
| `app/Models/CourseModule.php` | Model | ✅ |
| `app/Models/Document.php` | Model | ✅ |
| `app/Models/DocumentBlock.php` | Model | ✅ |
| `app/Models/DocumentReview.php` | Model | ✅ |
| `app/Models/DocumentShare.php` | Model | ✅ |
| `app/Models/DocumentVersion.php` | Model | ✅ |
| `app/Models/DocumentVersionBlockLayer.php` | Model | ✅ |
| `app/Models/EntityVersion.php` | Model | ✅ |
| `app/Models/JwtUser.php` | Model | ✅ |
| `app/Models/NotificationRule.php` | Model | ✅ |
| `app/Models/NotificationRuleRun.php` | Model | ✅ |
| `app/Models/Permission.php` | Model | ✅ |
| `app/Models/Process.php` | Model | ✅ |
| `app/Models/Study.php` | Model | ✅ |
| `app/Models/StudyType.php` | Model | ✅ |
| `app/Models/Team.php` | Model | ✅ |
| `app/Models/TeamMember.php` | Model | ✅ |
| `app/Models/Template.php` | Model | ✅ |
| `app/Models/TemplateBlock.php` | Model | ✅ |
| `app/Models/TemplateDocumentReviewer.php` | Model | ✅ |
| `app/Models/TemplateReviewer.php` | Model | ✅ |
| `app/Models/TemplateVersionBlockLayer.php` | Model | ✅ |
| `app/Models/Theme.php` | Model | ✅ |
| `app/Models/User.php` | Model | ✅ |
| `app/Models/UserCourseModule.php` | Model | ✅ |
| `app/Models/UserFavoriteDocument.php` | Model | ✅ |
| `app/Models/UserFavoriteTemplate.php` | Model | ✅ |
| `app/Models/UserFdw.php` | Model | ✅ |
| `app/Models/UserStudy.php` | Model | ✅ |
| `app/Models/UserStudyType.php` | Model | ✅ |
| `app/Notifications/Rules/PendingValidationsThresholdRule.php` | Other | ✅ |
| `app/Notifications/Rules/ScheduledNotificationRule.php` | Other | ✅ |
| `app/Notifications/Rules/ValidationDeadlineApproachingRule.php` | Other | ✅ |
| `app/Observers/BlockVersionObserver.php` | Other | ✅ |
| `app/Observers/DocumentBlockObserver.php` | Other | ✅ |
| `app/Observers/DocumentObserver.php` | Other | ✅ |
| `app/Observers/DocumentReviewObserver.php` | Other | ✅ |
| `app/Observers/DocumentShareObserver.php` | Other | ✅ |
| `app/Observers/DocumentVersionBlockLayerObserver.php` | Other | ✅ |
| `app/Observers/DocumentVersionObserver.php` | Other | ✅ |
| `app/Observers/EntityVersionObserver.php` | Other | ✅ |
| `app/Observers/ProcessObserver.php` | Other | ✅ |
| `app/Observers/TemplateBlockObserver.php` | Other | ✅ |
| `app/Observers/TemplateObserver.php` | Other | ✅ |
| `app/Observers/TemplateReviewerObserver.php` | Other | ✅ |
| `app/Observers/TemplateVersionBlockLayerObserver.php` | Other | ✅ |
| `app/Observers/UserFavoriteDocumentObserver.php` | Other | ✅ |
| `app/Observers/UserFavoriteTemplateObserver.php` | Other | ✅ |
| `app/Policies/BlockPolicy.php` | Policy | ✅ |
| `app/Policies/CommentPolicy.php` | Policy | ✅ |
| `app/Policies/DocumentBlockPolicy.php` | Policy | ✅ |
| `app/Policies/DocumentPolicy.php` | Policy | ✅ |
| `app/Policies/ProcessPolicy.php` | Policy | ✅ |
| `app/Policies/TemplateBlockPolicy.php` | Policy | ✅ |
| `app/Policies/TemplatePolicy.php` | Policy | ✅ |
| `app/Policies/ThemePolicy.php` | Policy | ✅ |
| `app/Providers/AppServiceProvider.php` | Other | ✅ |
| `app/Repositories/Contracts/AcademicHierarchyRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/AnchoredCommentRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/CommentReadRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/CommentRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/DocumentBlockRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/DocumentRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/DocumentVersionBlockLayerRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/DocumentVersionRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/EntityVersionRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/ProcessRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/ResolvedPermissionReaderInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/TeamReadRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/TemplateBlockRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/TemplateRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/TemplateReviewerRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/TemplateVersionBlockLayerRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/TemplateVersionRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/ThemeRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/UserDirectoryRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/UserFavoriteRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/UserProfileRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Eloquent/AbstractVersionableEntityRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/AbstractVersionBlockLayerRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/AcademicHierarchyRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/AnchoredCommentRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/CommentReadRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/CommentRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/DocumentBlockRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/DocumentRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/DocumentVersionBlockLayerRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/DocumentVersionRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/EntityVersionRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/ProcessRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/ResolvedPermissionReader.php` | Repository | ✅ |
| `app/Repositories/Eloquent/TeamReadRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/TemplateBlockRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/TemplateRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/TemplateReviewerRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/TemplateVersionBlockLayerRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/TemplateVersionRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/ThemeRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/UserDirectoryRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/UserFavoriteRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/UserProfileRepository.php` | Repository | ✅ |
| `app/Repositories/Resolvers/FdwUserProfileResolver.php` | Other | ✅ |
| `app/Repositories/Resolvers/PolymorphicResourceResolver.php` | Repository | ✅ |
| `app/Services/AnchoredCommentService.php` | Service | ✅ |
| `app/Services/ApiTeamEmbedService.php` | Service | ❌ |
| `app/Services/CommentService.php` | Service | ✅ |
| `app/Services/Concerns/AbstractBlockLayerResolver.php` | Service | ✅ |
| `app/Services/Concerns/AbstractBlockLayerWriter.php` | Service | ✅ |
| `app/Services/Concerns/BlockRenderSupport.php` | Service | ✅ |
| `app/Services/Concerns/NotifiesOwner.php` | Service | ✅ |
| `app/Services/Contracts/AnchoredCommentServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ApiTeamEmbedServiceInterface.php` | Service | ❌ |
| `app/Services/Contracts/CommentServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/DashboardServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/DocumentExportServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/DocumentPdfServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/DocumentRenderServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/DocumentServiceInterface.php` | Service | ❌ |
| `app/Services/Contracts/EntityVersionLifecycleServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ProcessServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/SnapshotServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/TeamReadServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/TemplateBlockServiceInterface.php` | Service | ❌ |
| `app/Services/Contracts/TemplatePdfServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/TemplateRenderServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/TemplateServiceInterface.php` | Service | ❌ |
| `app/Services/Contracts/ThemeImageServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ThemePdfServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ThemeRenderServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ThemeServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/UserDirectoryServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/UserFavoriteServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/UserProfileServiceInterface.php` | Service | ✅ |
| `app/Services/CoverImageService.php` | Service | ✅ |
| `app/Services/CoverRenderService.php` | Service | ✅ |
| `app/Services/DashboardService.php` | Service | ✅ |
| `app/Services/DocumentBlockService.php` | Service | ✅ |
| `app/Services/DocumentDocxExportService.php` | Service | ✅ |
| `app/Services/DocumentExportService.php` | Service | ✅ |
| `app/Services/DocumentMigrationBlockDiffer.php` | Service | ✅ |
| `app/Services/DocumentMigrationPayloadResolver.php` | Service | ✅ |
| `app/Services/DocumentPdfService.php` | Service | ✅ |
| `app/Services/DocumentRenderService.php` | Service | ✅ |
| `app/Services/DocumentReviewService.php` | Service | ✅ |
| `app/Services/DocumentService.php` | Service | ✅ |
| `app/Services/DocumentShareService.php` | Service | ✅ |
| `app/Services/DocumentStateService.php` | Service | ✅ |
| `app/Services/DocumentTemplateVersionNumberResolver.php` | Service | ✅ |
| `app/Services/DocumentVersionBlockLayerResolver.php` | Service | ✅ |
| `app/Services/DocumentVersionBlockLayerWriter.php` | Service | ✅ |
| `app/Services/DocumentVersionService.php` | Service | ✅ |
| `app/Services/EntityVersionDestroyService.php` | Service | ✅ |
| `app/Services/EntityVersionLifecycleService.php` | Service | ✅ |
| `app/Services/EntityVersionReconstructionService.php` | Service | ✅ |
| `app/Services/MediaAssetResolver.php` | Service | ✅ |
| `app/Services/MediaService.php` | Service | ✅ |
| `app/Services/MediaUploadService.php` | Service | ✅ |
| `app/Services/ProcessService.php` | Service | ✅ |
| `app/Services/ReviewerAcademicScopeResolver.php` | Service | ✅ |
| `app/Services/SnapshotService.php` | Service | ✅ |
| `app/Services/TeamAuthorizationService.php` | Service | ✅ |
| `app/Services/TeamReadService.php` | Service | ✅ |
| `app/Services/TemplateBlockService.php` | Service | ✅ |
| `app/Services/TemplateContextResolver.php` | Service | ✅ |
| `app/Services/TemplatePdfService.php` | Service | ✅ |
| `app/Services/TemplatePublishingService.php` | Service | ✅ |
| `app/Services/TemplateRenderService.php` | Service | ✅ |
| `app/Services/TemplateReviewerAssignmentService.php` | Service | ✅ |
| `app/Services/TemplateReviewService.php` | Service | ✅ |
| `app/Services/TemplateService.php` | Service | ✅ |
| `app/Services/TemplateVersionBlockLayerResolver.php` | Service | ✅ |
| `app/Services/TemplateVersionBlockLayerWriter.php` | Service | ✅ |
| `app/Services/ThemeFontResolver.php` | Service | ✅ |
| `app/Services/ThemeImageService.php` | Service | ✅ |
| `app/Services/ThemePdfService.php` | Service | ✅ |
| `app/Services/ThemeRenderService.php` | Service | ✅ |
| `app/Services/ThemeService.php` | Service | ✅ |
| `app/Services/ThemeStateTransitions.php` | Other | ✅ |
| `app/Services/TocBuilderService.php` | Service | ✅ |
| `app/Services/UserDirectoryService.php` | Service | ✅ |
| `app/Services/UserFavoriteService.php` | Service | ✅ |
| `app/Services/UserProfileService.php` | Service | ✅ |
| `app/Support/AcademicScopeContext.php` | DTO | ✅ |
| `app/Support/AcademicScopeNormalizer.php` | Other | ✅ |
| `app/Support/ApiEmbeddedTeamResponse.php` | Other | ✅ |
| `app/Support/BlockLayerPayloadComparator.php` | Other | ✅ |
| `app/Support/CloneDeadlinePolicy.php` | Other | ✅ |
| `app/Support/CommentAuditPayload.php` | Other | ✅ |
| `app/Support/DocumentHeadSnapshot.php` | Other | ✅ |
| `app/Support/DocumentReviewModeResolver.php` | Other | ✅ |
| `app/Support/IsoTimestamp.php` | Other | ✅ |
| `app/Support/MarkdownBlockRepair.php` | Other | ✅ |
| `app/Support/MarkdownDetect.php` | Other | ✅ |
| `app/Support/PreviewHeaders.php` | Other | ✅ |
| `app/Support/ReviewValidationNotificationRecipients.php` | Other | ✅ |
| `app/Support/ReviewValidationNotifier.php` | Other | ✅ |
| `app/Support/TemplateBlockDescriptionNormalizer.php` | Other | ✅ |
| `app/Support/TemplateHeadSnapshot.php` | Other | ✅ |
| `app/Support/TemplateVersionSnapshotParser.php` | Other | ✅ |
| `app/Support/ThemeMediaUrl.php` | Other | ✅ |
| `app/Support/TiptapContentSemantics.php` | Other | ✅ |
| `app/Support/VersionSubmissionChangelog.php` | Other | ✅ |
| `app/Support/VersionSubmissionCycles.php` | Other | ✅ |
| `app/Support/WeasyPrintRunner.php` | Other | ✅ |
| `app/Support/WorkingRevisionConflictResolver.php` | Other | ✅ |

