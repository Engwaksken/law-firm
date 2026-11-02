<?php

namespace App\Contracts;

/**
 * Models whose access is decided per record by their policy alone.
 * Gate::before does NOT grant the System Owner automatic access to these
 * (ethical walls on restricted matters must hold for everyone).
 */
interface HasRecordLevelAccess {}
