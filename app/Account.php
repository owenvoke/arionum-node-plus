<?php

namespace App;

use App\Helpers\EllipticCurve;
use App\Helpers\Sanitisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use StephenHill\Base58;

/**
 * Class Account
 *
 * @property string $id
 * @property string $public_key
 * @property string $block
 * @property float  $balance
 * @property string $alias
 */
final class Account extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;
    /**
     * @var bool
     */
    public $timestamps = false;
    /**
     * @var array
     */
    protected $casts = [
        'balance' => 'float',
    ];

    // inserts the account in the DB and updates the public key if empty
    public function add($public_key, $block)
    {
        global $db;
        $id = $this->getAddress($public_key);
        $bind = [":id" => $id, ":public_key" => $public_key, ":block" => $block, ":public_key2" => $public_key];

        $db->run(
            "INSERT INTO accounts SET id=:id, public_key=:public_key, block=:block, balance=0 ON DUPLICATE KEY UPDATE public_key=if(public_key='',:public_key2,public_key)",
            $bind
        );
    }

    public function addId(string $id, Block $block): bool
    {
        /** @var self $account */
        $account = self::make([
            'id'         => $id,
            'public_key' => '',
            'block'      => $block->id,
            'balance'    => 0,
        ]);

        return $account->save();
    }

    public function getAddress(string $hash): string
    {
        // phpcs:disable Generic.Files.LineLength

        // Broken base58 addresses, which are block winners, missing the first 0 bytes from the address.
        // TODO: Requires refactoring
        if ($hash == 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwCpspGFGQSaF9yVGLamBgymdf8M7FafghmP3oPzQb3W4PZsZApVa41uQrrHRVBH5p9bdoz7c6XeRQHK2TkzWR45e') {
            return '22SoB29oyq2JhMxtBbesL7JioEYytyC6VeFmzvBH6fRQrueSvyZfEXR5oR7ajSQ9mLERn6JKU85EAbVDNChke32';
        } elseif ($hash == 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCzbRyyz5oDNDKhk5jyjg4caRjkbqegMZMrUkuBjVMuYcVfPyc3aKuLmPHS4QEDjCrNGks7Z5oPxwv4yXSv7WJnkbL') {
            return 'AoFnv3SLujrJSa2J7FDTADGD7Eb9kv3KtNAp7YVYQEUPcLE6cC6nLvvhVqcVnRLYF5BFF38C1DyunUtmfJBhyU';
        } elseif ($hash == 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyradtFFJoaYB4QdcXyBGSXjiASMMnofsT4f5ZNaxTnNDJt91ubemn3LzgKrfQh8CBpqaphkVNoRLub2ctdMnrzG1') {
            return 'RncXQuc7S7aWkvTUJSHEFvYoV3ntAf7bfxEHjSiZNBvQV37MzZtg44L7GAV7szZ3uV8qWqikBewa3piZMqzBqm';
        } elseif ($hash == 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyjKMBY4ihhJ2G25EVezg7KnoCBVbhdvWfqzNA4LC5R7wgu3VNfJgvqkCq9sKKZcCoCpX6Qr9cN882MoXsfGTvZoj') {
            return 'Rq53oLzpCrb4BdJZ1jqQ2zsixV2ukxVdM4H9uvUhCGJCz1q2wagvuXV4hC6UVwK7HqAt1FenukzhVXgzyG1y32';
        }
        // phpcs:enable

        // hashes 9 times in sha512 (binary) and encodes in base58
        for ($i = 0; $i < 9; $i++) {
            $hash = hash('sha512', $hash, true);
        }
        return app(Base58::class)->encode($hash);
    }

    public function checkSignature($data, $signature, $publicKey)
    {
        return EllipticCurve::verify($data, $signature, $publicKey);
    }

    public function validKey(string $id): bool
    {
        $chars = str_split('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
        for ($i = 0; $i < strlen($id);
             $i++) {
            if (!in_array($id[$i], $chars)) {
                return false;
            }
        }

        return true;
    }

    public function freeAlias(string $alias): bool
    {
        $original = $alias;
        $alias = strtoupper($alias);
        $alias = Sanitisation::sanitiseAlphanumeric($alias);

        if (strlen($alias) < 4 || strlen($alias) > 25) {
            return false;
        }

        if ($original !== $alias) {
            return false;
        }

        return self::query()->where('alias', $alias)->exists();
    }

    public function hasAlias(string $publicKey): bool
    {
        $publicKey = Sanitisation::sanitiseAlphanumeric($publicKey);

        return self::query()->where('public_key', $publicKey)->whereNotNull('alias')->exists();
    }

    public function validAlias(string $alias): bool
    {
        $original = $alias;

        $banned = [
            'MERCUR',
            'DEV',
            'DEVELOPMEN',
            'MARKETIN',
            'MERCURY8',
            'DEVAR',
            'DEVELOPE',
            'DEVELOPER',
            'ARODE',
            'DONATIO',
            'MERCATO',
            'OCTAE',
            'MERCUR',
            'ARIONU',
            'ESCRO',
            'OKE',
            'BINANC',
            'CRYPTOPI',
            'HUOB',
            'ITFINE',
            'HITBT',
            'UPBI',
            'COINBAS',
            'KRAKE',
            'BITSTAM',
            'BITTRE',
            'POLONIE',
        ];

        $alias = strtoupper($alias);
        $alias = Sanitisation::sanitiseAlphanumeric($alias);
        if (in_array($alias, $banned)) {
            return false;
        }
        if (strlen($alias) < 4 || strlen($alias) > 25) {
            return false;
        }
        if ($original != $alias) {
            return false;
        }

        return self::query()->where('alias', $alias)->exists();
    }

    public function findByAlias(string $alias): self
    {
        return self::query()->where('alias', strtoupper($alias))->first();
    }

    public function validAddress($id)
    {
        if (strlen($id) < 70 || strlen($id) > 128) {
            return false;
        }
        $chars = str_split('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
        for ($i = 0; $i < strlen($id); $i++) {
            if (!in_array($id[$i], $chars)) {
                return false;
            }
        }

        return true;
    }

    public function balance(): string
    {
        return number_format($this->balance, 8, '.', '');
    }

    public function pendingBalance(): string
    {
        $currentBalance = $this->balance ?? '0.00000000';

        // if the original balance is 0, no mempool transactions are possible
        if ($currentBalance === '0.00000000') {
            return $currentBalance;
        }

        /** @var Mempool $mempool */
        $mempool = Mempool::query()->where('src', $this->id)->first(['val', 'fee']);
        $result = $currentBalance - ($mempool->val + $mempool->fee);

        return number_format($result, 8, '.', '');
    }

    public function getTransactions($limit = 100): Builder
    {
        return Transaction::query()
            ->where(function (Builder $query) {
                $query->where('dst', $this->public_key)
                    ->orWhere('public_key', $this->public_key)
                    ->orWhere('dst', $this->alias);
            })
            ->orderByDesc('height')
            ->limit($limit);
    }

    public function getMempoolTransactions(): Builder
    {
        return Mempool::query()->where(function (Builder $query) {
            $query->where('src', $this->id)
                ->orWhere('dst', $this->id);
        });
    }

    public function getMasternode(): Masternode
    {
        return Masternode::query()->where('public_key', $this->public_key)->first();
    }
}
