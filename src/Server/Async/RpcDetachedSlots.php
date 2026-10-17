<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function bin2hex;
use function clearstatcache;
use function fclose;
use function file_exists;
use function flock;
use function fopen;
use function is_dir;
use function microtime;
use function mkdir;
use function random_bytes;
use function rename;
use function touch;
use function unlink;
use function usleep;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * Detached `ufo:rpc:process` bookkeeping: a hand-over ticket per request (see createTicket()) and
 * a limit of how many detached processes run at the same time, across all workers of the server:
 * a detached process holds the lock of its slot file while it lives (the OS releases it on exit).
 * Approximate on purpose: two workers checking at the same moment may pick the same slot.
 */
class RpcDetachedSlots
{
    /**
     * @var resource|null
     */
    protected $held = null;

    public function __construct(
        #[Autowire('%kernel.cache_dir%')]
        protected string $cacheDir,
    ) {}

    /**
     * Index of a slot no detached process holds right now, null when all of them are busy.
     */
    public function free(int $count): ?int
    {
        for ($slot = 0; $slot < $count; $slot++) {
            if (!$handle = @fopen($this->path($slot), 'c')) continue;
            $isFree = flock($handle, LOCK_EX | LOCK_NB);
            if ($isFree) flock($handle, LOCK_UN);
            fclose($handle);
            if ($isFree) return $slot;
        }
        return null;
    }

    /**
     * Called in the detached process: keeps the slot until the process exits.
     */
    public function hold(int $slot): void
    {
        if ($this->held || !$handle = @fopen($this->path($slot), 'c')) return;
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            $this->held = $handle;
        } else {
            fclose($handle);
        }
    }

    /**
     * Hand-over of one request from the worker to a detached process: whoever renames the ticket first
     * (atomic) executes the request, so it runs exactly once and is never lost.
     */
    public function createTicket(): string
    {
        $ticket = $this->dir().'/ticket-'.bin2hex(random_bytes(8));
        touch($ticket);
        return $ticket;
    }

    /**
     * Takes the request over. Detached process: right before executing it; worker: on timeout.
     *
     * @return bool false when the other side has already taken it
     */
    public function claim(string $ticket): bool
    {
        if (!@rename($ticket, $ticket.'.claimed')) {
            return false;
        }
        @unlink($ticket.'.claimed');
        return true;
    }

    /**
     * Worker: waits until the detached process claims the request; on timeout takes it back.
     *
     * @return bool true: the detached process executes it; false: execute it here
     */
    public function awaitClaim(string $ticket, float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        do {
            clearstatcache(true, $ticket);
            if (!file_exists($ticket)) {
                return true;
            }
            usleep(5000);
        } while (microtime(true) < $deadline);

        return !$this->claim($ticket);
    }

    protected function path(int $slot): string
    {
        return $this->dir().'/slot-'.$slot.'.lock';
    }

    protected function dir(): string
    {
        $dir = $this->cacheDir.'/ufo_rpc_detached';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }
}
