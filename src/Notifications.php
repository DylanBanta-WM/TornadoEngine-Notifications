<?php

namespace TornadoEngine\Notifications;

use App\Models\User;
use Closure;
use Throwable;

/**
 * What the bell in the menu bar lists. Modules add sources:
 * app(Notifications::class)->source('my-module', fn (User $user) => [['title' => '…', 'text' => '…', 'url' => '…']]).
 */
class Notifications
{
    /** @var array<string, Closure> */
    private array $sources = [];

    public function source(string $key, Closure $source): static
    {
        $this->sources[$key] = $source;

        return $this;
    }

    /** @return list<array{title: string, text: ?string, url: ?string}> */
    public function for(User $user): array
    {
        $notifications = [];
        foreach ($this->sources as $source) {
            try {
                foreach ($source($user) as $notification) {
                    $notifications[] = ['title' => (string) $notification['title'], 'text' => $notification['text'] ?? null, 'url' => $notification['url'] ?? null];
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $notifications;
    }
}
