<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Students no longer have accounts: they pick classes on the public landing page.
     * Drop the accounts that still hold the role, then the role itself.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'mahasiswa')->value('id');

        if ($roleId === null) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('model_id');

        DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->whereIn('tokenable_id', $userIds)->delete();
        DB::table('model_has_roles')->where('role_id', $roleId)->delete();
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }

    public function down(): void
    {
        // Deleted accounts cannot be restored.
    }
};
