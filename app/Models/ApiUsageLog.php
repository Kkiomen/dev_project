<?php

namespace App\Models;

/**
 * Alias for AiOperationLog for semantic naming.
 *
 * Use this class when working with external API logging (Pexels, Facebook, etc.)
 * rather than AI-specific operations.
 */
class ApiUsageLog extends AiOperationLog
{
    // This is just an alias class for semantic naming
    // All functionality is inherited from AiOperationLog
}
