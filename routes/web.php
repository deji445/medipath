<?php

use App\Http\Controllers\ConsultantChatController;
use App\Http\Controllers\ConsultantRequestController;
use App\Http\Controllers\ConsultantRequestQueueController;
use App\Http\Controllers\EmergencyHelpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HospitalDashboardController;
use App\Http\Controllers\HospitalSettingsController;
use App\Http\Controllers\HospitalStaffController;
use App\Http\Controllers\HospitalSubmissionStatusController;
use App\Http\Controllers\PatientAssessmentController;
use App\Http\Controllers\PatientIntakeController;
use App\Http\Controllers\PatientRoutingController;
use App\Http\Controllers\PatientSubmissionController;
use App\Http\Controllers\PlatformHospitalController;
use App\Http\Controllers\WalkInIntakeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('emergency', EmergencyHelpController::class)->name('emergency');
Route::post('consultant/chat', [ConsultantChatController::class, 'store'])
    ->middleware('throttle:12,1')->name('consultant.chat.store');
Route::delete('consultant/chat', [ConsultantChatController::class, 'destroy'])
    ->name('consultant.chat.destroy');
Route::post('consultant/request', [ConsultantRequestController::class, 'store'])
    ->middleware('throttle:5,10')->name('consultant.request.store');
Route::get('walk-in/{hospital:slug}', [WalkInIntakeController::class, 'show'])
    ->name('walk-in.show');
Route::post('walk-in/{hospital:slug}', [WalkInIntakeController::class, 'start'])
    ->middleware('throttle:10,1')->name('walk-in.start');

Route::prefix('intake')->name('intake.')->group(function () {
    Route::get('/', [PatientIntakeController::class, 'start'])->name('start');
    Route::post('lookup', [PatientIntakeController::class, 'lookup'])->middleware('throttle:10,1')->name('lookup');
    Route::post('profile', [PatientIntakeController::class, 'storeProfile'])->name('profile.store');
    Route::get('identity', [PatientIntakeController::class, 'identity'])->name('identity');
    Route::post('identity', [PatientIntakeController::class, 'storeIdentity'])->name('identity.store');
    Route::get('history', [PatientIntakeController::class, 'history'])->name('history');
    Route::post('history', [PatientIntakeController::class, 'storeHistory'])->name('history.store');
    Route::get('visit', [PatientIntakeController::class, 'visit'])->name('visit');
    Route::post('visit', [PatientIntakeController::class, 'storeVisit'])->name('visit.store');
    Route::get('visit/current', [PatientIntakeController::class, 'editVisit'])->name('visit.edit');
    Route::patch('visit/current', [PatientIntakeController::class, 'updateVisit'])->name('visit.update');
    Route::get('review', [PatientIntakeController::class, 'review'])->name('review');
    Route::post('review', [PatientIntakeController::class, 'store'])->name('store');
    Route::get('complete', [PatientIntakeController::class, 'complete'])->name('complete');
});

Route::prefix('intake/assessment')->name('assessment.')->group(function () {
    Route::get('/', [PatientAssessmentController::class, 'ready'])->name('ready');
    Route::post('/', [PatientAssessmentController::class, 'storeAnswer'])->name('answer.store');
    Route::get('safety', [PatientAssessmentController::class, 'safety'])->name('safety');
    Route::post('safety', [PatientAssessmentController::class, 'storeSafety'])->name('safety.store');
    Route::get('emergency', [PatientAssessmentController::class, 'emergency'])->name('emergency');
    Route::get('result', [PatientAssessmentController::class, 'result'])->name('result');
});

Route::prefix('intake/routing')->name('routing.')->group(function () {
    Route::get('/', [PatientRoutingController::class, 'index'])->name('index');
    Route::post('/', [PatientRoutingController::class, 'store'])->name('store');
    Route::get('selected', [PatientRoutingController::class, 'selected'])->name('selected');
});

Route::prefix('intake/submission')->name('submission.')->group(function () {
    Route::get('/', [PatientSubmissionController::class, 'review'])->name('review');
    Route::post('/', [PatientSubmissionController::class, 'store'])->name('store');
    Route::get('complete', [PatientSubmissionController::class, 'complete'])->name('complete');
});

Route::middleware(['auth', 'verified', 'staff.tenant'])->group(function () {
    Route::get('dashboard', HospitalDashboardController::class)->name('dashboard');
    Route::patch('dashboard/submissions/{submission}', HospitalSubmissionStatusController::class)
        ->name('dashboard.submissions.status');

    Route::get('hospital/settings', [HospitalSettingsController::class, 'edit'])
        ->name('hospital.settings.edit');
    Route::patch('hospital/settings', [HospitalSettingsController::class, 'update'])
        ->name('hospital.settings.update');
    Route::get('hospital/staff', [HospitalStaffController::class, 'index'])
        ->name('hospital.staff.index');
    Route::post('hospital/staff', [HospitalStaffController::class, 'store'])
        ->name('hospital.staff.store');
    Route::patch('hospital/staff/{staffUser}', [HospitalStaffController::class, 'update'])
        ->name('hospital.staff.update');
});

Route::middleware(['auth', 'verified'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', [PlatformHospitalController::class, 'index'])->name('index');
    Route::post('hospitals', [PlatformHospitalController::class, 'store'])->name('hospitals.store');
    Route::patch('hospitals/{hospital}', [PlatformHospitalController::class, 'update'])
        ->name('hospitals.update');
    Route::get('consultant-requests', [ConsultantRequestQueueController::class, 'index'])
        ->name('consultant-requests.index');
    Route::patch('consultant-requests/{consultantRequest}', [ConsultantRequestQueueController::class, 'update'])
        ->name('consultant-requests.update');
});

require __DIR__.'/settings.php';
