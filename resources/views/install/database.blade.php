<x-guest-layout title="Database Setup" meta-description="Configure the database connection for WebBlocks CMS.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Database setup"
        description="Save the database connection that WebBlocks CMS should use for this install."
    >
        <x-auth-feedback />

        @include('install.partials.steps', ['steps' => $steps])

        <form method="POST" action="{{ route('install.database.store') }}" class="wb-stack wb-gap-4">
            @csrf

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-field">
                        <x-input-label for="db_connection" value="Database driver" />
                        <select id="db_connection" name="db_connection" class="wb-select">
                            @foreach (['sqlite' => 'SQLite', 'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL', 'sqlsrv' => 'SQL Server'] as $value => $label)
                                <option value="{{ $value }}" @selected($databaseDefaults['db_connection'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('db_connection')" />
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field">
                            <x-input-label for="db_host" value="Database host" />
                            <x-text-input id="db_host" type="text" name="db_host" :value="$databaseDefaults['db_host']" autocomplete="off" />
                            <x-input-error :messages="$errors->get('db_host')" />
                        </div>

                        <div class="wb-field">
                            <x-input-label for="db_port" value="Database port" />
                            <x-text-input id="db_port" type="text" name="db_port" :value="$databaseDefaults['db_port']" autocomplete="off" />
                            <x-input-error :messages="$errors->get('db_port')" />
                        </div>
                    </div>

                    <div class="wb-field">
                        <x-input-label for="db_database" :value="$databaseDefaults['db_connection'] === 'sqlite' ? 'SQLite database path' : 'Database name'" />
                        <x-text-input id="db_database" type="text" name="db_database" :value="$databaseDefaults['db_database']" autocomplete="off" />
                        <x-input-error :messages="$errors->get('db_database')" />
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field">
                            <x-input-label for="db_username" value="Database username" />
                            <x-text-input id="db_username" type="text" name="db_username" :value="$databaseDefaults['db_username']" autocomplete="off" />
                            <x-input-error :messages="$errors->get('db_username')" />
                        </div>

                        <div class="wb-field">
                            <x-input-label for="db_password" value="Database password" />
                            <x-text-input id="db_password" type="password" name="db_password" value="" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('db_password')" />
                            @if ($passwordSaved)
                                <div class="wb-text-xs wb-text-muted">A database password is already saved and remains hidden.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-row wb-row-gap-2">
                <a href="{{ route('install.welcome') }}" class="wb-btn wb-btn-secondary">Back</a>
                <x-primary-button>Save and test connection</x-primary-button>
            </div>
        </form>
    </x-auth-shell>
</x-guest-layout>
