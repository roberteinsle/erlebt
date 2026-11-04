<?php

namespace App\Support;

use RuntimeException;

/** Fehler, deren Meldung direkt in der Oberfläche angezeigt werden darf. */
class UserFacingException extends RuntimeException {}
