<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Tests\TestCase;

class PasswordRulesConfigTest extends TestCase
{
    private function passes(string $password): bool
    {
        return Validator::make(['p' => $password], ['p' => [Password::default()]])->passes();
    }

    public function test_non_production_default_is_eight_characters(): void
    {
        config(['auth.password_rules' => ['min_length' => null, 'rules' => null]]);

        $this->assertFalse($this->passes('short'));
        $this->assertTrue($this->passes('alllowercase'));
    }

    public function test_min_length_and_comma_separated_rules_are_applied(): void
    {
        config(['auth.password_rules' => ['min_length' => '10', 'rules' => 'mixed_case, numbers']]);

        $this->assertFalse($this->passes('abcdefghi'));
        $this->assertFalse($this->passes('alllowercase1'));
        $this->assertFalse($this->passes('NoDigitsHere'));
        $this->assertTrue($this->passes('GoodPassw0rd'));
    }

    public function test_empty_rules_require_only_the_minimum_length(): void
    {
        config(['auth.password_rules' => ['min_length' => '6', 'rules' => '']]);

        $this->assertFalse($this->passes('abc'));
        $this->assertTrue($this->passes('abcdef'));
    }

    public function test_unknown_rule_name_is_rejected(): void
    {
        config(['auth.password_rules' => ['min_length' => null, 'rules' => 'numbers,bogus']]);

        $this->expectException(InvalidArgumentException::class);

        $this->passes('whatever123');
    }
}
