<?php

namespace App\Console\Commands;

use App\Actions\Recipes\ClassifyRecipeTags;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('recipes:classify {--user= : Restrict to a single user (id or email)} {--all : Re-classify recipes that already have tags} {--limit= : Stop after this many recipes} {--dry-run : Print intent without writing}')]
#[Description('Auto-classify untagged recipes via the LLM and attach matching system tags.')]
class ClassifyRecipes extends Command
{
    public function handle(ClassifyRecipeTags $classify): int
    {
        $query = Recipe::query()->with(['ingredients', 'steps']);

        if ($userOpt = $this->option('user')) {
            $user = User::query()
                ->where('id', $userOpt)
                ->orWhere('email', $userOpt)
                ->first();
            if ($user === null) {
                $this->error("User not found: {$userOpt}");

                return self::FAILURE;
            }
            $query->where('user_id', $user->id);
        }

        if (! $this->option('all')) {
            $query->whereDoesntHave('tags');
        }

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to classify.');

            return self::SUCCESS;
        }

        $this->info("Classifying {$total} recipe(s)...");
        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no tags will be attached.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['classified' => 0, 'no_tags' => 0, 'errors' => 0];

        $query->orderBy('id')->each(function (Recipe $recipe) use ($classify, $bar, &$stats) {
            try {
                if ($this->option('dry-run')) {
                    $stats['classified']++;
                } else {
                    $attached = $classify->handle($recipe);
                    if ($attached === []) {
                        $stats['no_tags']++;
                    } else {
                        $stats['classified']++;
                    }
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                $bar->clear();
                $this->error("Recipe #{$recipe->id} ({$recipe->title}): ".$e->getMessage());
                $bar->display();
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. {$stats['classified']} tagged, {$stats['no_tags']} got nothing, {$stats['errors']} errors.");

        if (! $this->option('dry-run')) {
            $tagCount = Tag::query()->count();
            $this->line("Total tags in DB: {$tagCount}");
        }

        return self::SUCCESS;
    }
}
