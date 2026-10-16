<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_english_is_the_default_locale(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.current', 'en')
                ->where('localization.translations.language', 'Language')
                ->has('localization.locales', 2));
    }

    public function test_a_user_can_switch_to_twi_and_keep_the_preference(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'tw'])
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'tw')
            ->assertCookie('locale', 'tw');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.current', 'tw')
                ->where('localization.translations.language', 'Kasa')
                ->where('localization.translations.english', 'Borɔfo'));
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing('locale');
    }

    public function test_an_invalid_locale_cookie_falls_back_to_english(): void
    {
        $this->withCookie('locale', 'unsupported')
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.current', 'en'));
    }
}
