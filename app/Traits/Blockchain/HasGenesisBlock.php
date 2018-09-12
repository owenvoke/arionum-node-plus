<?php

namespace App\Traits\Blockchain;

use App\Block;
use Carbon\Carbon;

/**
 * Trait HasGenesisBlock
 */
trait HasGenesisBlock
{
    /**
     * @return bool
     * @throws \Throwable
     */
    final public static function genesis(): bool
    {
        if (Block::query()->count() > 0) {
            return false;
        }

        $block = new Block();

        // phpcs:disable Generic.Files.LineLength
        $block->id = 'L6oyJzUD7FkbyLYMeps6qAh7iTzrHvPHMqq8x9dyiUAbGDHAFqWrbbTK1rPaJ9mh8UReDhQvMRwCwPTpU6Z4Zgv';
        $block->generator = '2P67zUANj7NRKTruQ8nJRHNdKMroY6gLw4NjptTVmYk6Hh1QPYzzfEa9z4gv8qJhuhCNM8p9GDAEDqGUU1awaLW6';
        $block->height = 1;
        $block->date = Carbon::createFromTimestampUTC(1515324995);
        $block->nonce = "4QRKTSJ+i9Gf9ubPo487eSi+eWOnIBt9w4Y+5J+qbh8=";
        $block->signature = 'AN1rKvtLTWvZorbiiNk5TBYXLgxiLakra2byFef9qoz1bmRzhQheRtiWivfGSwP6r8qHJGrf8uBeKjNZP1GZvsdKUVVN2XQoL';
        $block->difficulty = 5555555555;
        $block->argon = '$M1ZpVzYzSUxYVFp6cXEwWA$CA6p39MVX7bvdXdIIRMnJuelqequanFfvcxzQjlmiik';
        $block->transactions = 0;
        // phpcs:enable

        return $block->saveOrFail();
    }
}
