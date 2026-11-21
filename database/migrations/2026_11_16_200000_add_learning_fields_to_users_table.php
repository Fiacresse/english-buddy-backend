<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('level', 2)->nullable()->after('password');
            $table->json('goals')->nullable()->after('level');
            $table->unsignedSmallInteger('daily_goal_minutes')->default(15)->after('goals');
            $table->unsignedInteger('xp')->default(0)->after('daily_goal_minutes');
            $table->unsignedInteger('streak')->default(0)->after('xp');
            $table->date('last_active_on')->nullable()->after('streak');
            $table->boolean('onboarded')->default(false)->after('last_active_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['level', 'goals', 'daily_goal_minutes', 'xp', 'streak', 'last_active_on', 'onboarded']);
        });
    }
};
