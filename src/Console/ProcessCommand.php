<?php

namespace Osit\Webaseo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * ProcessCommand - main class
 *
 * ProcessCommand
 * distributed under the MIT License
 *
 * @author  Dominic Moeketsi developer@osit.co.za
 * @company OmniSol Information Technology (PTY) LTD
 * @version 1.0.0
 */
class ProcessCommand extends Command
{
    protected $signature = "webaseo:init";
    protected $description = "Running WebaSEO process command.";

    /**
     * Initiates Webaseo in your app.
     *
     * @return void
     */
    public function handle()
    {
        // Create a local copy of the config file and assets
        $result = Process::run("php artisan vendor:publish --tag=webaseo-config");
        echo $result->output();
    }
}