<?php

namespace App\Services;

use App\Exceptions\PhotoEnhancementUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OpenAiPropertyPhotoEnhancer
{
    public function enhance(Media $media): string
    {
        $apiKey = config('services.openai.api_key');
        $path = $media->getPath();

        if (! is_string($apiKey) || $apiKey === '' || ! is_readable($path)) {
            throw new PhotoEnhancementUnavailableException;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new PhotoEnhancementUnavailableException;
        }

        $extension = match ($media->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new PhotoEnhancementUnavailableException,
        };
        $filename = pathinfo($media->file_name, PATHINFO_FILENAME).'.'.$extension;

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->connectTimeout(5)->timeout(180)
                ->retry([500, 1500], throw: false)
                ->attach('image[]', $contents, $filename, [
                    'Content-Type' => $media->mime_type,
                ])
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/images/edits', [
                    'model' => config('services.openai.image_model'),
                    'quality' => config('services.openai.image_quality'),
                    'size' => 'auto',
                    'prompt' => $this->prompt(),
                ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new PhotoEnhancementUnavailableException(previous: $exception);
        }

        $encodedImage = $response->json('data.0.b64_json');
        $enhancedImage = is_string($encodedImage) ? base64_decode($encodedImage, true) : false;

        if (! is_string($enhancedImage) || $enhancedImage === '') {
            throw new PhotoEnhancementUnavailableException;
        }

        return $enhancedImage;
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Enhance this existing property photo so it looks like professional real-estate photography suitable for a high-quality property listing.

IMPORTANT: Preserve the property faithfully. This is photo enhancement, not virtual renovation or redesign. Do not change the architecture, room dimensions, layout, permanent fixtures, wall locations, windows, doors, flooring, cabinetry, countertops, fireplace, stairs, ceiling design, or other structural/property features.

Apply the following improvements:

LIGHTING & EXPOSURE

- Create a bright, natural, professionally exposed interior.
- Lift dark shadows while retaining realistic depth.
- Reduce blown highlights, especially around windows and ceiling lights.
- Recover visible window detail when that information exists in the original image.
- Balance bright windows with the interior using a subtle, realistic HDR-style treatment.
- Do not fabricate an exterior view if the original window is completely overexposed.
- Avoid an artificial HDR appearance.

WHITE BALANCE & COLOR

- Correct white balance so white ceilings, cabinets, tiles, and fixtures appear naturally white.
- Remove unwanted yellow/orange color casts caused by artificial lighting.
- Preserve the property's actual wall colors.
- Slightly reduce excessive saturation where necessary.
- Keep colors natural, neutral, warm, and realistic.
- Do not repaint or digitally change wall colors.

PERSPECTIVE & LENS CORRECTION

- Correct lens distortion.
- Straighten vertical architectural lines.
- Correct perspective so walls, windows, cabinets, doors, and columns appear properly vertical.
- Level horizontal lines where appropriate.
- Do not artificially widen the room or exaggerate its dimensions.

COMPOSITION

- Improve the crop when necessary to emphasize the room and its important architectural features.
- Preserve enough context to communicate the room's actual size and layout.
- Give the image the balanced composition of professional real-estate photography.

DIGITAL DECLUTTERING
Remove only temporary visual clutter that distracts from the property, such as:

- loose cables
- plastic bags and packaging
- refrigerator magnets/stickers
- random countertop clutter
- cleaning items
- small miscellaneous household objects
- toys when they significantly distract from the room
- pets
- other obviously temporary personal objects

Do NOT remove permanent property features, major furniture, cabinetry, appliances, architectural elements, railings, fireplaces, windows, doors, lighting fixtures, or anything whose removal could misrepresent the property.

IMAGE QUALITY

- Improve sharpness and fine architectural detail.
- Apply mild noise reduction.
- Add subtle local contrast and clarity.
- Improve definition of floors, cabinetry, countertops, furniture, windows, and architectural details.
- Keep textures realistic.
- Avoid excessive sharpening, halos, fake HDR, oversaturation, or an AI-generated appearance.

CEILING & LIGHTS

- Recover excessive brightness around chandeliers and ceiling fixtures.
- Make white ceilings clean and naturally illuminated.
- Enhance the visibility of decorative ceiling architecture without changing its design.

WINDOWS

- Treat windows as an important architectural feature.
- Reduce excessive glare and blown highlights where possible.
- Preserve the exact window shape, frames, blinds, and architecture.
- Never invent scenery or a view that isn't supported by the original photograph.

FINAL STYLE
The finished image should resemble a professionally edited real-estate photograph taken by an experienced property photographer using a high-quality wide-angle camera and careful natural/HDR exposure blending.

It should feel:
bright,
clean,
spacious,
natural,
sharp,
neutral,
inviting,
professional,
and realistic.

The final result must still clearly be the SAME photograph of the SAME property.

Do not redesign, renovate, restage, replace furniture, change architecture, invent features, or make the property appear materially different from reality.
PROMPT;
    }
}
