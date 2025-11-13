<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('user:reset_quotas')->daily()->at('00:05');
