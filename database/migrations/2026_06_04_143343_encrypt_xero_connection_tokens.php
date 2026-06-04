<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    private array $fields = ['access_token', 'refresh_token', 'scopes'];

    public function up(): void
    {
        DB::table('xero_connections')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $updates = [];
                foreach ($this->fields as $field) {
                    $plain = $row->$field;
                    if ($plain !== null) {
                        $updates[$field] = Crypt::encryptString($plain);
                    }
                }
                if ($updates) {
                    DB::table('xero_connections')->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('xero_connections')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $updates = [];
                foreach ($this->fields as $field) {
                    $encrypted = $row->$field;
                    if ($encrypted !== null) {
                        $updates[$field] = Crypt::decryptString($encrypted);
                    }
                }
                if ($updates) {
                    DB::table('xero_connections')->where('id', $row->id)->update($updates);
                }
            }
        });
    }
};
