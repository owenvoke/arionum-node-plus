<?php

namespace App\Console\Commands\Util;

use App\Block;
use Illuminate\Console\Command;

/**
 * Class BlockCommand
 */
final class BlockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'util:block {block-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display details about the specified block';

    /**
     * Execute the console command.
     *
     * @return mixed|void
     */
    public function handle()
    {
        $blockId = $this->input->getArgument('block-id');

        $block = Block::find($blockId);

        if (!$block) {
            $this->output->writeln('<comment>Failed to find block with id:</comment> '.$blockId);
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
