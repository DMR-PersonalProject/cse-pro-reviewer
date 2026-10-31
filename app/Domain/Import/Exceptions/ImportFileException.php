<?php

declare(strict_types=1);

namespace App\Domain\Import\Exceptions;

use RuntimeException;

/**
 * The whole file can't be read as an import (bad JSON, wrong schema_version, missing CSV header).
 * Row-level problems are errors on the row instead.
 */
class ImportFileException extends RuntimeException {}
