<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserQrCodeAuthenticationTest extends TestCase
{
    #[DataProvider('acceptHeaders')]
    public function test_guests_receive_a_json_401_regardless_of_accept_header(array $headers): void
    {
        $this->get('/api/user/qrcode', $headers)
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public static function acceptHeaders(): array
    {
        return [
            'no Accept header' => [[]],
            'browser' => [['Accept' => 'text/html,application/xhtml+xml']],
            'wildcard' => [['Accept' => '*/*']],
            'JSON client' => [['Accept' => 'application/json']],
        ];
    }

    public function test_other_protected_api_routes_also_return_json_for_guests(): void
    {
        $this->get('/api/user_info')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_users_can_still_retrieve_their_qr_codes(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        Schema::create('qrcodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('profile_id')->nullable();
        });

        Schema::create('user_location', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qrcode_id');
            $table->text('location')->nullable();
            $table->timestamps();
        });

        DB::table('qrcodes')->insert(['id' => 1, 'user_id' => 123]);
        DB::table('user_location')->insert([
            ['qrcode_id' => 1, 'location' => json_encode(['ip' => '1.1.1.1', 'country' => 'Egypt', 'city' => 'Cairo'])],
            ['qrcode_id' => 1, 'location' => json_encode(['ip' => '1.1.1.1', 'country' => 'Egypt', 'city' => 'Cairo'])],
            ['qrcode_id' => 1, 'location' => json_encode(['ip' => '2.2.2.2', 'country' => 'Egypt', 'city' => 'Giza'])],
            ['qrcode_id' => 1, 'location' => 'invalid JSON'],
        ]);

        Sanctum::actingAs((new User)->forceFill(['id' => 123]));

        $this->get('/api/user/qrcode')
            ->assertOk()
            ->assertJsonPath('qr_codes.0.device_count', 2)
            ->assertJsonPath('qr_codes.0.locations', [
                ['country' => 'Egypt', 'city' => 'Cairo'],
                ['country' => 'Egypt', 'city' => 'Giza'],
            ]);
    }

    public function test_web_errors_keep_their_html_response(): void
    {
        $this->get('/missing-web-page', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_web_errors_still_honor_the_json_accept_header(): void
    {
        $this->getJson('/missing-web-page')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
