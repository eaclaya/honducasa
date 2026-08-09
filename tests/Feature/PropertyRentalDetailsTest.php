<?php

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('a property stores rental terms and ordered images', function () {
    Storage::fake('public');
    $property = Property::factory()->create([
        'price_amount' => 18_500,
        'currency' => 'HNL',
        'deposit_amount' => 18_500,
        'utilities_included' => true,
    ]);

    $second = $property->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection('photos');
    $second->update(['order_column' => 2]);
    $primary = $property->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('photos');
    $primary->update(['order_column' => 1]);

    expect($property->fresh())
        ->price_amount->toBe(18_500)
        ->currency->toBe('HNL')
        ->deposit_amount->toBe(18_500)
        ->utilities_included->toBeTrue()
        ->and($property->getMedia('photos')->pluck('id')->all())->toBe([$primary->id, $second->id])
        ->and($property->getFirstMedia('photos')->is($primary))->toBeTrue();
});
