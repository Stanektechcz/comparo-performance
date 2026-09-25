<?php

namespace App\Domain\Platform\Queues;

use RuntimeException;

/**
 * The long-running queue connection would re-deliver jobs that are still
 * running ({@see LongRunningQueue}).
 */
final class LongRunningQueueMisconfigured extends RuntimeException {}
