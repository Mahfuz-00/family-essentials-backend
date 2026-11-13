<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FINANCIAL_TABLES = [
        'financial_goals',
        'financial_liabilities',
        'financial_assets',
        'financial_taxes',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add yearly_budget_minor to ledgers
        Schema::table('ledgers', function (Blueprint $table) {
            $table->bigInteger('yearly_budget_minor')->nullable()->after('weekly_budget_minor');
        });

        // 2. Financial Goals (Emergency fund, vacation, new car, house down payment, etc.)
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('category', 64)->default('savings'); // emergency, vacation, vehicle, home, education, other
            $table->bigInteger('target_amount_minor');
            $table->bigInteger('current_amount_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('target_date')->nullable();
            $table->string('status', 32)->default('active'); // active, achieved, paused
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 3. Financial Liabilities (Mortgages, auto loans, personal loans, student debt, credit lines)
        Schema::create('financial_liabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('type', 64)->default('loan'); // mortgage, loan, auto_loan, student_loan, personal_loan, credit_line
            $table->string('lender', 255)->nullable();
            $table->bigInteger('principal_amount_minor')->default(0);
            $table->bigInteger('remaining_balance_minor');
            $table->bigInteger('monthly_payment_minor')->default(0);
            $table->integer('interest_rate_bps')->nullable(); // e.g. 650 = 6.50%
            $table->unsignedSmallInteger('due_day_of_month')->nullable(); // 1 to 31
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('active'); // active, paid_off, refinanced
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
        });

        // 4. Financial Assets (Savings accounts, brokerage investments, 401k/retirement, crypto, cash)
        Schema::create('financial_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('type', 64)->default('savings'); // savings, investment, stocks, retirement, crypto, real_estate, cash, other
            $table->string('institution', 255)->nullable();
            $table->bigInteger('balance_minor');
            $table->bigInteger('contribution_monthly_minor')->nullable();
            $table->integer('return_rate_bps')->nullable(); // e.g. 750 = 7.50% expected return
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        // 5. Financial Taxes (Property taxes, income tax estimates, municipal dues, deductions)
        Schema::create('financial_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('type', 64)->default('property'); // property, income, municipal, sales, deduction, other
            $table->unsignedSmallInteger('tax_year');
            $table->bigInteger('amount_minor');
            $table->date('due_date')->nullable();
            $table->string('status', 32)->default('pending'); // pending, paid, filed, overdue
            $table->timestamp('paid_at')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tax_year']);
            $table->index(['tenant_id', 'status']);
        });

        // 6. Enforce PostgreSQL Row-Level Security (RLS) on all tenant financial tables
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_admin') {
            $scope = "
                current_setting('app.bypass_rls', true) = 'on'
                OR tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint
            ";

            foreach (self::FINANCIAL_TABLES as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING ({$scope}) WITH CHECK ({$scope})");
            }

            // 7. Grant access to runtime role 'family_app' if role exists
            $hasAppRole = DB::selectOne("SELECT 1 FROM pg_roles WHERE rolname = 'family_app'") !== null;
            if ($hasAppRole) {
                foreach (self::FINANCIAL_TABLES as $table) {
                    DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO family_app");
                    DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$table}_id_seq TO family_app");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_admin') {
            foreach (self::FINANCIAL_TABLES as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('financial_taxes');
        Schema::dropIfExists('financial_assets');
        Schema::dropIfExists('financial_liabilities');
        Schema::dropIfExists('financial_goals');

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('yearly_budget_minor');
        });
    }
};
