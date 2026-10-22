<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('axcess:detect-conflicts --days=60')->dailyAt('01:00');
