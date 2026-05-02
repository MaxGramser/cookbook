<?php

namespace App\Console\Commands;

use App\Actions\Recipes\ImportRecipeFromUrl;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('recipes:import-test {--email= : Email of the user to attach recipes to} {--file=test_recipies.php : Path (relative to base) to a list of URLs}')]
#[Description('Run the URL-import pipeline against a list of test recipes and report what was extracted.')]
class ImportTestRecipes extends Command
{
    public function handle(ImportRecipeFromUrl $action): int
    {
        $user = $this->resolveUser();
        if ($user === null) {
            return self::FAILURE;
        }

        $path = base_path((string) $this->option('file'));
        if (! is_file($path)) {
            $this->error("Bestand niet gevonden: {$path}");

            return self::FAILURE;
        }

        /** @var array<int, string> $urls */
        $urls = require $path;
        $this->info('Importeren als '.$user->email.' — '.count($urls).' URLs');
        $this->newLine();

        $ok = 0;
        $fail = 0;

        foreach ($urls as $url) {
            $this->line("→ {$url}");
            try {
                $recipe = $action->handle($user, $url);
                $ok++;
                $this->line(sprintf(
                    '  ✓ %s · %d ingr · %d stappen · %d pers · %s',
                    $recipe->title,
                    $recipe->ingredients()->count(),
                    $recipe->steps()->count(),
                    $recipe->servings,
                    $recipe->cook_time_minutes ? "{$recipe->cook_time_minutes} min" : 'geen kooktijd',
                ));
                foreach ($recipe->ingredients()->orderBy('position')->limit(4)->get() as $ingredient) {
                    $this->line(sprintf(
                        '     · %s %s %s',
                        $ingredient->quantity ?? '—',
                        $ingredient->unit ?? '',
                        $ingredient->name,
                    ));
                }
                if ($recipe->ingredients()->count() > 4) {
                    $this->line('     · ...');
                }
            } catch (Throwable $e) {
                $fail++;
                $this->error('  ✗ '.$e->getMessage());
            }
            $this->newLine();
        }

        $this->info("Klaar: {$ok} ok, {$fail} fout");

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveUser(): ?User
    {
        $email = $this->option('email');

        if (is_string($email) && $email !== '') {
            $user = User::where('email', $email)->first();
            if ($user === null) {
                $this->error("Geen user met email {$email}");
            }

            return $user;
        }

        $user = User::orderBy('id')->first();
        if ($user === null) {
            $this->error('Er bestaat nog geen user — registreer eerst via /register.');
        }

        return $user;
    }
}
