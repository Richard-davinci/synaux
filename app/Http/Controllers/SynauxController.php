<?php

namespace App\Http\Controllers;

use App\Services\SynauxBudgets;
use App\Services\SynauxCatalog;
use App\Services\SynauxRequests;
use App\Services\SynauxWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// Conecta las pantallas con datos persistentes y autoriza cada cambio en el servidor.
class SynauxController extends Controller
{
    public function __construct(private SynauxCatalog $catalog, private SynauxWorkspace $workspace, private SynauxRequests $requests) {}

    // Prepara una pantalla pública y agrega únicamente los datos que su recorrido necesita.
    public function publicPage(Request $request, string $screen = 'servicios', ?string $id = null)
    {
        $data = ['screen' => $screen, 'services' => $this->catalog->services()];
        $ownProfile = $request->user() ? DB::table('professional_profiles')->where('user_id', $request->user()->id)->first() : null;
        $data['profile'] = $ownProfile ? $this->workspace->profile($ownProfile) : null;
        if ($screen === 'servicio') {
            $data['selected'] = $this->catalog->service($id);
        }
        if (in_array($screen, ['portfolio', 'trabajo'], true)) {
            $data['works'] = $this->workspace->works();
            $data['selected'] = $id ? collect($data['works'])->firstWhere('id', (int) $id) ?? abort(404) : null;
        }
        if (in_array($screen, ['muro', 'oportunidad'], true)) {
            $data['opportunities'] = $this->workspace->requests(null, public: true);
            $data['selected'] = $id ? collect($data['opportunities'])->firstWhere('id', (int) $id) : null;
            if ($id && ! $data['selected']) {
                // Una invitación aceptada permite ver el contexto privado, sin revelar respuestas ni contacto.
                $record = DB::table('project_requests')->where('id', $id)->firstOrFail();
                $profile = $request->user() ? DB::table('professional_profiles')->where('user_id', $request->user()->id)->first() : null;
                $invited = $profile && DB::table('project_request_invitations')->where('project_request_id', $id)->where('professional_profile_id', $profile->id)->where('status', 'accepted')->exists();
                abort_unless($request->user() && ($record->user_id === $request->user()->id || $invited), 404);
                $data['selected'] = collect($this->workspace->requests($record->user_id))->firstWhere('id', (int) $id);
                unset($data['selected']['answers'], $data['selected']['estimate_id'], $data['selected']['user_id']);
            }
            if ($id) {
                // Estos indicadores pertenecen al visitante actual; no publican la identidad del cliente.
                $data['selected']['is_owner'] = $request->user() && DB::table('project_requests')->where('id', $id)->where('user_id', $request->user()->id)->exists();
                $data['existingProposal'] = $ownProfile && DB::table('proposals')->where('project_request_id', $id)->where('professional_profile_id', $ownProfile->id)->exists();
            }
        }
        if (in_array($screen, ['profesionales', 'perfil-profesional'], true)) {
            $data['professionals'] = $this->workspace->professionals();
            $data['selected'] = $id ? collect($data['professionals'])->firstWhere('id', (int) $id) ?? abort(404) : null;
            if ($id) {
                $data['works'] = collect($this->workspace->works())->where('professional_profile_id', (int) $id)->values();
                $data['requests'] = $request->user() ? $this->workspace->requests($request->user()->id) : [];
            }
        }
        if ($screen === 'estimador') {
            $slug = (string) $request->query('servicio', 'sitio-institucional');
            $service = $this->catalog->service($slug);
            $data['questions'] = $this->catalog->questions($service['database_id']);
            $data['selected'] = $service;
            $data['pending'] = $request->session()->get('pending_estimate');
            $data['step'] = max(1, min(4, (int) $request->query('paso', 1)));
        }
        if ($screen === 'restablecer') {
            $data['token'] = $id;
            $data['email'] = (string) $request->query('email');
        }

        return Inertia::render($screen === 'servicios' ? 'Catalog' : 'Workspace', $data);
    }

