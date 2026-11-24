<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // SQLite exige suspender las claves foráneas antes de iniciar la transacción de reconstrucción.
    public $withinTransaction = false;

    private const REGISTERED = '(`client_user_id` IS NOT NULL AND `direct_service_id` IS NOT NULL)';

    private const EXTERNAL = '(`client_user_id` IS NULL AND `direct_service_id` IS NULL)';

    public function up(): void
    {
        $this->changeConstraint('(' . self::REGISTERED . ' OR ' . self::EXTERNAL . ')', 'ck_27_01', 'ck_budgets_origin');
    }

    public function down(): void
    {
        // Evita eliminar documentos externos para restaurar una restricción que los excluiría.
        if (DB::table('budgets')->whereNull('proposal_id')->whereNull('project_request_id')->whereNull('client_user_id')->exists()) {
            throw new RuntimeException('Hay presupuestos externos que requieren la restricción actual.');
        }
        $this->changeConstraint(self::REGISTERED, 'ck_budgets_origin', 'ck_27_01');
    }

    private function changeConstraint(string $to, string $oldName, string $newName): void
    {
        $condition = "(proposal_id IS NOT NULL AND project_request_id IS NULL AND professional_profile_id IS NULL AND client_user_id IS NULL AND direct_service_id IS NULL)
            OR (proposal_id IS NULL AND project_request_id IS NOT NULL AND professional_profile_id IS NOT NULL AND client_user_id IS NULL AND direct_service_id IS NULL)
            OR (proposal_id IS NULL AND project_request_id IS NULL AND professional_profile_id IS NOT NULL AND $to)";

        if (DB::getDriverName() !== 'sqlite') {
            // MySQL y MariaDB usan sintaxis distinta para retirar una restricción CHECK.
            $version = DB::selectOne('SELECT VERSION() AS server_version')->server_version;
            $drop = str_contains(strtolower($version), 'mariadb') ? 'DROP CONSTRAINT' : 'DROP CHECK';
            DB::statement("ALTER TABLE budgets $drop $oldName, ADD CONSTRAINT $newName CHECK ($condition)");

            return;
        }

        // Reconstruye solamente la tabla de origen, conservando IDs, índices y referencias.
        Schema::withoutForeignKeyConstraints(fn() => DB::transaction(function () use ($oldName, $newName, $condition) {
            $definition = DB::table('sqlite_master')->where('type', 'table')->where('name', 'budgets')->value('sql');
            $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'budgets')->whereNotNull('sql')->pluck('sql');
            $sequence = DB::table('sqlite_sequence')->where('name', 'budgets')->value('seq');
            $constraint = strpos($definition, 'CONSTRAINT `' . $oldName . '` CHECK');
            if ($constraint === false) {
                throw new RuntimeException('No se encontró la restricción de origen de presupuestos.');
            }
            $definition = preg_replace('/^CREATE TABLE ["`]?budgets["`]?/i', 'CREATE TABLE budgets_rebuild', substr($definition, 0, $constraint));
            DB::statement($definition . 'CONSTRAINT `' . $newName . '` CHECK (' . $condition . '))');
            DB::statement('INSERT INTO budgets_rebuild SELECT * FROM budgets');
            DB::statement('DROP TABLE budgets');
            DB::statement('ALTER TABLE budgets_rebuild RENAME TO budgets');
            foreach ($indexes as $index) {
                DB::statement($index);
            }
            if ($sequence !== null) {
                DB::table('sqlite_sequence')->updateOrInsert(['name' => 'budgets'], ['seq' => $sequence]);
            }
            if (DB::select('PRAGMA foreign_key_check')) {
                throw new RuntimeException('La reconstrucción de presupuestos dejó referencias inválidas.');
            }
        }));
    }
};
