<?php

namespace App\Support;

use RuntimeException;

/** The file changed on disk after the editor loaded it. */
class FileConflictException extends RuntimeException {}
