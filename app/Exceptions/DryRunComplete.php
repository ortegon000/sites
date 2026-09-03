<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza al final de una corrida en seco para deshacer la transacción. No es
 * un error: es la forma de recorrer el camino real sin dejar rastro.
 */
class DryRunComplete extends RuntimeException {}
