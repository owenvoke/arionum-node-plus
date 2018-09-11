<?php

namespace App\Console\Commands\Util;

use App\Block;
use Illuminate\Console\Command;

/**
 * Class CurrentCommand
 */
class CurrentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'util:current';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display details about the current block';

    /**
     * Execute the console command.
     *
     * @return mixed|void
     */
    public function handle()
    {
        $block = Block::current();

        if (!$block) {
            $this->output->writeln('<comment>Failed to find current block</comment>');
            return;
        }

        $this->output->table(
            [],
            [
                ['<comment>Id:</comment>', $block->id],
                ['<comment>Generator:</comment>', $block->generator],
                ['<comment>Height:</comment>', $block->height],
                ['<comment>Date:</comment>', $block->date],
                ['<comment>Nonce:</comment>', $block->nonce],
                ['<comment>Signature:</comment>', $block->signature],
                ['<comment>Difficulty:</comment>', $block->difficulty],
                ['<comment>Argon:</comment>', $block->argon],
                ['<comment>Transactions:</comment>', $block->transactions],
            ]
        );
    }
}
