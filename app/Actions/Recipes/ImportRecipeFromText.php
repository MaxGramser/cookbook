<?php

namespace App\Actions\Recipes;

use App\Ai\Agents\RecipeExtractor;
use App\Models\Recipe;
use App\Models\User;
use App\Support\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ImportRecipeFromText
{
    public function __construct(
        private RecipeExtractor $extractor,
        private PersistExtractedRecipe $persister,
        private ImageProcessor $imageProcessor,
    ) {}

    public function handle(User $user, string $text, ?UploadedFile $image = null): Recipe
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Geen tekst opgegeven.');
        }
        if (mb_strlen($text) > 20_000) {
            $text = mb_substr($text, 0, 20_000);
        }

        $extracted = $this->extractor->prompt($text);

        $imagePath = $image !== null
            ? $this->imageProcessor->processUpload($image)
            : null;

        return $this->persister->handle($user, $extracted, null, $imagePath);
    }
}
