<?php

namespace App\Console\Commands\Util;

use App\Mempool;
use Illuminate\Console\Command;

/**
 * Class MempoolCommand
 */
final class MempoolCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'util:mempool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display details about the mempool';

    /**
     * Execute the console command.
     *
     * @return mixed|void
     */
    public function handle()
    {
        $this->output->table(
            [],
            [
                ['<comment>Mempool size:</comment>', Mempool::count()],
            ]
        );
    }
}
