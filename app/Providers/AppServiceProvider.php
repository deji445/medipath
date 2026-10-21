<?php

namespace App\Providers;

use App\Contracts\PhoneVerificationSender;
use App\Models\Hospital;
use App\Policies\HospitalPolicy;
use App\Services\Assessment\AssessmentConversationProvider;
use App\Services\Assessment\AssessmentProvider;
use App\Services\Assessment\DeterministicAssessmentProvider;
use App\Services\Assessment\GeminiAssessmentConversationProvider;
use App\Services\Assessment\GeminiAssessmentProvider;
use App\Services\Verification\BmsPhoneVerificationSender;
use App\Services\Verification\DemoPhoneVerificationSender;
use App\Services\Verification\LocalPhoneVerificationSender;
use App\Services\Verification\NaloPhoneVerificationSender;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->bind(PhoneVerificationSender::class, function ($app): PhoneVerificationSender {
            return match (config('medipath.phone_verification.driver')) {
                'local' => $app->make(LocalPhoneVerificationSender::class),
                'demo' => $app->make(DemoPhoneVerificationSender::class),
                'bms' => $app->make(BmsPhoneVerificationSender::class),
                'nalo' => $app->make(NaloPhoneVerificationSender::class),
                default => throw new \RuntimeException('Unsupported phone verification driver.'),
            };
        });
        $this->app->bind(AssessmentProvider::class, function ($app): AssessmentProvider {
            return match (config('medipath.assessment.driver')) {
                'gemini' => $app->make(GeminiAssessmentProvider::class),
                'deterministic' => $app->make(DeterministicAssessmentProvider::class),
                default => throw new \RuntimeException('Unsupported MediPath assessment driver.'),
            };
        });
        $this->app->bind(AssessmentConversationProvider::class, fn ($app): AssessmentConversationProvider => $app->make(GeminiAssessmentConversationProvider::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::policy(Hospital::class, HospitalPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
