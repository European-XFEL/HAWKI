<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('user:reset_quotas')->monthlyOn(1, '00:15');
