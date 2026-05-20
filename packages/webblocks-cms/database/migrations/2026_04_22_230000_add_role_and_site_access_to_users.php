<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->nullable()->after('password');
        });

        Schema::create('site_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'site_id']);
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update(['role' => User::ROLE_SUPER_ADMIN]);

        DB::table('users')
            ->whereNull('role')
            ->update(['role' => User::ROLE_EDITOR]);

        DB::table('users')->update([
            'is_admin' => DB::raw("case when role = '".User::ROLE_SUPER_ADMIN."' then 1 else 0 end"),
        ]);

        $primarySiteId = DB::table('sites')
            ->where('is_primary', true)
            ->value('id')
            ?? DB::table('sites')->orderBy('id')->value('id');

        if ($primarySiteId) {
            $now = now();
            $users = DB::table('users')
                ->select('id')
                ->where('role', '!=', User::ROLE_SUPER_ADMIN)
                ->get();

            foreach ($users as $user) {
                DB::table('site_user')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'site_id' => $primarySiteId,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
