<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Install\InstallState;
use App\Support\Install\Installer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

class InstallWizardController extends Controller
{
    public function __construct(
        private readonly InstallState $installState,
        private readonly Installer $installer,
    ) {}

    public function welcome(): View
    {
        return view('install.welcome', $this->sharedViewData('welcome', [
            'requirements' => $this->installState->requirementsReport(),
            'canContinue' => $this->installState->canContinueFromRequirements(),
            'continueRoute' => route($this->installState->nextIncompleteStepRouteName()),
        ]));
    }

    public function database(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenStepUnavailable('database')) {
            return $redirect;
        }

        return view('install.database', $this->sharedViewData('database', [
            'databaseDefaults' => [
                'db_connection' => old('db_connection', $this->installState->databaseConnection()),
                'db_host' => old('db_host', (string) config('database.connections.'.config('database.default').'.host', '127.0.0.1')),
                'db_port' => old('db_port', (string) config('database.connections.'.config('database.default').'.port', '')),
                'db_database' => old('db_database', (string) config('database.connections.'.config('database.default').'.database', '')),
                'db_username' => old('db_username', (string) config('database.connections.'.config('database.default').'.username', '')),
            ],
            'passwordSaved' => $this->installState->databaseConnection() !== 'sqlite'
                && filled((string) config('database.connections.'.config('database.default').'.password', '')),
        ]));
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenStepUnavailable('database')) {
            return $redirect;
        }

        $validated = $this->validateDatabaseRequest($request);

        try {
            $this->installer->saveDatabaseConfiguration($validated);
        } catch (RuntimeException $exception) {
            return back()
                ->withErrors(['database' => $exception->getMessage()])
                ->withInput(Arr::except($request->all(), ['db_password']));
        }

        return redirect()
            ->route('install.core')
            ->with('status', 'Database connection saved and verified.');
    }

    public function core(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenStepUnavailable('core')) {
            return $redirect;
        }

        return view('install.core', $this->sharedViewData('core', [
            'coreResults' => session('install.core_results', []),
            'coreInstalled' => $this->installState->coreInstalled(),
        ]));
    }

    public function installCore(): RedirectResponse
    {
        if ($redirect = $this->redirectWhenStepUnavailable('core')) {
            return $redirect;
        }

        $results = $this->installer->installCore();
        $hasFailure = collect($results)->contains(fn (array $step) => $step['status'] === 'failed');

        if ($hasFailure) {
            return back()
                ->withErrors(['core' => collect($results)->lastWhere('status', 'failed')['message'] ?? 'Core install failed.'])
                ->with('install.core_results', $results);
        }

        return redirect()
            ->route('install.admin')
            ->with('status', 'Core CMS setup completed.')
            ->with('install.core_results', $results);
    }

    public function admin(): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenStepUnavailable('admin')) {
            return $redirect;
        }

        return view('install.admin', $this->sharedViewData('admin', []));
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenStepUnavailable('admin')) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $this->installer->createFirstSuperAdmin($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['admin' => $exception->getMessage()])->withInput($request->except(['password', 'password_confirmation']));
        }

        $request->session()->put('install.finish_available', true);

        return redirect()->route('install.finish');
    }

    public function finish(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('install.finish_available')) {
            return $this->installState->isInstalled()
                ? redirect()->route('login')
                : redirect()->route($this->installState->nextIncompleteStepRouteName());
        }

        return view('install.finish', $this->sharedViewData('finish', []));
    }

    private function validateDatabaseRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'db_connection' => ['required', Rule::in(['sqlite', 'mysql', 'mariadb', 'pgsql', 'sqlsrv'])],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:20'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $driver = (string) $request->input('db_connection');

            if ($driver !== 'sqlite') {
                foreach (['db_host', 'db_port', 'db_database', 'db_username'] as $field) {
                    if (! filled((string) $request->input($field))) {
                        $validator->errors()->add($field, 'This field is required for the selected database driver.');
                    }
                }
            }
        });

        return $validator->validate();
    }

    private function redirectWhenStepUnavailable(string $step): ?RedirectResponse
    {
        $nextRoute = $this->installState->nextIncompleteStepRouteName();

        if ($step === 'database') {
            return null;
        }

        if ($step === 'core' && ! $this->installState->databaseReachable()) {
            return redirect()->route('install.database');
        }

        if ($step === 'admin' && ! $this->installState->coreInstalled()) {
            return redirect()->route($nextRoute);
        }

        return null;
    }

    private function sharedViewData(string $currentStep, array $data): array
    {
        return $data + [
            'currentStep' => $currentStep,
            'steps' => $this->steps($currentStep),
        ];
    }

    private function steps(string $currentStep): array
    {
        $nextIncomplete = $this->installState->nextIncompleteStepRouteName();
        $currentIndex = [
            'welcome' => 1,
            'database' => 2,
            'core' => 3,
            'admin' => 4,
            'finish' => 5,
        ][$currentStep];

        $nextIndex = match ($nextIncomplete) {
            'install.database' => 2,
            'install.core' => 3,
            'install.admin' => 4,
            default => 5,
        };

        return [
            ['key' => 'welcome', 'label' => 'Requirements', 'route' => 'install.welcome', 'state' => $currentIndex === 1 ? 'current' : ($nextIndex > 2 ? 'complete' : 'upcoming')],
            ['key' => 'database', 'label' => 'Database', 'route' => 'install.database', 'state' => $currentIndex === 2 ? 'current' : ($nextIndex > 2 ? 'complete' : 'upcoming')],
            ['key' => 'core', 'label' => 'Install Core', 'route' => 'install.core', 'state' => $currentIndex === 3 ? 'current' : ($nextIndex > 3 ? 'complete' : 'upcoming')],
            ['key' => 'admin', 'label' => 'First Admin', 'route' => 'install.admin', 'state' => $currentIndex === 4 ? 'current' : ($nextIndex > 4 ? 'complete' : 'upcoming')],
            ['key' => 'finish', 'label' => 'Finish', 'route' => 'install.finish', 'state' => $currentIndex === 5 ? 'current' : ($nextIndex > 5 ? 'complete' : 'upcoming')],
        ];
    }
}
