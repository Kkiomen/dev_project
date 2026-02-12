<?php

use App\Models\Brand;
use App\Models\SmAccount;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmAccount', function () {

    describe('isActive', function () {

        it('returns true when status is active', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);

            expect($account->isActive())->toBeTrue();
        });

        it('returns false when status is not active', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'expired',
            ]);

            expect($account->isActive())->toBeFalse();
        });
    });

    describe('isExpired', function () {

        it('returns true when token_expires_at is in the past', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'token_expires_at' => Carbon::now()->subDay(),
            ]);

            expect($account->isExpired())->toBeTrue();
        });

        it('returns false when token_expires_at is in the future', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'token_expires_at' => Carbon::now()->addDay(),
            ]);

            expect($account->isExpired())->toBeFalse();
        });

        it('returns false when token_expires_at is null', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'token_expires_at' => null,
            ]);

            expect($account->isExpired())->toBeFalse();
        });
    });

    describe('isConnected', function () {

        it('returns true when active, not expired, and has access token', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
                'token_expires_at' => Carbon::now()->addDay(),
                'access_token' => 'some-token',
            ]);

            expect($account->isConnected())->toBeTrue();
        });

        it('returns false when not active', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'expired',
                'token_expires_at' => Carbon::now()->addDay(),
                'access_token' => 'some-token',
            ]);

            expect($account->isConnected())->toBeFalse();
        });

        it('returns false when token is expired', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
                'token_expires_at' => Carbon::now()->subDay(),
                'access_token' => 'some-token',
            ]);

            expect($account->isConnected())->toBeFalse();
        });

        it('returns false when access_token is null', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
                'token_expires_at' => Carbon::now()->addDay(),
                'access_token' => null,
            ]);

            expect($account->isConnected())->toBeFalse();
        });
    });

    describe('markAsExpired', function () {

        it('sets status to expired and saves', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
            ]);

            $account->markAsExpired();

            $account->refresh();
            expect($account->status)->toBe('expired');
        });
    });

    describe('markAsRevoked', function () {

        it('sets status to revoked and clears tokens', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'status' => 'active',
                'access_token' => 'my-token',
                'refresh_token' => 'my-refresh',
            ]);

            $account->markAsRevoked();

            $account->refresh();
            expect($account->status)->toBe('revoked')
                ->and($account->access_token)->toBeNull()
                ->and($account->refresh_token)->toBeNull();
        });
    });

    describe('token encryption', function () {

        it('encrypts and decrypts access_token', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'access_token' => 'my-secret-access-token',
            ]);

            // The decrypted value should match what we set
            expect($account->access_token)->toBe('my-secret-access-token');

            // The raw database value should be encrypted (not plain text)
            $rawValue = $account->getAttributes()['access_token'];
            expect($rawValue)->not->toBe('my-secret-access-token')
                ->and($rawValue)->not->toBeNull();
        });

        it('encrypts and decrypts refresh_token', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'refresh_token' => 'my-secret-refresh-token',
            ]);

            // The decrypted value should match what we set
            expect($account->refresh_token)->toBe('my-secret-refresh-token');

            // The raw database value should be encrypted (not plain text)
            $rawValue = $account->getAttributes()['refresh_token'];
            expect($rawValue)->not->toBe('my-secret-refresh-token')
                ->and($rawValue)->not->toBeNull();
        });

        it('handles null tokens gracefully', function () {
            $account = SmAccount::factory()->create([
                'brand_id' => $this->brand->id,
                'access_token' => null,
                'refresh_token' => null,
            ]);

            expect($account->access_token)->toBeNull()
                ->and($account->refresh_token)->toBeNull();
        });
    });
});