    // Abre una solicitud nueva y recupera la estimación elegida solo si pertenece a la cuenta actual.
    public function newRequest(Request $request)
    {
        $data = $this->privateData($request);
        $data['screen'] = 'publicar';
        $id = (int) $request->query('estimacion', 0);
        $data['selectedEstimate'] = $id ? collect($data['estimates'])->firstWhere('id', $id) ?? abort(404) : null;

        return Inertia::render('Workspace', $data);
    }

    // Valida las respuestas, calcula en el servidor y conserva el resultado en la sesión para continuar.
    public function calculate(Request $request)
    {
        $data = $request->validate(['service' => 'required|string', 'answers' => 'required|array|max:100']);
        $result = $this->catalog->calculate($data['service'], $data['answers']);
        $request->session()->put('pending_estimate', $result);

        return redirect('/estimador?paso=4&servicio='.$data['service']);
    }

    // Valida y registra una consulta; la confirmación corresponde a un registro realmente guardado.
    public function contact(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:254',
            'topic' => 'required|string|max:255', 'message' => 'required|string|min:10|max:10000']);
        DB::table('contact_inquiries')->insert([...$data, 'created_at' => now(), 'updated_at' => now()]);

        return redirect('/contacto?enviado=1')->with('notice', 'Recibimos tu consulta. Quedó guardada para su revisión.');
    }

    // Entrega una imagen solo si el trabajo es público o pertenece a la cuenta que la solicita.
    public function media(Request $request, string $file)
    {
        // Se resuelve un nombre generado por Laravel; la consulta determina si la imagen es pública o propia.
        abort_unless(preg_match('/^[a-zA-Z0-9]+\.(jpg|jpeg|png|webp)$/i', $file), 404);
        $path = '/media/portfolio/'.$file;
        $work = DB::table('portfolio_projects as w')->join('professional_profiles as p', 'p.id', '=', 'w.professional_profile_id')
            ->where(fn ($q) => $q->where('w.cover_path', $path)->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('portfolio_project_images as i')->whereColumn('i.portfolio_project_id', 'w.id')->where('i.image_path', $path)))
            ->select('w.is_public', 'p.active', 'p.user_id')->first();
        abort_unless($work && (($work->is_public && $work->active) || $request->user()?->id === $work->user_id), 404);
        abort_unless(Storage::disk('public')->exists('portfolio/'.$file), 404);

        return Storage::disk('public')->response('portfolio/'.$file, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    // Resuelve el área y su sección; Gestión y Administración exigen el permiso administrativo real.
    public function area(Request $request, string $area, string $section = '')
    {
        $admin = in_array($area, ['gestion', 'admin'], true);
        abort_if($admin && ! $request->user()->is_admin, 403);
        $allowed = [
            'cliente' => ['', 'estimaciones', 'solicitudes', 'presupuestos', 'proyectos', 'publicaciones', 'propuestas', 'invitaciones'],
            'profesional' => ['', 'perfil', 'propuestas', 'invitaciones', 'presupuestos', 'proyectos', 'portfolio'],
            'gestion' => ['', 'solicitudes', 'presupuestos', 'proyectos', 'portfolio', 'contactos'],
            'admin' => ['', 'servicios', 'preguntas', 'componentes', 'reglas', 'tarifario'],
        ];
        abort_unless(isset($allowed[$area]) && in_array($section, $allowed[$area], true), 404);
        $data = $this->privateData($request, $admin);
        $data['screen'] = $area;
        $data['area'] = $area === 'admin' ? 'gestion' : $area;
        $data['section'] = $section;
        if ($area === 'profesional' && in_array($section, ['presupuestos', 'proyectos'], true)) {
            // Distingue los presupuestos emitidos del historial de esta misma persona como cliente.
            $data['quotes'] = array_values(array_filter($data['quotes'], fn ($q) => $q['issuer_id'] === $request->user()->id));
            $data['projects'] = $this->workspace->projects($data['quotes']);
        }
        if ($area === 'admin') {
            $data['administration'] = app(SynauxAdminController::class)->data();
        }
        if ($section === 'contactos') {
            $data['inquiries'] = DB::table('contact_inquiries')->orderByDesc('id')->get();
        }

        return Inertia::render('Workspace', $data);
    }

    // Busca el registro entre los datos autorizados de la cuenta y aplica los permisos del editor.
    public function detail(Request $request, string $kind, int $id)
    {
        $data = $this->privateData($request, (bool) $request->user()->is_admin);
        $source = ['solicitud' => 'requests', 'presupuesto' => 'quotes', 'proyecto' => 'projects', 'estimacion' => 'estimates', 'editor-trabajo' => 'works', 'editor-presupuesto' => 'quotes'];
        abort_unless(isset($source[$kind]), 404);
        $data['selected'] = $id ? collect($data[$source[$kind]])->firstWhere('id', $id) ?? abort(404) : null;
        if ($kind === 'editor-presupuesto' && $id) {
            abort_unless($data['selected']['issuer_id'] === $request->user()->id, 403);
        }
        if ($kind === 'editor-trabajo') {
            $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->first();
            abort_unless($profile, 403, 'Completá tu perfil profesional.');
            if ($id) {
                abort_unless($data['selected']['professional_profile_id'] === $profile->id, 403);
            }
        }
        $data['screen'] = $kind;
        $data['requestId'] = (int) $request->query('solicitud', 0);

        return Inertia::render('Workspace', $data);
    }

    // Reúne las colecciones de la cuenta sin mezclar registros privados de otras personas.
    private function privateData(Request $request, bool $management = false): array
    {
        $id = $request->user()->id;
        $profile = DB::table('professional_profiles')->where('user_id', $id)->first();
        $quotes = $this->workspace->quotes($id, $management);
        // El profesional solo puede presupuestar solicitudes donde fue elegido; no recibe otras solicitudes privadas.
        $chosen = $profile ? DB::table('proposals')->where('professional_profile_id', $profile->id)->where('status', 'accepted')->pluck('project_request_id')->all() : [];
        $budgetRequests = $management ? $this->workspace->requests($id, true) : ($chosen ? $this->workspace->requests(null, true, only: $chosen) : []);

        return ['area' => $management ? 'gestion' : 'cliente', 'services' => $this->catalog->services($management), 'requests' => $this->workspace->requests($id, $management),
            'budgetRequests' => $budgetRequests,
            'quotes' => $quotes, 'projects' => $this->workspace->projects($quotes),
            'proposals' => $this->workspace->proposals($id, $management), 'invitations' => $this->workspace->invitations($id, $management),
            'profile' => $profile ? $this->workspace->profile($profile) : null, 'professionals' => $this->workspace->professionals(),
            'works' => $this->workspace->works($id), 'estimates' => DB::table('estimates')->where('user_id', $id)->orderByDesc('id')->get()->map(fn ($e) => [
                ...((array) $e), 'answers' => json_decode($e->answers_snapshot, true), 'items' => json_decode($e->items_snapshot ?? '[]', true),
            ])->all()];
    }

    // Despacha únicamente las acciones enumeradas; la URL no puede ejecutar un método arbitrario.
    public function action(Request $request, string $action)
    {
        // La lista explícita impide ejecutar métodos arbitrarios desde una URL.
        return match ($action) {
            'guardar-estimacion' => $this->saveEstimate($request),
            'solicitud' => $this->saveRequest($request),
            'estado-solicitud' => $this->requestStatus($request),
            'perfil' => $this->saveProfile($request),
            'propuesta' => $this->saveProposal($request),
            'seleccionar-propuesta' => $this->selectProposal($request),
            'retirar-propuesta' => $this->withdrawProposal($request),
            'invitacion' => $this->invite($request),
            'responder-invitacion' => $this->respondInvitation($request),
            'presupuesto' => redirect('/presupuestos/'.app(SynauxBudgets::class)->save($request))->with('notice', 'Versión guardada.'),
            'decidir-presupuesto' => $this->decide($request),
            'proyecto' => $this->updateProject($request),
            'trabajo' => $this->saveWork($request),
            default => abort(404),
        };
    }

    // Guarda una instantánea del resultado de sesión y evita duplicarla si se repite el envío.
    private function saveEstimate(Request $request)
    {
        $pending = $request->session()->get('pending_estimate');
        abort_unless($pending, 422, 'Primero completá el estimador.');
        // La clave del cálculo evita duplicados por doble clic sin reutilizar una estimación al recalcular.
        $existing = DB::table('estimates')->where('request_key', $pending['key'])->first();
        if ($existing) {
            abort_unless($existing->user_id === $request->user()->id, 403);

            return redirect($request->boolean('to_request') ? '/solicitudes/nueva?estimacion='.$existing->id : '/estimaciones/'.$existing->id);
        }
        $id = DB::table('estimates')->insertGetId(['user_id' => $request->user()->id, 'service_id' => $pending['service']['database_id'],
            'tariff_version_id' => $pending['tariff_id'], 'request_key' => $pending['key'], 'summary' => $pending['service']['name'],
            'status' => $pending['mode'] === 'automatic' ? 'calculated' : 'manual', 'base_min_snapshot' => $pending['base_min'],
            'base_max_snapshot' => $pending['base_max'], 'price_min' => $pending['min'], 'price_max' => $pending['max'],
            'currency_snapshot' => $pending['currency'], 'answers_snapshot' => json_encode($pending['answers']), 'items_snapshot' => json_encode($pending['items']), 'created_at' => now(), 'updated_at' => now()]);

        return redirect($request->boolean('to_request') ? '/solicitudes/nueva?estimacion='.$id : '/estimaciones/'.$id)->with('notice', 'Estimación guardada. Podés crear una solicitud con estas respuestas.');
    }

    // Valida el alcance y guarda una solicitud propia como borrador, privada o pública.
    private function saveRequest(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|integer', 'estimate_id' => 'nullable|integer', 'service_id' => 'required|integer|exists:services,id',
            'title' => 'required|string|min:5|max:255', 'description' => 'required|string|min:10|max:10000',
            'visibility' => ['required', Rule::in(['private', 'public'])], 'category' => 'nullable|string|max:255',
            'skills' => 'nullable|string|max:1000', 'budget_reference' => 'nullable|string|max:255', 'desired_deadline' => 'nullable|string|max:255', 'publish' => 'required|boolean']);
        // Normaliza las habilidades del formulario antes de guardar los datos validados.
        $d['skills'] = $this->tags($d['skills'] ?? '');
        $id = $this->requests->save($request->user()->id, $d);


        return redirect('/solicitudes/'.$id)->with('notice', $d['publish'] ? 'Solicitud enviada.' : 'Borrador guardado.');
    }

    // Comprueba propiedad o administración antes de revisar, cerrar o cancelar una solicitud.
    private function requestStatus(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer', 'status' => ['required', Rule::in(['review', 'closed', 'cancelled'])]]);
        $r = DB::table('project_requests')->where('id', $d['id'])->firstOrFail();
        abort_unless($r->user_id === $request->user()->id || $request->user()->is_admin, 403);
        if ($d['status'] === 'review') {
            abort_unless($request->user()->is_admin && $r->status === 'open', 403);
            DB::table('project_requests')->where('id', $r->id)->update(['reviewed_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('project_requests')->where('id', $r->id)->update(['status' => $d['status'], 'updated_at' => now()]);
        }

        return back()->with('notice', 'Estado actualizado.');
    }

    // Actualiza el perfil profesional de la propia cuenta y normaliza su lista de habilidades.
    private function saveProfile(Request $request)
    {
        $d = $request->validate(['brand_name' => 'required|string|max:255', 'headline' => 'required|string|max:255', 'bio' => 'required|string|max:5000',
            'skills' => 'nullable|string|max:1000', 'location' => 'nullable|string|max:255', 'availability_status' => ['required', Rule::in(['available', 'busy', 'unavailable'])], 'active' => 'required|boolean']);
        $d['skills'] = json_encode($this->tags($d['skills'] ?? ''));
        DB::table('professional_profiles')->updateOrInsert(['user_id' => $request->user()->id], [...$d, 'updated_at' => now()]);

        return redirect('/profesional/perfil')->with('notice', 'Perfil profesional actualizado.');
    }

    // Permite una propuesta de un perfil activo ante una oportunidad abierta o una invitación aceptada.
    private function saveProposal(Request $request)
    {
        $d = $request->validate(['request_id' => 'required|integer', 'message' => 'required|string|min:10|max:10000',
            'estimated_delivery_days' => 'required|integer|min:1|max:3650', 'amount' => 'nullable|numeric|min:0|max:100000000', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])]]);
        $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->where('active', 1)->firstOrFail();
        $r = DB::table('project_requests')->where('id', $d['request_id'])->firstOrFail();
        $invited = DB::table('project_request_invitations')->where('project_request_id', $r->id)->where('professional_profile_id', $profile->id)->where('status', 'accepted')->exists();
        abort_unless($r->status === 'open' && ($r->visibility === 'public' || $invited) && $r->user_id !== $request->user()->id, 403);
        $existing = DB::table('proposals')->where('project_request_id', $r->id)->where('professional_profile_id', $profile->id)->first();
        abort_if($existing, 422, 'Ya presentaste una propuesta para esta solicitud.');
        DB::table('proposals')->insert(['project_request_id' => $r->id, 'professional_profile_id' => $profile->id, 'message' => $d['message'],
            'estimated_delivery_days' => $d['estimated_delivery_days'], 'amount' => $d['amount'] ?? null, 'currency_code' => $d['currency_code'], 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);

        return redirect('/profesional/propuestas')->with('notice', 'Propuesta presentada.');
    }

    // El cliente elige un profesional para presupuestar; esta selección todavía no crea un proyecto.
    private function selectProposal(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer']);
        DB::transaction(function () use ($request, $d) {
            $p = DB::table('proposals')->where('id', $d['id'])->lockForUpdate()->firstOrFail();
            $r = DB::table('project_requests')->where('id', $p->project_request_id)->lockForUpdate()->firstOrFail();
            abort_unless($r->user_id === $request->user()->id && $r->status === 'open' && $p->status === 'submitted', 403);
            abort_if(DB::table('proposals')->where('project_request_id', $r->id)->where('status', 'accepted')->exists(), 422, 'Ya elegiste una propuesta.');
            DB::table('proposals')->where('id', $p->id)->update(['status' => 'accepted', 'updated_at' => now()]);
        });

        return back()->with('notice', 'Profesional elegido para preparar el presupuesto. Esto todavía no crea una contratación.');
    }

    // Permite retirar únicamente una propuesta enviada por el profesional actual.
    private function withdrawProposal(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer']);
        $p = DB::table('proposals')->where('id', $d['id'])->firstOrFail();
        $profile = DB::table('professional_profiles')->where('id', $p->professional_profile_id)->firstOrFail();
        abort_unless($profile->user_id === $request->user()->id && $p->status === 'submitted', 403);
        DB::table('proposals')->where('id', $p->id)->update(['status' => 'withdrawn', 'updated_at' => now()]);

        return back()->with('notice', 'Propuesta retirada.');
    }

    // Invita a un perfil activo a una solicitud propia y evita repetir la misma invitación.
    private function invite(Request $request)
    {
        $d = $request->validate(['request_id' => 'required|integer', 'professional_id' => 'required|integer|exists:professional_profiles,id']);
        $r = DB::table('project_requests')->where('id', $d['request_id'])->where('user_id', $request->user()->id)->where('status', 'open')->firstOrFail();
        $pro = DB::table('professional_profiles')->where('id', $d['professional_id'])->where('active', 1)->firstOrFail();
        abort_if($pro->user_id === $request->user()->id, 422);
        DB::table('project_request_invitations')->insertOrIgnore(['project_request_id' => $r->id, 'professional_profile_id' => $pro->id, 'status' => 'pending', 'created_at' => now()]);

        return redirect('/cliente/invitaciones')->with('notice', 'Invitación guardada para el profesional.');
    }

    // Valida que la respuesta provenga del destinatario de una invitación todavía pendiente.
    private function respondInvitation(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer', 'status' => ['required', Rule::in(['accepted', 'declined'])]]);
        $i = DB::table('project_request_invitations')->where('id', $d['id'])->firstOrFail();
        $p = DB::table('professional_profiles')->where('id', $i->professional_profile_id)->firstOrFail();
        $r = DB::table('project_requests')->where('id', $i->project_request_id)->firstOrFail();
        abort_unless($p->user_id === $request->user()->id && $i->status === 'pending' && $r->status === 'open', 403);
        DB::table('project_request_invitations')->where('id', $i->id)->update(['status' => $d['status']]);

        return back()->with('notice', 'Respuesta guardada.');
    }

    // Procesa una decisión formal vigente y devuelve el siguiente paso del recorrido.
    private function decide(Request $request)
    {
        $id = app(SynauxBudgets::class)->decide($request);

        return redirect($id ? '/proyectos/'.$id : '/cliente/presupuestos')->with('notice', $id ? 'Presupuesto aceptado y proyecto creado.' : 'Presupuesto rechazado.');
    }

    // Valida el avance, fechas y notas; solo el emisor o un administrador puede cambiarlos.
    private function updateProject(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer', 'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'progress' => 'required|integer|min:0|max:100', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'notes' => 'nullable|string|max:10000']);
        $project = DB::table('projects')->where('id', $d['id'])->firstOrFail();
        $quotes = $this->workspace->quotes($request->user()->id, (bool) $request->user()->is_admin);
        $version = collect($quotes)->firstWhere('id', $project->accepted_budget_version_id);
        abort_unless($version && ($request->user()->is_admin || $version['issuer_id'] === $request->user()->id), 403);
        $id = $d['id'];
        unset($d['id']);
        DB::table('projects')->where('id', $id)->update([...$d, 'updated_at' => now()]);

        return back()->with('notice', 'Avance del proyecto actualizado.');
    }

    // Guarda un trabajo del perfil propio y controla formato y tamaño de sus imágenes.
    private function saveWork(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|integer', 'service_id' => 'required|integer|exists:services,id', 'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000', 'category' => 'required|string|max:255', 'challenge' => 'nullable|string|max:10000', 'result' => 'nullable|string|max:10000',
            'year' => 'nullable|integer|min:1990|max:2100', 'is_public' => 'required|boolean', 'live_url' => 'nullable|url:http,https|max:2048',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', 'gallery' => 'nullable|array|max:8', 'gallery.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120']);
        $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->firstOrFail();
        $old = ! empty($d['id']) ? DB::table('portfolio_projects')->where('id', $d['id'])->where('professional_profile_id', $profile->id)->firstOrFail() : null;
        // Los archivos se limitan a imágenes raster; se guardan con nombres generados por Laravel.
        $values = collect($d)->except(['id', 'cover', 'gallery'])->all();
        if ($request->hasFile('cover')) {
            $values['cover_path'] = '/media/'.$request->file('cover')->store('portfolio', 'public');
        }
        $id = DB::transaction(function () use ($request, $profile, $old, $values) {
            if ($old) {
                DB::table('portfolio_projects')->where('id', $old->id)->update([...$values, 'updated_at' => now()]);
                $id = $old->id;
            } else {
                $id = DB::table('portfolio_projects')->insertGetId([...$values, 'professional_profile_id' => $profile->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $order = (DB::table('portfolio_project_images')->where('portfolio_project_id', $id)->max('sort_order') ?? -1) + 1;
            foreach ($request->file('gallery', []) as $image) {
                DB::table('portfolio_project_images')->insert(['portfolio_project_id' => $id, 'image_path' => '/media/'.$image->store('portfolio', 'public'), 'alt_text' => $values['title'], 'sort_order' => $order++]);
            }

            return $id;
        });

        return redirect('/editor/trabajo/'.$id)->with('notice', 'Trabajo guardado.');
    }

    // Convierte un texto separado por comas en una lista sin elementos vacíos ni repetidos.
    private function tags(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $text)))));
    }
}
